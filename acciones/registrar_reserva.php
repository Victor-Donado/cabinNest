<?php
// acciones/registrar_reserva.php

require_once __DIR__ . '/../configuracion/conexion.php';
require_once __DIR__ . '/../seguridad/proteger_cliente.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../catalogo.php');
    exit;
}

if (!verificarTokenCsrf($_POST['token_csrf'] ?? null)) {
    $_SESSION['error_reserva'] = 'La sesión del formulario expiró. Intenta nuevamente.';
    header('Location: ../catalogo.php');
    exit;
}

$documentoCliente = (int) $_SESSION['documento'];
$idCabana         = filter_var($_POST['id_cabana'] ?? '', FILTER_VALIDATE_INT);
$fechaCheckin     = trim($_POST['fecha_checkin'] ?? '');
$fechaCheckout    = trim($_POST['fecha_checkout'] ?? '');
$metodoPago       = trim($_POST['metodo_pago'] ?? '');
$tipoPago         = trim($_POST['tipo_pago'] ?? 'Pago Total');

$metodosPermitidos = ['Tarjeta Crédito', 'Tarjeta Débito', 'PSE', 'Transferencia', 'Efectivo'];
$tiposPermitidos   = ['Pago Total', 'Anticipo 50%'];

if (!$idCabana || $fechaCheckin === '' || $fechaCheckout === '') {
    $_SESSION['error_reserva'] = 'Selecciona una cabaña y un rango de fechas válido.';
    header('Location: ../catalogo.php');
    exit;
}

if (!in_array($metodoPago, $metodosPermitidos, true) || !in_array($tipoPago, $tiposPermitidos, true)) {
    $_SESSION['error_reserva'] = 'Selecciona un método y tipo de pago válidos.';
    header('Location: ../pagar.php');
    exit;
}

// Validar formato y coherencia de fechas
$objetoCheckin  = DateTime::createFromFormat('Y-m-d', $fechaCheckin);
$objetoCheckout = DateTime::createFromFormat('Y-m-d', $fechaCheckout);
$hoy            = new DateTime('today');

if (!$objetoCheckin || !$objetoCheckout || $objetoCheckin < $hoy || $objetoCheckout <= $objetoCheckin) {
    $_SESSION['error_reserva'] = 'La fecha de salida debe ser posterior a la de entrada y no anterior a hoy.';
    header('Location: ../reserva.php?id_cabana=' . $idCabana);
    exit;
}

$cantidadNoches = (int) $objetoCheckin->diff($objetoCheckout)->days;
$conexion       = obtenerConexion();

try {
    // 1. Obtener nombre y apellido del titular desde `usuario`
    $consultaCliente = $conexion->prepare(
        'SELECT nombre, apellido FROM `usuario` WHERE documento = :documento LIMIT 1'
    );
    $consultaCliente->execute([':documento' => $documentoCliente]);
    $datosCliente = $consultaCliente->fetch();

    if (!$datosCliente) {
        $_SESSION['error_reserva'] = 'No se encontró la cuenta del cliente autenticado.';
        header('Location: ../login.php');
        exit;
    }

    // 2. Verificar que la cabaña exista y obtener su tarifa activa real desde MySQL
    $consultaTarifa = $conexion->prepare(
        'SELECT c.`id_cabaña` AS id_cabana, c.nombre, c.capacidad, t.id_tarifa, t.precio_noche
         FROM `cabaña` c
         INNER JOIN `tarifa` t ON c.`id_cabaña` = t.`id_cabaña`
         WHERE c.`id_cabaña` = :id_cabana AND t.activa = 1
         LIMIT 1'
    );
    $consultaTarifa->execute([':id_cabana' => $idCabana]);
    $datosCabana = $consultaTarifa->fetch();

    if (!$datosCabana) {
        $_SESSION['error_reserva'] = 'La cabaña seleccionada no tiene una tarifa activa disponible.';
        header('Location: ../catalogo.php');
        exit;
    }

    // 3. Comprobar que no exista otra reserva activa que se cruce con estas fechas
    $consultaCruce = $conexion->prepare(
        'SELECT COUNT(*)
         FROM `reserva` r
         WHERE r.`id_cabaña` = :id_cabana
           AND r.estado <> \'Cancelada\'
           AND r.fecha_checkin < :fecha_checkout
           AND r.fecha_checkout > :fecha_checkin'
    );
    $consultaCruce->execute([
        ':id_cabana'      => $idCabana,
        ':fecha_checkin'  => $fechaCheckin,
        ':fecha_checkout' => $fechaCheckout,
    ]);

    if ((int) $consultaCruce->fetchColumn() > 0) {
        $_SESSION['error_reserva'] = 'La cabaña ya está reservada en esas fechas. Elige otro rango.';
        header('Location: ../reserva.php?id_cabana=' . $idCabana);
        exit;
    }

    // 4. Armar lista consolidada de huéspedes (titular activo + acompañantes enviados)
    $listaHuespedes = [
        $documentoCliente => [
            'documento' => $documentoCliente,
            'nombre'    => (string) $datosCliente['nombre'],
            'apellido'  => (string) $datosCliente['apellido'],
        ],
    ];

    $huespedesEntrada = !empty($_POST['huespedes']) && is_array($_POST['huespedes'])
        ? $_POST['huespedes']
        : ($_SESSION['borrador_reserva']['huespedes'] ?? []);

    foreach ($huespedesEntrada as $item) {
        $doc = filter_var($item['documento'] ?? '', FILTER_VALIDATE_INT);
        $nom = trim((string) ($item['nombre'] ?? ''));
        $ape = trim((string) ($item['apellido'] ?? ''));

        if ($doc && $doc > 0 && $doc !== $documentoCliente && $nom !== '' && $ape !== '') {
            $listaHuespedes[$doc] = [
                'documento' => (int) $doc,
                'nombre'    => mb_substr($nom, 0, 80),
                'apellido'  => mb_substr($ape, 0, 80),
            ];
        }
    }

    // 5. Calcular totales en el servidor
    $precioNoche  = (float) $datosCabana['precio_noche'];
    $totalReserva = round($precioNoche * $cantidadNoches, 2);
    $montoPagar   = ($tipoPago === 'Anticipo 50%') ? round($totalReserva * 0.5, 2) : $totalReserva;
    $ahora        = date('Y-m-d H:i:s');

    $conexion->beginTransaction();

    // 6. Insertar en `reserva`
    $insertarReserva = $conexion->prepare(
        'INSERT INTO `reserva` (
            fecha_reserva, fecha_checkin, fecha_checkout, estado,
            id_cliente, cantidad_noches, `id_cabaña`, id_tarifa, total
        ) VALUES (
            :fecha_reserva, :fecha_checkin, :fecha_checkout, :estado,
            :id_cliente, :cantidad_noches, :id_cabana, :id_tarifa, :total
        )'
    );
    $insertarReserva->execute([
        ':fecha_reserva'   => $ahora,
        ':fecha_checkin'   => $fechaCheckin,
        ':fecha_checkout'  => $fechaCheckout,
        ':estado'          => 'Confirmada',
        ':id_cliente'      => $documentoCliente,
        ':cantidad_noches' => $cantidadNoches,
        ':id_cabana'       => (int) $datosCabana['id_cabana'],
        ':id_tarifa'       => (int) $datosCabana['id_tarifa'],
        ':total'           => number_format($totalReserva, 2, '.', ''),
    ]);

    $idReserva = (int) $conexion->lastInsertId();

    // 7. Crear cada huésped en `huesped` si no existe y asociarlo en `hospedaje`
    $guardarHuesped = $conexion->prepare(
        'INSERT INTO `huesped` (documento, nombre, apellido)
         VALUES (:documento, :nombre, :apellido)
         ON DUPLICATE KEY UPDATE
            nombre = VALUES(nombre),
            apellido = VALUES(apellido)'
    );

    $insertarHospedaje = $conexion->prepare(
        'INSERT IGNORE INTO `hospedaje` (id_reserva, id_huesped)
         VALUES (:id_reserva, :id_huesped)'
    );

    foreach ($listaHuespedes as $huesped) {
        $guardarHuesped->execute([
            ':documento' => $huesped['documento'],
            ':nombre'    => $huesped['nombre'],
            ':apellido'  => $huesped['apellido'],
        ]);

        $insertarHospedaje->execute([
            ':id_reserva' => $idReserva,
            ':id_huesped' => $huesped['documento'],
        ]);
    }

    // 8. Registrar el pago en `pago`
    $insertarPago = $conexion->prepare(
        'INSERT INTO `pago` (id_reserva, fecha_pago, monto, metodo_pago, tipo_pago, id_usuario)
         VALUES (:id_reserva, :fecha_pago, :monto, :metodo_pago, :tipo_pago, :id_usuario)'
    );
    $insertarPago->execute([
        ':id_reserva'  => $idReserva,
        ':fecha_pago'  => $ahora,
        ':monto'       => number_format($montoPagar, 2, '.', ''),
        ':metodo_pago' => $metodoPago,
        ':tipo_pago'   => $tipoPago,
        ':id_usuario'  => $documentoCliente,
    ]);

    $conexion->commit();

    unset($_SESSION['borrador_reserva']);
    $_SESSION['exito_cuenta'] = "Reserva #{$idReserva} confirmada correctamente.";
    header('Location: ../cuenta.php');
    exit;
} catch (Throwable $error) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    error_log('Error al registrar reserva y pago: ' . $error->getMessage());
    $_SESSION['error_reserva'] = 'Ocurrió un error interno al procesar tu reserva. Intenta nuevamente.';
    header('Location: ../catalogo.php');
    exit;
}
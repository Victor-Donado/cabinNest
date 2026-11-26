<?php
// acciones/guardar_resena.php

require_once __DIR__ . '/../configuracion/conexion.php';
require_once __DIR__ . '/../seguridad/proteger_cliente.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../cuenta.php');
    exit;
}

if (!verificarTokenCsrf($_POST['token_csrf'] ?? null)) {
    $_SESSION['error_accion'] = 'La sesión del formulario expiró. Intenta nuevamente.';
    header('Location: ../cuenta.php');
    exit;
}

$documentoCliente = (int) $_SESSION['documento'];
$idReserva        = filter_var($_POST['id_reserva'] ?? '', FILTER_VALIDATE_INT);
$calificacion     = filter_var(
    $_POST['calificacion'] ?? '',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 0, 'max_range' => 5]]
);
$comentario       = trim((string) ($_POST['comentario'] ?? ''));

if (!$idReserva || $calificacion === false || $comentario === '') {
    $_SESSION['error_accion'] = 'Debes seleccionar una puntuación de 0 a 5 y escribir un comentario.';
    header('Location: ../cuenta.php');
    exit;
}

$comentario = mb_substr($comentario, 0, 500);
$conexion   = obtenerConexion();

try {
    // 1. Verificar que la reserva exista, pertenezca al cliente y esté finalizada
    $consultaReserva = $conexion->prepare(
        'SELECT r.id_reserva, r.`id_cabaña` AS id_cabana, r.estado
         FROM `reserva` r
         WHERE r.id_reserva = :id_reserva
           AND r.id_cliente = :id_cliente
         LIMIT 1'
    );
    $consultaReserva->execute([
        ':id_reserva' => $idReserva,
        ':id_cliente' => $documentoCliente,
    ]);
    $reserva = $consultaReserva->fetch();

    if (!$reserva || !in_array($reserva['estado'], ['Completada', 'Finalizada'], true)) {
        $_SESSION['error_accion'] = 'Solo puedes reseñar reservas cuyo estado haya finalizado.';
        header('Location: ../cuenta.php');
        exit;
    }

    $idCabana = (int) $reserva['id_cabana'];

    // 2. Detectar columnas disponibles en `reseña` para compatibilidad exacta con tu tabla
    $columnas = $conexion->query('SHOW COLUMNS FROM `reseña`')->fetchAll(PDO::FETCH_COLUMN);

    $colUsuario = null;
    foreach (['id_usuario', 'id_cliente', 'documento'] as $posibleCol) {
        if (in_array($posibleCol, $columnas, true)) {
            $colUsuario = $posibleCol;
            break;
        }
    }

    $tieneIdReserva = in_array('id_reserva', $columnas, true);

    $colFecha = null;
    foreach (['fecha_resena', 'fecha', 'fecha_creacion'] as $posibleFecha) {
        if (in_array($posibleFecha, $columnas, true)) {
            $colFecha = $posibleFecha;
            break;
        }
    }

    // 3. Evitar reseñas duplicadas para la misma reserva
    if ($tieneIdReserva && $colUsuario !== null) {
        $verificarDup = $conexion->prepare(
            "SELECT COUNT(*) FROM `reseña` WHERE id_reserva = :id_reserva AND `{$colUsuario}` = :doc"
        );
        $verificarDup->execute([
            ':id_reserva' => $idReserva,
            ':doc'        => $documentoCliente,
        ]);
        if ((int) $verificarDup->fetchColumn() > 0) {
            $_SESSION['error_accion'] = 'Ya enviaste una reseña para esta reserva.';
            header('Location: ../cuenta.php');
            exit;
        }
    }

    // 4. Construir e insertar la reseña en MySQL
    $camposInsert = ['`id_cabaña`', 'calificacion', 'comentario'];
    $valoresParam = [':id_cabana', ':calificacion', ':comentario'];
    $parametros   = [
        ':id_cabana'    => $idCabana,
        ':calificacion' => (int) $calificacion,
        ':comentario'   => $comentario,
    ];

    if ($colUsuario !== null) {
        $camposInsert[]          = "`{$colUsuario}`";
        $valoresParam[]          = ':id_usuario';
        $parametros[':id_usuario'] = $documentoCliente;
    }

    if ($tieneIdReserva) {
        $camposInsert[]          = 'id_reserva';
        $valoresParam[]          = ':id_reserva';
        $parametros[':id_reserva'] = $idReserva;
    }

    if ($colFecha !== null) {
        $camposInsert[]     = "`{$colFecha}`";
        $valoresParam[]     = ':fecha';
        $parametros[':fecha'] = date('Y-m-d H:i:s');
    }

    $sqlInsert = sprintf(
        'INSERT INTO `reseña` (%s) VALUES (%s)',
        implode(', ', $camposInsert),
        implode(', ', $valoresParam)
    );

    $insertarResena = $conexion->prepare($sqlInsert);
    $insertarResena->execute($parametros);

    $_SESSION['exito_cuenta'] = '¡Gracias por tu reseña! Tu calificación fue registrada correctamente.';
    header('Location: ../cuenta.php');
    exit;
} catch (Throwable $error) {
    error_log('Error al guardar reseña: ' . $error->getMessage());
    $_SESSION['error_accion'] = 'Ocurrió un error al guardar tu reseña. Intenta nuevamente.';
    header('Location: ../cuenta.php');
    exit;
}
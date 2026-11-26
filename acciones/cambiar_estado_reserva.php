<?php
// acciones/cambiar_estado_reserva.php

require_once __DIR__ . '/../configuracion/conexion.php';
require_once __DIR__ . '/../seguridad/proteger_admin.php';

$esPeticionJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
    || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

function responderCambioEstado(bool $exito, string $mensaje, int $codigoHttp, bool $esJson): void
{
    if ($esJson) {
        http_response_code($codigoHttp);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['exito' => $exito, 'mensaje' => $mensaje]);
        exit;
    }

    $_SESSION[$exito ? 'exito_reserva_admin' : 'error_reserva_admin'] = $mensaje;
    header('Location: ../reservas.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderCambioEstado(false, 'Método no permitido.', 405, $esPeticionJson);
}

if (!verificarTokenCsrf($_POST['token_csrf'] ?? null)) {
    responderCambioEstado(false, 'Token de seguridad inválido.', 403, $esPeticionJson);
}

$idReserva   = filter_var($_POST['id_reserva'] ?? '', FILTER_VALIDATE_INT);
$nuevoEstado = trim($_POST['estado'] ?? '');

$estadosPermitidos = ['Pendiente', 'Confirmada', 'En curso', 'Completada', 'Cancelada'];

if (!$idReserva || !in_array($nuevoEstado, $estadosPermitidos, true)) {
    responderCambioEstado(false, 'Datos de reserva o estado inválidos.', 400, $esPeticionJson);
}

$conexion = obtenerConexion();

try {
    $conexion->beginTransaction();

    // 1. Obtener la cabaña vinculada a la reserva
    $consultaCabana = $conexion->prepare(
        'SELECT t.`id_cabaña`
         FROM `reserva` r
         INNER JOIN `tarifa` t ON r.id_tarifa = t.id_tarifa
         WHERE r.id_reserva = :id_reserva
         LIMIT 1'
    );
    $consultaCabana->execute([':id_reserva' => $idReserva]);
    $idCabana = $consultaCabana->fetchColumn();

    if (!$idCabana) {
        $conexion->rollBack();
        responderCambioEstado(false, 'No se encontró la reserva solicitada.', 404, $esPeticionJson);
    }

    // 2. Actualizar el estado de la reserva
    $actualizarReserva = $conexion->prepare(
        'UPDATE `reserva` SET estado = :estado WHERE id_reserva = :id_reserva'
    );
    $actualizarReserva->execute([
        ':estado'     => $nuevoEstado,
        ':id_reserva' => $idReserva,
    ]);

    // 3. Sincronizar disponibilidad de la cabaña en `cabaña.ocupado`
    if ($nuevoEstado === 'En curso') {
        $conexion->prepare('UPDATE `cabaña` SET ocupado = 1 WHERE `id_cabaña` = :id_cabana')
            ->execute([':id_cabana' => $idCabana]);
    } elseif ($nuevoEstado === 'Completada' || $nuevoEstado === 'Cancelada') {
        $verificarOtrasEnCurso = $conexion->prepare(
            'SELECT COUNT(*)
             FROM `reserva` r
             INNER JOIN `tarifa` t ON r.id_tarifa = t.id_tarifa
             WHERE t.`id_cabaña` = :id_cabana
               AND r.estado = \'En curso\'
               AND r.id_reserva <> :id_reserva'
        );
        $verificarOtrasEnCurso->execute([
            ':id_cabana'  => $idCabana,
            ':id_reserva' => $idReserva,
        ]);

        if ((int) $verificarOtrasEnCurso->fetchColumn() === 0) {
            $conexion->prepare('UPDATE `cabaña` SET ocupado = 0 WHERE `id_cabaña` = :id_cabana')
                ->execute([':id_cabana' => $idCabana]);
        }
    }

    $conexion->commit();
    responderCambioEstado(true, "Reserva #{$idReserva} actualizada a \"{$nuevoEstado}\".", 200, $esPeticionJson);
} catch (Throwable $error) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    error_log('Error al actualizar estado de reserva: ' . $error->getMessage());
    responderCambioEstado(false, 'Error interno al actualizar la reserva.', 500, $esPeticionJson);
}
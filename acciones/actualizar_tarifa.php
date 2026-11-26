<?php
// acciones/actualizar_tarifa.php

require_once __DIR__ . '/../configuracion/conexion.php';
require_once __DIR__ . '/../seguridad/proteger_admin.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['exito' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}

if (!verificarTokenCsrf($_POST['token_csrf'] ?? null)) {
    http_response_code(403);
    echo json_encode(['exito' => false, 'mensaje' => 'Token de seguridad inválido.']);
    exit;
}

$idCabana = filter_var($_POST['id_cabana'] ?? '', FILTER_VALIDATE_INT);
$idTarifa = filter_var($_POST['id_tarifa'] ?? '', FILTER_VALIDATE_INT);

if (!$idCabana || !$idTarifa) {
    http_response_code(400);
    echo json_encode(['exito' => false, 'mensaje' => 'Datos de tarifa incompletos.']);
    exit;
}

$conexion = obtenerConexion();

try {
    $conexion->beginTransaction();

    $desactivarTodas = $conexion->prepare(
        'UPDATE `tarifa` SET activa = 0 WHERE `id_cabaña` = :id_cabana'
    );
    $desactivarTodas->execute([':id_cabana' => $idCabana]);

    $activarSeleccionada = $conexion->prepare(
        'UPDATE `tarifa` SET activa = 1 WHERE id_tarifa = :id_tarifa AND `id_cabaña` = :id_cabana'
    );
    $activarSeleccionada->execute([
        ':id_tarifa' => $idTarifa,
        ':id_cabana' => $idCabana,
    ]);

    if ($activarSeleccionada->rowCount() === 0) {
        $conexion->rollBack();
        http_response_code(404);
        echo json_encode(['exito' => false, 'mensaje' => 'No se encontró la tarifa seleccionada.']);
        exit;
    }

    $conexion->commit();
    echo json_encode(['exito' => true, 'mensaje' => 'Tarifa actualizada correctamente.']);
} catch (Throwable $error) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    error_log('Error al actualizar tarifa: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['exito' => false, 'mensaje' => 'Error interno al actualizar la tarifa.']);
}
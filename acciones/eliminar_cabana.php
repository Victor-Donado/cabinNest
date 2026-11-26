<?php
// acciones/eliminar_cabana.php

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

if (!$idCabana) {
    http_response_code(400);
    echo json_encode(['exito' => false, 'mensaje' => 'ID de cabaña inválido.']);
    exit;
}

$conexion = obtenerConexion();

try {
    // Verificar si la cabaña tiene reservas asociadas para proteger la integridad de datos
    $consultaReservas = $conexion->prepare(
        'SELECT COUNT(*) FROM `reserva` r
         INNER JOIN `tarifa` t ON r.id_tarifa = t.id_tarifa
         WHERE t.`id_cabaña` = :id_cabana'
    );
    $consultaReservas->execute([':id_cabana' => $idCabana]);

    if ((int) $consultaReservas->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode([
            'exito'   => false,
            'mensaje' => 'No se puede eliminar esta cabaña porque tiene reservas registradas en el historial.',
        ]);
        exit;
    }

    // Obtener nombres de archivos físicos para borrarlos de subidas/cabanas/
    $consultaFotos = $conexion->prepare(
        'SELECT foto FROM `cabaña_foto` WHERE `id_cabaña` = :id_cabana'
    );
    $consultaFotos->execute([':id_cabana' => $idCabana]);
    $fotosAEliminar = $consultaFotos->fetchAll(PDO::FETCH_COLUMN);

    $conexion->beginTransaction();

    $conexion->prepare('DELETE FROM `inventario` WHERE `id_cabaña` = :id_cabana')->execute([':id_cabana' => $idCabana]);
    $conexion->prepare('DELETE FROM `tarifa` WHERE `id_cabaña` = :id_cabana')->execute([':id_cabana' => $idCabana]);
    $conexion->prepare('DELETE FROM `cabaña_foto` WHERE `id_cabaña` = :id_cabana')->execute([':id_cabana' => $idCabana]);
    $conexion->prepare('DELETE FROM `cabaña` WHERE `id_cabaña` = :id_cabana')->execute([':id_cabana' => $idCabana]);

    $conexion->commit();

    $carpetaSubidas = __DIR__ . '/../subidas/cabanas';
    foreach ($fotosAEliminar as $archivoFoto) {
        $rutaFisica = $carpetaSubidas . '/' . basename($archivoFoto);
        if (is_file($rutaFisica)) {
            @unlink($rutaFisica);
        }
    }

    echo json_encode(['exito' => true, 'mensaje' => 'Cabaña eliminada correctamente.']);
} catch (Throwable $error) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    error_log('Error al eliminar cabaña: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['exito' => false, 'mensaje' => 'Error interno al eliminar la cabaña.']);
}
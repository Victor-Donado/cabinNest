<?php
// acciones/cancelar_reserva.php

require_once __DIR__ . '/../configuracion/conexion.php';
require_once __DIR__ . '/../seguridad/sesion.php';

iniciarSesionSegura();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['documento'])) {
    header('Location: ../index.php');
    exit;
}

$esAdmin     = ($_SESSION['tipo_cuenta'] ?? '') === 'administrador';
$rutaDestino = $esAdmin ? '../reservas.php' : '../cuenta.php';

if (!verificarTokenCsrf($_POST['token_csrf'] ?? null)) {
    $_SESSION['error_accion'] = 'La sesión del formulario expiró.';
    header('Location: ' . $rutaDestino);
    exit;
}

$idReserva = filter_var($_POST['id_reserva'] ?? '', FILTER_VALIDATE_INT);

if (!$idReserva) {
    header('Location: ' . $rutaDestino);
    exit;
}

$conexion = obtenerConexion();

try {
    if ($esAdmin) {
        $consulta = $conexion->prepare(
            'UPDATE `reserva` SET estado = \'Cancelada\' WHERE id_reserva = :id_reserva'
        );
        $consulta->execute([':id_reserva' => $idReserva]);
    } else {
        // El huésped solo puede cancelar reservas que pertenezcan a su propio documento
        $consulta = $conexion->prepare(
            'UPDATE `reserva`
             SET estado = \'Cancelada\'
             WHERE id_reserva = :id_reserva AND id_cliente = :id_cliente'
        );
        $consulta->execute([
            ':id_reserva' => $idReserva,
            ':id_cliente' => (int) $_SESSION['documento'],
        ]);
    }

    $_SESSION['exito_cuenta'] = "La reserva #{$idReserva} fue cancelada.";
    header('Location: ' . $rutaDestino);
    exit;
} catch (Throwable $error) {
    error_log('Error al cancelar reserva: ' . $error->getMessage());
    $_SESSION['error_accion'] = 'No se pudo cancelar la reserva.';
    header('Location: ' . $rutaDestino);
    exit;
}
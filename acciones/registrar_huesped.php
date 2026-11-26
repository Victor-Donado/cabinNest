<?php
// acciones/registrar_huesped.php

require_once __DIR__ . '/../configuracion/conexion.php';
require_once __DIR__ . '/../seguridad/sesion.php';

iniciarSesionSegura();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../signup.php');
    exit;
}

if (!verificarTokenCsrf($_POST['token_csrf'] ?? null)) {
    $_SESSION['error_registro'] = 'La sesión del formulario expiró. Intenta nuevamente.';
    header('Location: ../signup.php');
    exit;
}

$documento  = filter_var($_POST['documento'] ?? '', FILTER_VALIDATE_INT);
$nombre     = trim($_POST['nombre'] ?? '');
$apellido   = trim($_POST['apellido'] ?? '');
$telefono   = preg_replace('/\D/', '', $_POST['telefono'] ?? '');
$correo     = filter_var(trim($_POST['correo'] ?? ''), FILTER_VALIDATE_EMAIL);
$contrasena = $_POST['contrasena'] ?? '';

if (!$documento || $documento <= 0) {
    $_SESSION['error_registro'] = 'Ingresa un número de documento válido.';
    header('Location: ../signup.php');
    exit;
}

if ($nombre === '' || mb_strlen($nombre) > 80 || $apellido === '' || mb_strlen($apellido) > 80) {
    $_SESSION['error_registro'] = 'El nombre y el apellido son obligatorios (máximo 80 caracteres).';
    header('Location: ../signup.php');
    exit;
}

if ($telefono !== '' && strlen($telefono) > 10) {
    $_SESSION['error_registro'] = 'El teléfono debe tener máximo 10 dígitos.';
    header('Location: ../signup.php');
    exit;
}

if (!$correo || mb_strlen($correo) > 120) {
    $_SESSION['error_registro'] = 'Ingresa un correo electrónico válido.';
    header('Location: ../signup.php');
    exit;
}

if (strlen($contrasena) < 8) {
    $_SESSION['error_registro'] = 'La contraseña debe tener al menos 8 caracteres.';
    header('Location: ../signup.php');
    exit;
}

$conexion = obtenerConexion();

try {
    // Verificar si el documento o el correo ya están registrados en `usuario`
    $consultaExiste = $conexion->prepare(
        'SELECT documento FROM `usuario` WHERE documento = :documento OR correo = :correo LIMIT 1'
    );
    $consultaExiste->execute([
        ':documento' => $documento,
        ':correo'    => $correo,
    ]);

    if ($consultaExiste->fetch()) {
        $_SESSION['error_registro'] = 'El documento o correo ya se encuentra registrado.';
        header('Location: ../signup.php');
        exit;
    }

    $contrasenaCifrada = password_hash($contrasena, PASSWORD_DEFAULT);

    $insertarUsuario = $conexion->prepare(
        'INSERT INTO `usuario` (documento, nombre, apellido, telefono, correo, contrasena, rol)
         VALUES (:documento, :nombre, :apellido, :telefono, :correo, :contrasena, :rol)'
    );
    $insertarUsuario->execute([
        ':documento'  => $documento,
        ':nombre'     => $nombre,
        ':apellido'   => $apellido,
        ':telefono'   => $telefono !== '' ? $telefono : null,
        ':correo'     => $correo,
        ':contrasena' => $contrasenaCifrada,
        ':rol'        => 'cliente',
    ]);

    $_SESSION['exito_login'] = 'Cuenta creada con éxito. Inicia sesión con tus datos.';
    header('Location: ../login.php');
    exit;
} catch (Throwable $error) {
    error_log('Error al registrar usuario: ' . $error->getMessage());
    $_SESSION['error_registro'] = 'Ocurrió un error al crear tu cuenta. Intenta más tarde.';
    header('Location: ../signup.php');
    exit;
}
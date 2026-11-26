<?php
// acciones/iniciar_sesion.php

require_once __DIR__ . '/../configuracion/conexion.php';
require_once __DIR__ . '/../seguridad/sesion.php';

iniciarSesionSegura();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

$origen         = ($_POST['origen'] ?? '') === 'admin' ? 'admin' : 'cliente';
$rutaRetorno    = $origen === 'admin' ? '../admin.php' : '../login.php';
$identificador  = trim($_POST['identificador'] ?? '');
$contrasena     = $_POST['contrasena'] ?? '';

if (!verificarTokenCsrf($_POST['token_csrf'] ?? null)) {
    $_SESSION['error_login'] = 'La sesión del formulario expiró. Intenta nuevamente.';
    header('Location: ' . $rutaRetorno);
    exit;
}

if ($identificador === '' || $contrasena === '') {
    $_SESSION['error_login'] = 'Completa todos los campos para continuar.';
    header('Location: ' . $rutaRetorno);
    exit;
}

$conexion = obtenerConexion();

try {
    $documentoNumero = ctype_digit($identificador) ? (int) $identificador : 0;

    $consulta = $conexion->prepare(
        'SELECT documento, nombre, apellido, correo, contrasena, rol
         FROM `usuario`
         WHERE correo = :correo OR documento = :documento
         LIMIT 1'
    );
    $consulta->execute([
        ':correo'    => $identificador,
        ':documento' => $documentoNumero,
    ]);

    $usuario = $consulta->fetch();

    if (!$usuario || !password_verify($contrasena, $usuario['contrasena'])) {
        $_SESSION['error_login'] = 'Correo, documento o contraseña incorrectos.';
        header('Location: ' . $rutaRetorno);
        exit;
    }

    // Mapea el rol de la base de datos ('admin' / 'cliente') al tipo de sesión del sistema
    $tipoCuenta = ($usuario['rol'] === 'admin') ? 'administrador' : 'huesped';

    // Evita que un cliente entre por admin.php o un administrador por login.php
    if (($origen === 'admin' && $tipoCuenta !== 'administrador') ||
        ($origen === 'cliente' && $tipoCuenta !== 'huesped')) {
        $_SESSION['error_login'] = 'Correo, documento o contraseña incorrectos.';
        header('Location: ' . $rutaRetorno);
        exit;
    }

    // Regenera el ID de sesión tras autenticarse (protección contra fijación de sesión)
    session_regenerate_id(true);

    $_SESSION['documento']       = (int) $usuario['documento'];
    $_SESSION['nombre_completo'] = $usuario['nombre'] . ' ' . $usuario['apellido'];
    $_SESSION['correo']          = $usuario['correo'];
    $_SESSION['tipo_cuenta']     = $tipoCuenta;

    if ($tipoCuenta === 'administrador') {
        header('Location: ../dashboard.php');
    } else {
        header('Location: ../catalogo.php');
    }
    exit;
} catch (Throwable $error) {
    error_log('Error en inicio de sesión: ' . $error->getMessage());
    $_SESSION['error_login'] = 'Error interno al verificar tus datos.';
    header('Location: ' . $rutaRetorno);
    exit;
}
<?php
require_once __DIR__ . '/seguridad/sesion.php';
iniciarSesionSegura();

if (($_SESSION['tipo_cuenta'] ?? '') === 'huesped') {
    header('Location: catalogo.php');
    exit;
}

$tokenCsrf    = generarTokenCsrf();
$mensajeError = $_SESSION['error_login'] ?? '';
$mensajeExito = $_SESSION['exito_login'] ?? '';
unset($_SESSION['error_login'], $_SESSION['exito_login']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CabinNest - Iniciar Sesión</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lora:wght@600;700&family=Roboto+Slab:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/login.css">
    <script src="https://kit.fontawesome.com/3d84e79734.js" crossorigin="anonymous"></script>
    <link rel="icon" href="img/icono.png" type="image/png">
</head>
<body>

    <div id="cabeza">
        <div id="menu">
            <a href="index.php" class="logo-link">
                <img src="img/icono-largo.png" id="icono-largo">
            </a>

            <div id="division">
                <a href="index.php" class="division-btn">
                    <i class="fa-solid fa-house"></i>
                    <span>Inicio</span>
                </a>
                <a href="catalogo.php" class="division-btn">
                    <i class="fa-solid fa-campground"></i>
                    <span>Cabañas</span>
                </a>
                <a href="index.php#nosotros" class="division-btn">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span>Nosotros</span>
                </a>
                <a href="#contacto" class="division-btn">
                    <i class="fa-solid fa-envelope"></i>
                    <span>Contacto</span>
                </a>
            </div>

            <div class="espaciador-menu"></div>
        </div>
    </div>

    <div id="cuerpo">
        <div class="login-caja">

            <div class="caja1">
                <div class="caja1-contenido">
                    <h1 id="titulo-bienvenida">Bienvenido de Nuevo</h1>
                    <p class="subtitulo-bienvenida">Inicia sesión y continúa tu experiencia en CabinNest</p>

                    <img class="cabaña" src="img/cabaña1.jpg">

                    <div class="cosas">
                        <div class="apunte">
                            <i class="fa-solid fa-circle-check"></i>
                            <span>Accede a tus Reservas</span>
                        </div>
                        <div class="apunte">
                            <i class="fa-solid fa-circle-check"></i>
                            <span>Gestiona tus estancias</span>
                        </div>
                    </div>
                </div>

                <img class="fondo" src="img/fondo-caja1.jpg">
            </div>

            <div class="caja2">
                <div class="titulo">
                    <i class="fa-solid fa-user"></i>
                    <h2 id="titulo-login">Iniciar Sesión</h2>
                </div>

                <?php if ($mensajeExito !== ''): ?>
                    <p style="width: 100%; background: #e9f0e4; color: #304026; border: 1px solid rgba(48, 64, 38, 0.3); border-radius: 10px; padding: 9px 12px; font-family: 'Inter', sans-serif; font-size: 13px; font-weight: 600; text-align: center;">
                        <?= limpiarTexto($mensajeExito) ?>
                    </p>
                <?php endif; ?>

                <?php if ($mensajeError !== ''): ?>
                    <p style="width: 100%; background: #fbeae6; color: #c84b31; border: 1px solid rgba(200, 75, 49, 0.35); border-radius: 10px; padding: 9px 12px; font-family: 'Inter', sans-serif; font-size: 13px; font-weight: 600; text-align: center;">
                        <?= limpiarTexto($mensajeError) ?>
                    </p>
                <?php endif; ?>

                <form id="formulario" action="acciones/iniciar_sesion.php" method="POST">
                    <input type="hidden" name="token_csrf" value="<?= limpiarTexto($tokenCsrf) ?>">
                    <input type="hidden" name="origen" value="cliente">

                    <input type="text" id="correo-login" name="identificador" placeholder="Correo Electrónico o Documento" required>
                    <input type="password" id="password-login" name="contrasena" placeholder="Contraseña" required>

                    <button type="submit" id="btn-iniciar">
                        <i class="fa-solid fa-right-to-bracket"></i>
                        <span>Iniciar Sesión</span>
                    </button>
                </form>

                <p class="registrarse">
                    <span>¿No tienes cuenta?</span>
                    <a href="signup.php">Registrarse</a>
                </p>
            </div>

        </div>
    </div>

    <div id="footer">
        <svg class="ola-animada" viewBox="0 25 150 22" preserveAspectRatio="none">
            <defs>
                <linearGradient id="gradiente-ola-login">
                    <stop stop-color="#225d1d"></stop>
                    <stop offset="1" stop-color="#38ff4f"></stop>
                </linearGradient>
                <path id="w" d="M -160 40 L -101.33333333333334 27 L -42.66666666666667 40 L 15.999999999999993 27 L 74.66666666666666 40 L 133.33333333333331 27 L 192 40 L 192 80 L -160 80 Z"></path>
            </defs>
            <g>
                <use href="#w" x="48" y="0" fill="url(#gradiente-ola-login)" opacity="0.3" class="ola-capa-1"></use>
                <use href="#w" x="68" y="3" fill="url(#gradiente-ola-login)" opacity="0.4" class="ola-capa-2"></use>
            </g>
        </svg>

        <img id="planta1" class="planta-decorativa" src="img/planta.png" alt="">
        <img id="planta2" class="planta-decorativa" src="img/planta.png" alt="">
    </div>

</body>
</html>
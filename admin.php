<?php
require_once __DIR__ . '/seguridad/sesion.php';
iniciarSesionSegura();

if (($_SESSION['tipo_cuenta'] ?? '') === 'administrador') {
    header('Location: dashboard.php');
    exit;
}

$tokenCsrf    = generarTokenCsrf();
$mensajeError = $_SESSION['error_login'] ?? '';
unset($_SESSION['error_login']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CabinNest - Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lora:wght@600;700&family=Roboto+Slab:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
    <script src="https://kit.fontawesome.com/3d84e79734.js" crossorigin="anonymous"></script>
    <link rel="icon" href="img/icono.png" type="image/png">
</head>
<body>
    <div class="cuerpo">
        <div class="caveza">
            <img src="img/icono-largo.png">
            <a href="index.php">Ir a la Pagina Web</a>
        </div>
        <div class="caja-iniciar">
            <div class="izq">
                <div class="cabeza-izq">
                    <i class="fa-solid fa-shield-halved"></i>
                    <h1>Panel Administrativo</h1>
                </div>
                <div class="img-caja">
                    <img src="subidas/cabanas/cabaña1.jpg" class="cabin-img">
                    <svg class="cabin-wave" viewBox="0 0 1440 150" preserveAspectRatio="none">
                        <path d="M45,150 C200,145 310,52 470,52 C680,52 930,118 1140,118 C1270,118 1360,98 1440,82 L1440,150 Z"></path>
                    </svg>
                </div>
                <div class="iconos-caja">
                    <div class="iconos">
                        <i class="fa-solid fa-arrow-up-right-dots"></i>
                        <p>Gestiona <br> Reservas</p>
                    </div>
                    <div class="iconos" style="border-left: 2px solid #344F27; border-right: 2px solid #344F27;">
                        <i class="fa-solid fa-house-circle-check"></i>
                        <p>Administra <br> Cabañas</p>
                    </div>
                    <div class="iconos">
                        <i class="fa-solid fa-chart-pie"></i>
                        <p>Consulta <br> Reportes</p>
                    </div>
                </div>
                <img class="izq-footer" src="img/fondo-caja1.jpg" alt="">
            </div>
            <div class="der">
                <div class="titulo">
                    <i class="fa-solid fa-gear"></i>
                    <h1>Iniciar Sesión - Administrador</h1>
                </div>

                <?php if ($mensajeError !== ''): ?>
                    <p style="width: 100%; background: #fbeae6; color: #c84b31; border: 1px solid rgba(200, 75, 49, 0.35); border-radius: 10px; padding: 9px 12px; font-family: 'Inter', sans-serif; font-size: 13px; font-weight: 600; text-align: center;">
                        <?= limpiarTexto($mensajeError) ?>
                    </p>
                <?php endif; ?>

                <form class="formulario" action="acciones/iniciar_sesion.php" method="POST">
                    <input type="hidden" name="token_csrf" value="<?= limpiarTexto($tokenCsrf) ?>">
                    <input type="hidden" name="origen" value="admin">

                    <input type="text" name="identificador" placeholder="Correo Electrónico o Documento" required>
                    <input type="password" name="contrasena" placeholder="Contraseña" required>
                    <button type="submit">
                        <i class="fa-solid fa-right-to-bracket"></i>
                        <p>Iniciar Sesión</p>
                    </button>
                </form>
                <div class="der-footer">
                    <i class="fa-solid fa-user-shield"></i>
                    <p>Solo personal autorizado</p>
                </div>
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
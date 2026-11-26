<?php
require_once __DIR__ . '/seguridad/sesion.php';
iniciarSesionSegura();

if (($_SESSION['tipo_cuenta'] ?? '') === 'huesped') {
    header('Location: catalogo.php');
    exit;
}

$tokenCsrf    = generarTokenCsrf();
$mensajeError = $_SESSION['error_registro'] ?? '';
unset($_SESSION['error_registro']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CabinNest - Crear Cuenta</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lora:wght@600;700&family=Roboto+Slab:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/signup.css">
    <link rel="icon" href="img/icono.png" type="image/png">

    <script src="https://kit.fontawesome.com/3d84e79734.js" crossorigin="anonymous"></script>
</head>
<body>

    <header id="cabeza">
        <div id="menu">
            <a href="index.php" class="logo-link">
                <img src="img/icono-largo.png" id="icono-largo" alt="Logo de CabinNest">
            </a>

            <nav id="division" aria-label="Navegación principal">
                <a href="index.php" class="division-btn">
                    <i class="fa-solid fa-house" aria-hidden="true"></i>
                    <span>Inicio</span>
                </a>
                <a href="catalogo.php" class="division-btn">
                    <i class="fa-solid fa-campground" aria-hidden="true"></i>
                    <span>Cabañas</span>
                </a>
                <a href="index.php#nosotros" class="division-btn">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <span>Nosotros</span>
                </a>
                <a href="index.php#contacto" class="division-btn">
                    <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                    <span>Contacto</span>
                </a>
            </nav>

            <a href="login.php" id="btn-iniciar">
                <i class="fa-solid fa-user" aria-hidden="true"></i>
                <span>Iniciar Sesión</span>
            </a>
        </div>
    </header>

    <!-- ================= CONTENIDO PRINCIPAL: REGISTRO ================= -->
    <main id="cuerpo">
        <div class="registrarse-caja">

            <!-- Panel Izquierdo: Beneficios -->
            <section class="caja1" aria-labelledby="titulo-unete">
                <div class="caja1-contenido">
                    <h1 id="titulo-unete">Únete a CabinNest</h1>
                    <p class="subtitulo-unete">Crea tu cuenta y comienza a vivir experiencias inolvidables en nuestras cabañas</p>

                    <img class="cabaña" src="img/cabaña1.jpg" alt="Cabaña de lujo rodeada de naturaleza">

                    <ul class="cosas">
                        <li class="apunte">
                            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                            <span>Reserva fácilmente tus cabañas</span>
                        </li>
                        <li class="apunte">
                            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                            <span>Gestiona tus reservas</span>
                        </li>
                        <li class="apunte">
                            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                            <span>Recibe promociones</span>
                        </li>
                    </ul>
                </div>

                <img class="fondo" src="img/fondo-caja1.jpg" alt="Tu refugio en la naturaleza">
            </section>

            <!-- Panel Derecho: Formulario de Registro -->
            <section class="caja2" aria-labelledby="titulo-registro">
                <div class="titulo">
                    <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                    <h2 id="titulo-registro">Crear una Cuenta</h2>
                </div>

                <?php if ($mensajeError !== ''): ?>
                    <p id="alerta-registro" role="alert" style="width: 100%; background: #fbeae6; color: #c84b31; border: 1px solid rgba(200, 75, 49, 0.35); border-radius: 10px; padding: 8px 12px; font-family: 'Inter', sans-serif; font-size: 12.5px; font-weight: 600; text-align: center;">
                        <?= limpiarTexto($mensajeError) ?>
                    </p>
                <?php endif; ?>

                <form id="formulario" action="acciones/registrar_huesped.php" method="POST">
                    <input type="hidden" name="token_csrf" value="<?= limpiarTexto($tokenCsrf) ?>">

                    <div class="fila1">
                        <input type="text" id="nombre-registro" name="nombre" maxlength="80" placeholder="Nombre" aria-label="Nombre" autocomplete="given-name" required>
                        <input type="text" id="apellido-registro" name="apellido" maxlength="80" placeholder="Apellido" aria-label="Apellido" autocomplete="family-name" required>
                    </div>

                    <input type="number" id="cedula" name="documento" min="1" step="1" inputmode="numeric" placeholder="Número de Documento (C.C.)" aria-label="Número de Documento" required>
                    <input type="email" id="correo-registro" name="correo" maxlength="120" placeholder="Correo Electrónico" aria-label="Correo Electrónico" autocomplete="email" required>
                    <input type="tel" id="telefono-registro" name="telefono" maxlength="10" inputmode="numeric" pattern="[0-9]{10}" placeholder="Teléfono (10 dígitos)" aria-label="Teléfono" autocomplete="tel" required>
                    <input type="password" id="password-registro" name="contrasena" minlength="8" placeholder="Contraseña (mín. 8 caracteres)" aria-label="Contraseña" autocomplete="new-password" required>
                    <input type="password" id="confirmar-password" name="confirmar_contrasena" minlength="8" placeholder="Confirmar Contraseña" aria-label="Confirmar Contraseña" autocomplete="new-password" required>

                    <button type="submit" id="btn-registrarse">
                        <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                        <span>Registrarse</span>
                    </button>
                </form>

                <p class="iniciar-sesion">
                    <span>¿Ya tienes una cuenta?</span>
                    <a href="login.php">Iniciar Sesión</a>
                </p>
            </section>

        </div>
    </main>

    <!-- ================= PIE DECORATIVO (OLAS Y PLANTAS) ================= -->
    <footer id="footer" aria-hidden="true">
        <svg class="ola-animada" viewBox="0 25 150 22" preserveAspectRatio="none">
            <defs>
                <linearGradient id="gradiente-ola-signup">
                    <stop stop-color="#225d1d"></stop>
                    <stop offset="1" stop-color="#38ff4f"></stop>
                </linearGradient>
                <path id="w" d="M -160 40 L -101.33333333333334 27 L -42.66666666666667 40 L 15.999999999999993 27 L 74.66666666666666 40 L 133.33333333333331 27 L 192 40 L 192 80 L -160 80 Z"></path>
            </defs>
            <g>
                <use href="#w" x="48" y="0" fill="url(#gradiente-ola-signup)" opacity="0.3" class="ola-capa-1"></use>
                <use href="#w" x="68" y="3" fill="url(#gradiente-ola-signup)" opacity="0.4" class="ola-capa-2"></use>
            </g>
        </svg>

        <img id="planta1" class="planta-decorativa" src="img/planta.png" alt="">
        <img id="planta2" class="planta-decorativa" src="img/planta.png" alt="">
    </footer>

    <script>
        const formularioRegistro = document.getElementById('formulario');
        const campoContrasena = document.getElementById('password-registro');
        const campoConfirmar = document.getElementById('confirmar-password');

        formularioRegistro.addEventListener('submit', (evento) => {
            if (campoContrasena.value !== campoConfirmar.value) {
                evento.preventDefault();
                alert('Las contraseñas no coinciden. Verifica ambos campos.');
                campoConfirmar.focus();
            }
        });
    </script>

</body>
</html>
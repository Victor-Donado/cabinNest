<?php
require_once __DIR__ . '/seguridad/proteger_admin.php';
require_once __DIR__ . '/configuracion/conexion.php';

$conexion = obtenerConexion();

// 1. Consultar usuarios inscritos con rol 'cliente' desde `usuario`
$consultaUsuarios = $conexion->query(
    'SELECT documento, nombre, apellido, correo, telefono
     FROM `usuario`
     WHERE rol = \'cliente\'
     ORDER BY nombre ASC, apellido ASC'
);
$listaUsuarios = $consultaUsuarios->fetchAll();

// 2. Preparar consulta de reservas por cada cliente
$consultaReservasUsuario = $conexion->prepare(
    'SELECT r.id_reserva,
            r.fecha_checkin,
            r.fecha_checkout,
            r.estado,
            c.nombre AS nombre_cabana,
            (SELECT COUNT(*) FROM `hospedaje` h WHERE h.id_reserva = r.id_reserva) AS cantidad_huespedes
     FROM `reserva` r
     INNER JOIN `tarifa` t ON r.id_tarifa = t.id_tarifa
     INNER JOIN `cabaña` c ON t.`id_cabaña` = c.`id_cabaña`
     WHERE r.id_cliente = :documento
     ORDER BY r.fecha_checkin DESC'
);

function obtenerIniciales(string $nombre, string $apellido): string
{
    $ini1 = mb_strtoupper(mb_substr(trim($nombre), 0, 1, 'UTF-8'), 'UTF-8');
    $ini2 = mb_strtoupper(mb_substr(trim($apellido), 0, 1, 'UTF-8'), 'UTF-8');
    return ($ini1 . $ini2) ?: 'US';
}

function claseBadgeModalUsuario(string $estado): string
{
    if ($estado === 'Cancelada') {
        return 'cancelada';
    }
    if ($estado === 'Completada') {
        return 'completada';
    }
    return 'activa';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CabinNest - Usuarios</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lora:wght@600;700&family=Roboto+Slab:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/usuarios.css">
    <link rel="icon" href="img/icono.png" type="image/png">
    <script src="https://kit.fontawesome.com/3d84e79734.js" crossorigin="anonymous"></script>
</head>
<body>
    <input type="checkbox" id="menu-toggle" class="menu-checkbox" aria-hidden="true">

    <aside class="menu" aria-label="Menú de administración">
        <div class="menu-movil-top">
            <span>Menú</span>
            <label for="menu-toggle" class="btn-cerrar-menu" aria-label="Cerrar menú">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </label>
        </div>

        <div class="img-cabeza">
            <a href="index.php">
                <img class="icono-menu" src="img/icono-largo.png" alt="Logo de CabinNest">
            </a>
        </div>

        <nav class="botones" aria-label="Secciones del panel">
            <a href="dashboard.php">
                <i class="fa-solid fa-chess-board" aria-hidden="true"></i>
                <span>DashBoard</span>
            </a>
            <a href="reservas.php">
                <i class="fa-regular fa-calendar-days" aria-hidden="true"></i>
                <span>Reservas</span>
            </a>
            <a href="pagos.php">
                <i class="fa-solid fa-credit-card" aria-hidden="true"></i>
                <span>Pagos</span>
            </a>
            <a href="cabanas.php">
                <i class="fa-solid fa-campground" aria-hidden="true"></i>
                <span>Cabañas</span>
            </a>
            <a class="activo" href="usuarios.php" aria-current="page">
                <i class="fa-solid fa-users" aria-hidden="true"></i>
                <span>Usuarios</span>
            </a>
        </nav>

        <img class="img-footer" src="img/menu-admin.jpg" alt="Ilustración natural CabinNest">
    </aside>

    <label for="menu-toggle" class="menu-overlay" aria-label="Cerrar menú"></label>
    <main class="dashboard">

        <header class="cabeza-dashboard">
            <label for="menu-toggle" class="btn-hamburguesa" role="button" tabindex="0" aria-label="Abrir menú">
                <span></span>
                <span></span>
                <span></span>
            </label>

            <div class="cabeza-txt">
                <h1>Gestión de Usuarios</h1>
                <p>Consulta los usuarios inscritos y revisa sus reservas</p>
            </div>

            <a href="acciones/cerrar_sesion.php" id="cerrar-sesion">
                <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                <span>Cerrar Sesión</span>
            </a>
        </header>

        <div class="dashboard-contenido">
            <section class="usuarios-caja" aria-labelledby="titulo-lista-usuarios">

                <div class="usuarios-barra">
                    <div class="usuarios-titulo">
                        <i class="fa-solid fa-users" aria-hidden="true"></i>
                        <h2 id="titulo-lista-usuarios">Usuarios Inscritos</h2>
                        <span class="contador-usuarios">
                            <?= count($listaUsuarios) ?> <?= count($listaUsuarios) === 1 ? 'registrado' : 'registrados' ?>
                        </span>
                    </div>
                </div>

                <div class="tabla-encabezado" aria-hidden="true">
                    <span>Nombre</span>
                    <span>Apellido</span>
                    <span>Correo Electrónico</span>
                    <span>Teléfono</span>
                    <span class="col-accion">Acciones</span>
                </div>

                <div class="lista-usuarios">

                    <?php if (empty($listaUsuarios)): ?>
                        <p class="sin-reservas" style="padding: 24px; text-align: center;">
                            Aún no hay clientes registrados en la base de datos.
                        </p>
                    <?php endif; ?>

                    <?php foreach ($listaUsuarios as $indice => $usuario):
                        $nombreCompleto = trim($usuario['nombre'] . ' ' . $usuario['apellido']);
                        $iniciales      = obtenerIniciales($usuario['nombre'], $usuario['apellido']);
                        $claseAvatar    = ($indice % 2 === 1) ? 'avatar-iniciales naranja' : 'avatar-iniciales';

                        $consultaReservasUsuario->execute([':documento' => (int) $usuario['documento']]);
                        $reservasUsuario = $consultaReservasUsuario->fetchAll();
                    ?>
                        <article class="usuario-item" data-nombre-completo="<?= limpiarTexto($nombreCompleto) ?>">
                            <div class="dato-usuario dato-nombre">
                                <span class="<?= $claseAvatar ?>" aria-hidden="true"><?= limpiarTexto($iniciales) ?></span>
                                <strong><?= limpiarTexto($usuario['nombre']) ?></strong>
                            </div>
                            <div class="dato-usuario"><?= limpiarTexto($usuario['apellido']) ?></div>
                            <div class="dato-usuario dato-correo"><?= limpiarTexto($usuario['correo'] ?? 'Sin correo') ?></div>
                            <div class="dato-usuario dato-telefono"><?= limpiarTexto($usuario['telefono'] ?? 'Sin teléfono') ?></div>
                            <div class="dato-usuario col-accion">
                                <button type="button" class="btn-reservas">
                                    <i class="fa-regular fa-calendar-check" aria-hidden="true"></i>
                                    <span>Reservas (<?= count($reservasUsuario) ?>)</span>
                                </button>
                            </div>
                            <div class="reservas-usuario-data" hidden>
                                <?php if (empty($reservasUsuario)): ?>
                                    <p class="sin-reservas">Este usuario aún no tiene reservas registradas.</p>
                                <?php else: ?>
                                    <?php foreach ($reservasUsuario as $res):
                                        $cantHuespedes = max(1, (int) $res['cantidad_huespedes']);
                                        $claseEstado   = claseBadgeModalUsuario($res['estado']);
                                    ?>
                                        <div class="reserva-modal-item">
                                            <div class="reserva-modal-top">
                                                <strong><?= limpiarTexto($res['nombre_cabana']) ?></strong>
                                                <span class="badge-estado <?= $claseEstado ?>"><?= limpiarTexto($res['estado']) ?></span>
                                            </div>
                                            <p class="reserva-modal-detalle">
                                                <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                                                <?= date('d/m/Y', strtotime($res['fecha_checkin'])) ?> - <?= date('d/m/Y', strtotime($res['fecha_checkout'])) ?>
                                                • <?= $cantHuespedes ?> <?= $cantHuespedes === 1 ? 'huésped' : 'huéspedes' ?>
                                            </p>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>

                </div>
            </section>
        </div>
    </main>

    <div id="modal-reservas" class="modal-fondo" role="dialog" aria-modal="true" aria-labelledby="modal-titulo-usuario">
        <div class="modal-ventana">
            <header class="modal-encabezado">
                <button type="button" id="btn-cerrar-modal" class="btn-cerrar-modal" aria-label="Cerrar ventana">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
                <div class="modal-titulo-grupo">
                    <h3 id="modal-titulo-usuario">Reservas del Usuario</h3>
                    <span id="modal-subtitulo-usuario">Historial registrado en CabinNest</span>
                </div>
            </header>

            <div id="modal-lista-reservas" class="modal-cuerpo"></div>
        </div>
    </div>

    <script>
        const modalFondo       = document.getElementById('modal-reservas');
        const btnCerrarModal   = document.getElementById('btn-cerrar-modal');
        const modalTitulo      = document.getElementById('modal-titulo-usuario');
        const modalLista       = document.getElementById('modal-lista-reservas');
        const botonesReservas  = document.querySelectorAll('.btn-reservas');
        const menuToggle       = document.getElementById('menu-toggle');

        botonesReservas.forEach((boton) => {
            boton.addEventListener('click', () => {
                const tarjetaUsuario  = boton.closest('.usuario-item');
                const nombreUsuario   = tarjetaUsuario.dataset.nombreCompleto;
                const reservasOcultas = tarjetaUsuario.querySelector('.reservas-usuario-data');

                modalTitulo.textContent = `Reservas de ${nombreUsuario}`;
                modalLista.innerHTML = reservasOcultas
                    ? reservasOcultas.innerHTML
                    : '<p class="sin-reservas">Este usuario aún no tiene reservas registradas.</p>';

                modalFondo.classList.add('activo');
            });
        });

        const cerrarModal = () => modalFondo.classList.remove('activo');

        btnCerrarModal.addEventListener('click', cerrarModal);

        modalFondo.addEventListener('click', (evento) => {
            if (evento.target === modalFondo) cerrarModal();
        });

        document.addEventListener('keydown', (evento) => {
            if (evento.key === 'Escape') {
                if (modalFondo.classList.contains('activo')) cerrarModal();
                if (menuToggle && menuToggle.checked) menuToggle.checked = false;
            }
        });
    </script>

</body>
</html>
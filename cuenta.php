<?php
require_once __DIR__ . '/seguridad/proteger_cliente.php';
require_once __DIR__ . '/configuracion/conexion.php';

$tokenCsrf        = generarTokenCsrf();
$documentoCliente = (int) $_SESSION['documento'];
$conexion         = obtenerConexion();

// 1. Obtener información personal del usuario autenticado
$consultaUsuario = $conexion->prepare(
    'SELECT documento, nombre, apellido, telefono, correo
     FROM `usuario`
     WHERE documento = :documento
     LIMIT 1'
);
$consultaUsuario->execute([':documento' => $documentoCliente]);
$datosUsuario = $consultaUsuario->fetch();

// 2. Obtener historial de reservas del cliente
$consultaReservas = $conexion->prepare(
    'SELECT r.id_reserva,
            r.fecha_checkin,
            r.fecha_checkout,
            r.estado,
            r.cantidad_noches,
            r.total,
            c.`id_cabaña` AS id_cabana,
            c.nombre AS nombre_cabana,
            f.foto AS foto_principal,
            p.metodo_pago,
            p.monto AS monto_pagado
     FROM `reserva` r
     INNER JOIN `tarifa` t ON r.id_tarifa = t.id_tarifa
     INNER JOIN `cabaña` c ON t.`id_cabaña` = c.`id_cabaña`
     LEFT JOIN `cabaña_foto` f ON c.`id_cabaña` = f.`id_cabaña` AND f.id_foto = 1
     LEFT JOIN `pago` p ON r.id_reserva = p.id_reserva
     WHERE r.id_cliente = :id_cliente
     ORDER BY r.fecha_reserva DESC'
);
$consultaReservas->execute([':id_cliente' => $documentoCliente]);
$listaReservas = $consultaReservas->fetchAll();

// 3. Verificar reseñas ya registradas por el cliente para marcar reservas reseñadas
$columnasResena = $conexion->query('SHOW COLUMNS FROM `reseña`')->fetchAll(PDO::FETCH_COLUMN);
$tieneIdReserva = in_array('id_reserva', $columnasResena, true);
$colUsuarioRes  = null;
foreach (['id_usuario', 'id_cliente', 'documento'] as $posibleCol) {
    if (in_array($posibleCol, $columnasResena, true)) {
        $colUsuarioRes = $posibleCol;
        break;
    }
}

$resenasPorReserva = [];
$resenasPorCabana  = [];

if ($tieneIdReserva && $colUsuarioRes !== null) {
    $stmtRes = $conexion->prepare(
        "SELECT id_reserva, calificacion FROM `reseña` WHERE `{$colUsuarioRes}` = :doc AND id_reserva IS NOT NULL"
    );
    $stmtRes->execute([':doc' => $documentoCliente]);
    foreach ($stmtRes->fetchAll() as $filaRes) {
        $resenasPorReserva[(int) $filaRes['id_reserva']] = (int) $filaRes['calificacion'];
    }
} elseif ($colUsuarioRes !== null) {
    $stmtRes = $conexion->prepare(
        "SELECT `id_cabaña` AS id_cabana, calificacion FROM `reseña` WHERE `{$colUsuarioRes}` = :doc"
    );
    $stmtRes->execute([':doc' => $documentoCliente]);
    foreach ($stmtRes->fetchAll() as $filaRes) {
        $resenasPorCabana[(int) $filaRes['id_cabana']] = (int) $filaRes['calificacion'];
    }
}

$mensajeExito = $_SESSION['exito_cuenta'] ?? '';
$mensajeError = $_SESSION['error_accion'] ?? '';
unset($_SESSION['exito_cuenta'], $_SESSION['error_accion']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CabinNest - Mi Cuenta</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lora:wght@600;700&family=Roboto+Slab:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/cuenta.css">
    <script src="https://kit.fontawesome.com/3d84e79734.js" crossorigin="anonymous"></script>
    <link rel="icon" href="img/icono.png" type="image/png">
    <style>
        .btn-resenar {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 15px;
            border: none;
            border-radius: 999px;
            background: #DF6D10;
            color: #ffffff;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease, transform 0.15s ease;
        }

        .btn-resenar:hover {
            background: #c55d0a;
            transform: translateY(-1px);
        }

        .badge-resenada {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 999px;
            background: #e9f0e4;
            color: #304026;
            font-family: 'Inter', sans-serif;
            font-size: 12.5px;
            font-weight: 600;
        }

        .badge-resenada i {
            color: #DF6D10;
        }

        /* Modal de Reseña */
        .modal-resena-fondo {
            position: fixed;
            inset: 0;
            background: rgba(24, 30, 20, 0.58);
            backdrop-filter: blur(2px);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            z-index: 1200;
        }

        .modal-resena-fondo.activo {
            display: flex;
        }

        .modal-resena-ventana {
            width: 100%;
            max-width: 480px;
            background: #ffffff;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.28);
            animation: aparecerModalResena 0.18s ease-out;
        }

        @keyframes aparecerModalResena {
            from { opacity: 0; transform: translateY(8px) scale(0.98); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .modal-resena-encabezado {
            background: #304026;
            color: #ffffff;
            padding: 18px 22px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .btn-cerrar-resena {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: none;
            background: rgba(255, 255, 255, 0.16);
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            cursor: pointer;
            flex-shrink: 0;
        }

        .btn-cerrar-resena:hover {
            background: rgba(255, 255, 255, 0.28);
        }

        .modal-resena-titulo h3 {
            margin: 0;
            font-family: 'Lora', serif;
            font-size: 19px;
            font-weight: 700;
            color: #ffffff;
        }

        .modal-resena-titulo span {
            display: block;
            margin-top: 3px;
            font-family: 'Inter', sans-serif;
            font-size: 12.5px;
            color: #dce3d6;
        }

        .modal-resena-cuerpo {
            padding: 20px 22px 22px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .grupo-resena {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .grupo-resena label {
            font-family: 'Inter', sans-serif;
            font-size: 13.5px;
            font-weight: 600;
            color: #304026;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .valor-puntuacion-tag {
            background: #fdf0e6;
            color: #DF6D10;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .selector-puntuacion {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
        }

        .btn-nota {
            padding: 10px 6px;
            border-radius: 12px;
            border: 1.5px solid #e2dbd0;
            background: #fbf8f3;
            color: #3d3832;
            font-family: 'Inter', sans-serif;
            font-size: 13.5px;
            font-weight: 700;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .btn-nota i {
            font-size: 13px;
            color: #c7bfb3;
        }

        .btn-nota.activa {
            border-color: #DF6D10;
            background: #fdf0e6;
            color: #DF6D10;
        }

        .btn-nota.activa i {
            color: #DF6D10;
        }

        .textarea-resena {
            width: 100%;
            min-height: 105px;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1.5px solid #dfd7cb;
            background: #fbf8f3;
            font-family: 'Inter', sans-serif;
            font-size: 13.5px;
            color: #2b2723;
            resize: vertical;
            box-sizing: border-box;
        }

        .textarea-resena:focus {
            outline: none;
            border-color: #304026;
            background: #ffffff;
        }

        .acciones-modal-resena {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 4px;
        }

        .btn-cancelar-modal {
            padding: 9px 16px;
            border-radius: 999px;
            border: 1px solid #d5cfc7;
            background: #fbf8f3;
            color: #4f4a44;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-enviar-resena {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 18px;
            border: none;
            border-radius: 999px;
            background: #304026;
            color: #ffffff;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-enviar-resena:hover {
            background: #24311c;
        }
    </style>
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

            <a href="acciones/cerrar_sesion.php" id="btn-cerrar">
                <i class="fa-solid fa-right-to-bracket"></i>
                <span>Cerrar Sesión</span>
            </a>
        </div>
    </div>

    <div id="cuerpo">
        <div class="cuenta">

            <input type="radio" name="vista-cuenta" id="radio-reservas" class="radio-oculto" checked>
            <input type="radio" name="vista-cuenta" id="radio-datos" class="radio-oculto">

            <div class="cuenta-menu">
                <div class="cuenta-cabeza">
                    <div class="cuenta-banner"></div>
                    <div class="avatar-wrapper">
                        <i class="fa-solid fa-circle-user"></i>
                    </div>
                    <p class="nombre"><?= limpiarTexto(($datosUsuario['nombre'] ?? '') . ' ' . ($datosUsuario['apellido'] ?? '')) ?></p>
                    <p class="correo"><?= limpiarTexto($datosUsuario['correo'] ?? '') ?></p>
                </div>

                <div class="cuenta-tabs">
                    <label for="radio-reservas" id="btn-reservas">
                        <i class="fa-solid fa-house"></i>
                        <span>Mis reservas</span>
                    </label>
                    <label for="radio-datos" id="btn-datos">
                        <i class="fa-solid fa-database"></i>
                        <span>Mis datos</span>
                    </label>
                </div>
            </div>

            <div class="reservas">

                <?php if ($mensajeExito !== ''): ?>
                    <p style="background: #e9f0e4; color: #304026; border: 1px solid rgba(48, 64, 38, 0.3); border-radius: 12px; padding: 10px 14px; font-family: 'Inter', sans-serif; font-size: 13.5px; font-weight: 600;">
                        <i class="fa-solid fa-circle-check"></i> <?= limpiarTexto($mensajeExito) ?>
                    </p>
                <?php endif; ?>

                <?php if ($mensajeError !== ''): ?>
                    <p style="background: #fbeae6; color: #c84b31; border: 1px solid rgba(200, 75, 49, 0.35); border-radius: 12px; padding: 10px 14px; font-family: 'Inter', sans-serif; font-size: 13.5px; font-weight: 600;">
                        <i class="fa-solid fa-triangle-exclamation"></i> <?= limpiarTexto($mensajeError) ?>
                    </p>
                <?php endif; ?>

                <?php if (empty($listaReservas)): ?>
                    <div class="reservas-caja">
                        <div class="reservas-info">
                            <h2>Aún no tienes reservas registradas</h2>
                            <p style="font-family: 'Inter', sans-serif; font-size: 13.5px; color: #6b655e;">
                                Explora nuestras cabañas disponibles y programa tu próxima estadía.
                            </p>
                            <div class="botones">
                                <a href="catalogo.php" class="btn-comprobante">
                                    <i class="fa-solid fa-campground"></i> Ver Cabañas
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php foreach ($listaReservas as $reserva):
                    $idReserva    = (int) $reserva['id_reserva'];
                    $idCabana     = (int) $reserva['id_cabana'];
                    $estadoRes    = $reserva['estado'];
                    $esCancelada  = ($estadoRes === 'Cancelada');
                    $esFinalizada = in_array($estadoRes, ['Completada', 'Finalizada'], true);
                    $fechaInFmt   = date('d/m/Y', strtotime($reserva['fecha_checkin']));
                    $fechaOutFmt  = date('d/m/Y', strtotime($reserva['fecha_checkout']));
                    $rutaImagen   = !empty($reserva['foto_principal'])
                        ? 'subidas/cabanas/' . rawurlencode($reserva['foto_principal'])
                        : 'img/cabaña1.jpg';

                    $notaRegistrada = $tieneIdReserva
                        ? ($resenasPorReserva[$idReserva] ?? null)
                        : ($resenasPorCabana[$idCabana] ?? null);
                ?>
                    <div class="reservas-caja">
                        <div class="reserva-imagen">
                            <img src="<?= limpiarTexto($rutaImagen) ?>">
                        </div>
                        <div class="reservas-info">
                            <div class="reserva-encabezado">
                                <h2><?= limpiarTexto($reserva['nombre_cabana']) ?> <small style="font-family: 'Inter', sans-serif; font-size: 12px; color: #6b655e;">#<?= $idReserva ?></small></h2>
                                <span class="estado" style="<?= $esCancelada ? 'background: #fbeae6; color: #c84b31;' : '' ?>">
                                    <i class="fa-solid <?= $esCancelada ? 'fa-circle-xmark' : 'fa-circle-check' ?>"></i>
                                    <?= limpiarTexto($estadoRes) ?>
                                </span>
                            </div>
                            <div class="fechas">
                                <i class="fa-regular fa-calendar-days"></i>
                                <p><?= $fechaInFmt ?> - <?= $fechaOutFmt ?> • Total: $<?= number_format((float) $reserva['total'], 2) ?></p>
                            </div>
                            <div class="botones">
                                <button type="button" class="btn-comprobante" onclick="alert('Comprobante de Reserva #<?= $idReserva ?>\nCabaña: <?= limpiarTexto($reserva['nombre_cabana']) ?>\nFechas: <?= $fechaInFmt ?> al <?= $fechaOutFmt ?>\nMétodo de pago: <?= limpiarTexto($reserva['metodo_pago'] ?? 'Tarjeta Crédito') ?>\nMonto pagado: $<?= number_format((float) ($reserva['monto_pagado'] ?? $reserva['total']), 2) ?>')">
                                    <i class="fa-solid fa-file-invoice"></i> Comprobante
                                </button>

                                <?php if ($esFinalizada): ?>
                                    <?php if ($notaRegistrada !== null): ?>
                                        <span class="badge-resenada">
                                            <i class="fa-solid fa-star"></i> Reseñada (<?= (int) $notaRegistrada ?>/5)
                                        </span>
                                    <?php else: ?>
                                        <button type="button"
                                                class="btn-resenar"
                                                data-id-reserva="<?= $idReserva ?>"
                                                data-id-cabana="<?= $idCabana ?>"
                                                data-nombre-cabana="<?= limpiarTexto($reserva['nombre_cabana']) ?>">
                                            <i class="fa-solid fa-star"></i> Reseñar
                                        </button>
                                    <?php endif; ?>
                                <?php elseif (!$esCancelada): ?>
                                    <form action="acciones/cancelar_reserva.php" method="POST" style="display: inline;" onsubmit="return confirm('¿Seguro que deseas cancelar la reserva #<?= $idReserva ?>?');">
                                        <input type="hidden" name="token_csrf" value="<?= limpiarTexto($tokenCsrf) ?>">
                                        <input type="hidden" name="id_reserva" value="<?= $idReserva ?>">
                                        <button type="submit" class="btn-cancelar">
                                            <i class="fa-solid fa-xmark"></i> Cancelar
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="calificacion"></div>
                    </div>
                <?php endforeach; ?>

            </div>

            <div class="datos-caja">
                <div class="datos-encabezado">
                    <div class="datos-icono">
                        <i class="fa-solid fa-id-card"></i>
                    </div>
                    <div>
                        <h2 id="titulo-mis-datos">Información Personal</h2>
                        <p>Consulta tus datos registrados y actualiza tu información de contacto</p>
                    </div>
                </div>

                <form id="form-datos" class="datos-formulario" action="acciones/actualizar_datos_huesped.php" method="POST">
                    <input type="hidden" name="token_csrf" value="<?= limpiarTexto($tokenCsrf) ?>">

                    <div class="campo-dato">
                        <label for="dato-nombre">
                            Nombre <span class="etiqueta-bloqueada"><i class="fa-solid fa-lock"></i> Solo lectura</span>
                        </label>
                        <input type="text" id="dato-nombre" value="<?= limpiarTexto($datosUsuario['nombre'] ?? '') ?>" readonly>
                    </div>

                    <div class="campo-dato">
                        <label for="dato-apellido">
                            Apellido <span class="etiqueta-bloqueada"><i class="fa-solid fa-lock"></i> Solo lectura</span>
                        </label>
                        <input type="text" id="dato-apellido" value="<?= limpiarTexto($datosUsuario['apellido'] ?? '') ?>" readonly>
                    </div>

                    <div class="campo-dato">
                        <label for="dato-documento">
                            Documento <span class="etiqueta-bloqueada"><i class="fa-solid fa-lock"></i> Solo lectura</span>
                        </label>
                        <input type="text" id="dato-documento" value="<?= limpiarTexto((string) ($datosUsuario['documento'] ?? '')) ?>" readonly>
                    </div>

                    <div class="campo-dato">
                        <label for="dato-telefono">
                            Teléfono <span class="etiqueta-editable"><i class="fa-solid fa-pen"></i> Editable</span>
                        </label>
                        <input type="tel" id="dato-telefono" name="telefono" maxlength="10" value="<?= limpiarTexto($datosUsuario['telefono'] ?? '') ?>" placeholder="Ingresa tu teléfono" required>
                    </div>

                    <div class="campo-dato campo-completo">
                        <label for="dato-correo">
                            Correo Electrónico <span class="etiqueta-editable"><i class="fa-solid fa-pen"></i> Editable</span>
                        </label>
                        <input type="email" id="dato-correo" name="correo" maxlength="120" value="<?= limpiarTexto($datosUsuario['correo'] ?? '') ?>" placeholder="tucorreo@ejemplo.com" required>
                    </div>

                    <div class="datos-pie">
                        <p class="nota-seguridad">
                            <i class="fa-solid fa-circle-info"></i> Para cambiar tu nombre o documento contacta a soporte.
                        </p>
                        <button type="submit" id="btn-guardar">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <span>Guardar Cambios</span>
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <div id="modal-resena" class="modal-resena-fondo">
        <div class="modal-resena-ventana">
            <div class="modal-resena-encabezado">
                <button type="button" id="btn-cerrar-modal-resena" class="btn-cerrar-resena">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <div class="modal-resena-titulo">
                    <h3 id="titulo-modal-resena">Calificar Estadía</h3>
                    <span id="subtitulo-modal-resena">Comparte tu experiencia sobre la cabaña</span>
                </div>
            </div>

            <form action="acciones/guardar_resena.php" method="POST" class="modal-resena-cuerpo">
                <input type="hidden" name="token_csrf" value="<?= limpiarTexto($tokenCsrf) ?>">
                <input type="hidden" name="id_reserva" id="resena-id-reserva" value="">
                <input type="hidden" name="id_cabana" id="resena-id-cabana" value="">
                <input type="hidden" name="calificacion" id="resena-calificacion" value="5">

                <div class="grupo-resena">
                    <label>
                        <span>Puntuación (0 a 5)</span>
                        <span id="texto-puntuacion" class="valor-puntuacion-tag">5 / 5</span>
                    </label>
                    <div class="selector-puntuacion" id="selector-puntuacion">
                        <?php for ($nota = 0; $nota <= 5; $nota++): ?>
                            <button type="button" class="btn-nota <?= $nota === 5 ? 'activa' : '' ?>" data-nota="<?= $nota ?>">
                                <i class="fa-solid fa-star"></i>
                                <span><?= $nota ?></span>
                            </button>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="grupo-resena">
                    <label for="resena-comentario">Comentario</label>
                    <textarea id="resena-comentario"
                              name="comentario"
                              class="textarea-resena"
                              maxlength="500"
                              placeholder="Cuéntanos cómo fue tu estadía, la limpieza, comodidad y entorno de la cabaña..."
                              required></textarea>
                </div>

                <div class="acciones-modal-resena">
                    <button type="button" id="btn-cancelar-resena" class="btn-cancelar-modal">Cancelar</button>
                    <button type="submit" class="btn-enviar-resena">
                        <i class="fa-solid fa-paper-plane"></i>
                        <span>Publicar Reseña</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="footer">
        <svg class="ola-animada" viewBox="0 25 150 22" preserveAspectRatio="none">
            <defs>
                <linearGradient id="gradiente-ola-cuenta">
                    <stop stop-color="#225d1d"></stop>
                    <stop offset="1" stop-color="#38ff4f"></stop>
                </linearGradient>
                <path id="w" d="M -160 40 L -101.33333333333334 27 L -42.66666666666667 40 L 15.999999999999993 27 L 74.66666666666666 40 L 133.33333333333331 27 L 192 40 L 192 80 L -160 80 Z"></path>
            </defs>
            <g>
                <use href="#w" x="48" y="0" fill="url(#gradiente-ola-cuenta)" opacity="0.3" class="ola-capa-1"></use>
                <use href="#w" x="68" y="3" fill="url(#gradiente-ola-cuenta)" opacity="0.4" class="ola-capa-2"></use>
            </g>
        </svg>

        <img id="planta1" class="planta-decorativa" src="img/planta.png" alt="">
        <img id="planta2" class="planta-decorativa" src="img/planta.png" alt="">
    </div>

    <script>
        const modalResena        = document.getElementById('modal-resena');
        const tituloModalResena  = document.getElementById('titulo-modal-resena');
        const subtituloResena    = document.getElementById('subtitulo-modal-resena');
        const inputIdReserva     = document.getElementById('resena-id-reserva');
        const inputIdCabana      = document.getElementById('resena-id-cabana');
        const inputCalificacion  = document.getElementById('resena-calificacion');
        const textoPuntuacion    = document.getElementById('texto-puntuacion');
        const textareaComentario = document.getElementById('resena-comentario');
        const botonesNota        = document.querySelectorAll('.btn-nota');

        function seleccionarPuntuacion(valor) {
            inputCalificacion.value = String(valor);
            textoPuntuacion.textContent = `${valor} / 5`;

            botonesNota.forEach((btn) => {
                const notaBtn = Number(btn.dataset.nota);
                btn.classList.toggle('activa', notaBtn === valor);
            });
        }

        botonesNota.forEach((btn) => {
            btn.addEventListener('click', () => {
                seleccionarPuntuacion(Number(btn.dataset.nota));
            });
        });

        document.querySelectorAll('.btn-resenar').forEach((boton) => {
            boton.addEventListener('click', () => {
                const idReserva    = boton.dataset.idReserva;
                const idCabana     = boton.dataset.idCabana;
                const nombreCabana = boton.dataset.nombreCabana;

                inputIdReserva.value        = idReserva;
                inputIdCabana.value         = idCabana;
                tituloModalResena.textContent = `Reseñar ${nombreCabana}`;
                subtituloResena.textContent   = `Reserva #${idReserva} • Califica tu estadía de 0 a 5`;
                textareaComentario.value    = '';
                seleccionarPuntuacion(5);

                modalResena.classList.add('activo');
                textareaComentario.focus();
            });
        });

        function cerrarModalResena() {
            modalResena.classList.remove('activo');
        }

        document.getElementById('btn-cerrar-modal-resena').addEventListener('click', cerrarModalResena);
        document.getElementById('btn-cancelar-resena').addEventListener('click', cerrarModalResena);

        modalResena.addEventListener('click', (evento) => {
            if (evento.target === modalResena) {
                cerrarModalResena();
            }
        });

        document.addEventListener('keydown', (evento) => {
            if (evento.key === 'Escape' && modalResena.classList.contains('activo')) {
                cerrarModalResena();
            }
        });
    </script>

</body>
</html>
<?php
require_once __DIR__ . '/seguridad/sesion.php';
require_once __DIR__ . '/configuracion/conexion.php';

iniciarSesionSegura();

$estaAutenticado = !empty($_SESSION['documento']) && (($_SESSION['tipo_cuenta'] ?? '') === 'huesped');
$conexion        = obtenerConexion();

$idCabana = filter_var($_GET['id_cabana'] ?? '', FILTER_VALIDATE_INT);

if (!$idCabana) {
    // Si entran sin ID, tomar la primera cabaña disponible con tarifa activa
    $primeraCabana = $conexion->query(
        'SELECT c.`id_cabaña`
         FROM `cabaña` c
         INNER JOIN `tarifa` t ON c.`id_cabaña` = t.`id_cabaña` AND t.activa = 1
         ORDER BY c.`id_cabaña` ASC
         LIMIT 1'
    )->fetchColumn();

    if (!$primeraCabana) {
        header('Location: catalogo.php');
        exit;
    }
    $idCabana = (int) $primeraCabana;
}

// 1. Obtener datos principales de la cabaña, tarifa activa y promedio de reseñas
$consultaCabana = $conexion->prepare(
    'SELECT c.`id_cabaña` AS id_cabana,
            c.nombre,
            c.capacidad,
            c.descripcion,
            t.id_tarifa,
            t.precio_noche,
            COALESCE(ROUND(AVG(r.calificacion), 1), 5.0) AS promedio_calificacion,
            COUNT(DISTINCT r.id_resena) AS total_resenas
     FROM `cabaña` c
     INNER JOIN `tarifa` t ON c.`id_cabaña` = t.`id_cabaña` AND t.activa = 1
     LEFT JOIN `reseña` r ON c.`id_cabaña` = r.`id_cabaña`
     WHERE c.`id_cabaña` = :id_cabana
     GROUP BY c.`id_cabaña`, c.nombre, c.capacidad, c.descripcion, t.id_tarifa, t.precio_noche
     LIMIT 1'
);
$consultaCabana->execute([':id_cabana' => $idCabana]);
$cabana = $consultaCabana->fetch();

if (!$cabana) {
    $_SESSION['error_reserva'] = 'La cabaña solicitada no está disponible.';
    header('Location: catalogo.php');
    exit;
}

// 2. Obtener las 3 fotografías de la cabaña desde `cabaña_foto`
$consultaFotos = $conexion->prepare(
    'SELECT id_foto, foto
     FROM `cabaña_foto`
     WHERE `id_cabaña` = :id_cabana
     ORDER BY id_foto ASC
     LIMIT 3'
);
$consultaFotos->execute([':id_cabana' => $idCabana]);
$fotosRegistradas = $consultaFotos->fetchAll();

$fotosRespaldo = ['img/cabaña1.jpg', 'img/f2.jpg', 'img/f3.jpg'];
$rutasFotos    = [];

for ($indice = 0; $indice < 3; $indice++) {
    if (!empty($fotosRegistradas[$indice]['foto'])) {
        $rutasFotos[$indice] = 'subidas/cabanas/' . rawurlencode($fotosRegistradas[$indice]['foto']);
    } else {
        $rutasFotos[$indice] = $fotosRespaldo[$indice];
    }
}

// 3. Obtener elementos/comodidades desde `inventario`
$consultaInventario = $conexion->prepare(
    'SELECT nombre_elemento, cantidad
     FROM `inventario`
     WHERE `id_cabaña` = :id_cabana AND estado <> \'En revisión\'
     ORDER BY id_inventario ASC
     LIMIT 6'
);
$consultaInventario->execute([':id_cabana' => $idCabana]);
$comodidades = $consultaInventario->fetchAll();

// 4. Obtener rangos de fechas ya reservadas para bloquearlas en el calendario
$consultaOcupadas = $conexion->prepare(
    'SELECT r.fecha_checkin, r.fecha_checkout
     FROM `reserva` r
     INNER JOIN `tarifa` t ON r.id_tarifa = t.id_tarifa
     WHERE t.`id_cabaña` = :id_cabana
       AND r.estado <> \'Cancelada\'
       AND r.fecha_checkout >= CURDATE()'
);
$consultaOcupadas->execute([':id_cabana' => $idCabana]);
$rangosOcupados = $consultaOcupadas->fetchAll();

// 5. Obtener todas las reseñas de la cabaña con nombre y apellido del usuario
$consultaResenas = $conexion->prepare(
    'SELECT r.calificacion,
            r.comentario,
            u.nombre,
            u.apellido
     FROM `reseña` r
     INNER JOIN `usuario` u ON r.id_cliente = u.documento
     WHERE r.`id_cabaña` = :id_cabana
     ORDER BY r.fecha_resena DESC, r.id_resena DESC'
);
$consultaResenas->execute([':id_cabana' => $idCabana]);
$listaResenas = $consultaResenas->fetchAll();

$precioNoche  = (float) $cabana['precio_noche'];
$capacidadMax = max(1, (int) $cabana['capacidad']);
$calificacion = number_format((float) $cabana['promedio_calificacion'], 1);
$totalResenas = (int) $cabana['total_resenas'];
$descripcion  = !empty($cabana['descripcion'])
    ? $cabana['descripcion']
    : 'Disfrute de una estadía inolvidable rodeada de naturaleza, aire puro y paisajes espectaculares. Ideal para desconectarse y relajarse con total comodidad.';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CabinNest - <?= limpiarTexto($cabana['nombre']) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lora:wght@600;700&family=Roboto+Slab:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/reserva.css">
    <script src="https://kit.fontawesome.com/3d84e79734.js" crossorigin="anonymous"></script>
    <link rel="icon" href="img/icono.png" type="image/png">

    <style>
        #reserva {
            flex-direction: column;
            align-items: center;
        }

        .seccion-resenas {
            width: 100%;
            max-width: 1240px;
            margin: 28px auto 16px;
            padding: 24px 28px;
            background: #fbf8f3;
            border: 1px solid #e5dfd5;
            border-radius: 18px;
            box-shadow: 0 8px 24px rgba(48, 64, 38, 0.06);
            box-sizing: border-box;
        }

        .resenas-encabezado {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            padding-bottom: 16px;
            margin-bottom: 20px;
            border-bottom: 1px solid #e5dfd5;
        }

        .resenas-titulo {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #304026;
        }

        .resenas-titulo i {
            color: #DF6D10;
            font-size: 20px;
        }

        .resenas-titulo h2 {
            margin: 0;
            font-family: 'Lora', serif;
            font-size: 22px;
            color: #304026;
        }

        .resenas-resumen-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f1ece2;
            color: #304026;
            padding: 6px 14px;
            border-radius: 999px;
            font-family: 'Inter', sans-serif;
            font-size: 13.5px;
            font-weight: 600;
        }

        .resenas-resumen-badge i {
            color: #DF6D10;
        }

        .grid-resenas {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
        }

        .tarjeta-resena {
            background: #ffffff;
            border: 1px solid #e8e2d8;
            border-radius: 14px;
            padding: 18px 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .resena-autor-fila {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .resena-autor {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .resena-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #304026;
            color: #fbf8f3;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .resena-autor strong {
            font-family: 'Inter', sans-serif;
            font-size: 14.5px;
            color: #2c2825;
        }

        .resena-estrellas {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            color: #DF6D10;
            font-size: 13px;
            flex-shrink: 0;
        }

        .resena-estrellas span {
            margin-left: 4px;
            font-family: 'Inter', sans-serif;
            font-weight: 700;
            color: #304026;
        }

        .resena-comentario {
            margin: 0;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            line-height: 1.55;
            color: #4f4a44;
        }

        .sin-resenas-mensaje {
            margin: 0;
            padding: 18px;
            text-align: center;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            color: #6b655e;
            background: #ffffff;
            border: 1px dashed #d8d0c5;
            border-radius: 12px;
        }
    </style>
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

            <?php if ($estaAutenticado): ?>
                <a href="cuenta.php" id="btn-reservar">
                    <i class="fa-solid fa-user" aria-hidden="true"></i>
                    <span>Mi Cuenta</span>
                </a>
            <?php else: ?>
                <a href="login.php" id="btn-reservar">
                    <i class="fa-solid fa-user" aria-hidden="true"></i>
                    <span>Iniciar Sesión</span>
                </a>
            <?php endif; ?>
        </div>
    </header>
    <main id="reserva">
        <div class="reserva-div">
            <section id="informacion" aria-label="Información de la cabaña">
                <a id="volver" href="catalogo.php">
                    <i class="fa-solid fa-circle-left" aria-hidden="true"></i>
                    <span>Volver al catálogo</span>
                </a>

                <div id="informacion-txt">
                    <h1><?= limpiarTexto($cabana['nombre']) ?></h1>
                    <div class="valoracion">
                        <i class="fa-solid fa-star" aria-hidden="true"></i>
                        <p class="reseñas"><?= $calificacion ?> (<?= $totalResenas ?> reseñas)</p>
                    </div>
                </div>

                <p class="subtitulo-cabana">Capacidad máxima para <?= $capacidadMax ?> <?= $capacidadMax === 1 ? 'persona' : 'personas' ?> • Todas las comodidades incluidas</p>

                <div class="cabaña-reserva">
                    <div class="fotos">
                        <div id="galeria-principal" class="galeria-contenedor">
                            <input type="radio" name="control-galeria" id="seleccion-foto-1" class="radio-oculto" checked>
                            <input type="radio" name="control-galeria" id="seleccion-foto-2" class="radio-oculto">
                            <input type="radio" name="control-galeria" id="seleccion-foto-3" class="radio-oculto">

                            <div id="visor-foto-grande" class="visor-principal">
                                <div id="diapositiva-1" class="diapositiva">
                                    <img src="<?= limpiarTexto($rutasFotos[0]) ?>" alt="<?= limpiarTexto($cabana['nombre']) ?> - Vista 1" class="imagen-principal">
                                    <div class="insignia-contador">
                                        <svg class="icono-camara" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                                            <circle cx="12" cy="13" r="4"/>
                                        </svg>
                                        <span>1 / 3</span>
                                    </div>
                                    <label for="seleccion-foto-3" class="boton-navegacion boton-anterior" aria-label="Foto anterior">❮</label>
                                    <label for="seleccion-foto-2" class="boton-navegacion boton-siguiente" aria-label="Foto siguiente">❯</label>
                                </div>

                                <div id="diapositiva-2" class="diapositiva">
                                    <img src="<?= limpiarTexto($rutasFotos[1]) ?>" alt="<?= limpiarTexto($cabana['nombre']) ?> - Vista 2" class="imagen-principal">
                                    <div class="insignia-contador">
                                        <svg class="icono-camara" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                                            <circle cx="12" cy="13" r="4"/>
                                        </svg>
                                        <span>2 / 3</span>
                                    </div>
                                    <label for="seleccion-foto-1" class="boton-navegacion boton-anterior" aria-label="Foto anterior">❮</label>
                                    <label for="seleccion-foto-3" class="boton-navegacion boton-siguiente" aria-label="Foto siguiente">❯</label>
                                </div>

                                <div id="diapositiva-3" class="diapositiva">
                                    <img src="<?= limpiarTexto($rutasFotos[2]) ?>" alt="<?= limpiarTexto($cabana['nombre']) ?> - Vista 3" class="imagen-principal">
                                    <div class="insignia-contador">
                                        <svg class="icono-camara" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                                            <circle cx="12" cy="13" r="4"/>
                                        </svg>
                                        <span>3 / 3</span>
                                    </div>
                                    <label for="seleccion-foto-2" class="boton-navegacion boton-anterior" aria-label="Foto anterior">❮</label>
                                    <label for="seleccion-foto-1" class="boton-navegacion boton-siguiente" aria-label="Foto siguiente">❯</label>
                                </div>
                            </div>

                            <div id="barra-miniaturas" class="fila-miniaturas">
                                <label for="seleccion-foto-1" id="miniatura-1" class="miniatura">
                                    <img src="<?= limpiarTexto($rutasFotos[0]) ?>" alt="Miniatura 1" class="imagen-miniatura">
                                </label>
                                <label for="seleccion-foto-2" id="miniatura-2" class="miniatura">
                                    <img src="<?= limpiarTexto($rutasFotos[1]) ?>" alt="Miniatura 2" class="imagen-miniatura">
                                </label>
                                <label for="seleccion-foto-3" id="miniatura-3" class="miniatura">
                                    <img src="<?= limpiarTexto($rutasFotos[2]) ?>" alt="Miniatura 3" class="imagen-miniatura">
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="caracteristicas">
                        <div id="caracteristicas-titulo" class="caracteristicas-list">
                            <i class="fa-brands fa-yelp" aria-hidden="true"></i>
                            <h2>Equipamiento y Comodidades</h2>
                        </div>
                        <ul class="lista-comodidades">
                            <?php if (empty($comodidades)): ?>
                                <li class="caracteristicas-list">
                                    <i class="fa-solid fa-wifi" aria-hidden="true"></i>
                                    <span>WiFi gratis</span>
                                </li>
                                <li class="caracteristicas-list">
                                    <i class="fa-solid fa-fan" aria-hidden="true"></i>
                                    <span>Aire Acondicionado</span>
                                </li>
                                <li class="caracteristicas-list">
                                    <i class="fa-solid fa-kitchen-set" aria-hidden="true"></i>
                                    <span>Cocina Equipada</span>
                                </li>
                            <?php else: ?>
                                <?php foreach ($comodidades as $item): ?>
                                    <li class="caracteristicas-list">
                                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                                        <span><?= limpiarTexto($item['nombre_elemento']) ?> (<?= (int) $item['cantidad'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <div id="descripcion">
                    <article id="descripcion1">
                        <div id="descripcion-icon">
                            <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
                            <h3>Descripción</h3>
                        </div>
                        <p><?= limpiarTexto($descripcion) ?></p>
                    </article>

                    <aside id="descripcion2">
                        <img src="img/tree.png" alt="Árbol ilustrativo">
                        <div id="descripcion2-txt">
                            <h4>Conecta con la naturaleza</h4>
                            <p>Respira aire puro y vive una experiencia inolvidable</p>
                        </div>
                    </aside>
                </div>
            </section>

            <aside id="calendario" aria-label="Formulario de reserva">
                <div class="tarjeta-reserva">
                    <div class="encabezado-tarjeta">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        <h2>Reservar Esta Cabaña</h2>
                    </div>

                    <div class="cuerpo-tarjeta">
                        <div class="navegacion-calendario">
                            <button type="button" class="boton-nav" id="mesAnterior" aria-label="Mes anterior">‹</button>
                            <div class="etiqueta-mes" id="etiquetaMes">Noviembre 2026</div>
                            <button type="button" class="boton-nav" id="mesSiguiente" aria-label="Mes siguiente">›</button>
                        </div>

                        <div class="dias-semana" aria-hidden="true">
                            <div>Do</div><div>Lu</div><div>Ma</div><div>Mi</div><div>Ju</div><div>Vi</div><div>Sá</div>
                        </div>

                        <div class="cuadricula-dias" id="cuadriculaDias"></div>

                        <div class="leyenda">
                            <div class="item-leyenda"><span class="punto entrada" aria-hidden="true"></span> Entrada</div>
                            <div class="item-leyenda"><span class="punto salida" aria-hidden="true"></span> Salida</div>
                            <div class="item-leyenda"><span class="punto ocupado" aria-hidden="true"></span> Ocupado</div>
                        </div>

                        <div class="caja-info">
                            <div class="info-izquierda">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4a453f" stroke-width="1.8" aria-hidden="true">
                                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                                </svg>
                                <span>Noches</span>
                            </div>
                            <div class="info-derecha">
                                <span id="cantidadNoches" aria-live="polite">0</span>
                            </div>
                        </div>

                        <div class="caja-info">
                            <div class="info-izquierda">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4a453f" stroke-width="1.8" aria-hidden="true">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                </svg>
                                <label for="selectorHuespedes">Huéspedes</label>
                            </div>
                            <div class="info-derecha">
                                <select id="selectorHuespedes" class="selector-huespedes">
                                    <?php for ($persona = 1; $persona <= $capacidadMax; $persona++): ?>
                                        <option value="<?= $persona ?>" <?= ($persona === min(2, $capacidadMax)) ? 'selected' : '' ?>>
                                            <?= $persona ?> <?= $persona === 1 ? 'Persona' : 'Personas' ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <div class="seccion-precio">
                            <div class="precio-noche">$<?= number_format($precioNoche, 0) ?> <span>/ noche</span></div>
                            <div class="precio-total" id="precioTotal" aria-live="polite">Total: $0</div>
                        </div>

                        <button type="button" class="boton-confirmar" id="botonConfirmar">
                            <span>Confirmar Reserva</span>
                        </button>
                    </div>
                </div>
            </aside>

        </div>

        <section class="seccion-resenas" aria-labelledby="titulo-resenas-cabana">
            <div class="resenas-encabezado">
                <div class="resenas-titulo">
                    <i class="fa-solid fa-comments" aria-hidden="true"></i>
                    <h2 id="titulo-resenas-cabana">Reseñas de Huéspedes</h2>
                </div>
                <span class="resenas-resumen-badge">
                    <i class="fa-solid fa-star" aria-hidden="true"></i>
                    <?= $calificacion ?> • <?= $totalResenas ?> <?= $totalResenas === 1 ? 'reseña' : 'reseñas' ?>
                </span>
            </div>

            <?php if (empty($listaResenas)): ?>
                <p class="sin-resenas-mensaje">
                    Aún no hay reseñas registradas para esta cabaña.
                </p>
            <?php else: ?>
                <div class="grid-resenas">
                    <?php foreach ($listaResenas as $resena):
                        $nombreAutor   = trim($resena['nombre'] . ' ' . $resena['apellido']);
                        $iniNombre     = mb_strtoupper(mb_substr(trim($resena['nombre']), 0, 1, 'UTF-8'), 'UTF-8');
                        $iniApellido   = mb_strtoupper(mb_substr(trim($resena['apellido']), 0, 1, 'UTF-8'), 'UTF-8');
                        $iniciales     = ($iniNombre . $iniApellido) ?: 'US';
                        $puntuacion    = max(1, min(5, (int) $resena['calificacion']));
                        $comentarioTxt = trim((string) ($resena['comentario'] ?? ''));
                    ?>
                        <article class="tarjeta-resena">
                            <div class="resena-autor-fila">
                                <div class="resena-autor">
                                    <span class="resena-avatar" aria-hidden="true"><?= limpiarTexto($iniciales) ?></span>
                                    <strong><?= limpiarTexto($nombreAutor) ?></strong>
                                </div>
                                <div class="resena-estrellas" aria-label="Puntuación: <?= $puntuacion ?> de 5">
                                    <?php for ($estrella = 1; $estrella <= 5; $estrella++): ?>
                                        <i class="<?= $estrella <= $puntuacion ? 'fa-solid' : 'fa-regular' ?> fa-star" aria-hidden="true"></i>
                                    <?php endfor; ?>
                                    <span><?= number_format((float) $puntuacion, 1) ?></span>
                                </div>
                            </div>

                            <?php if ($comentarioTxt !== ''): ?>
                                <p class="resena-comentario"><?= limpiarTexto($comentarioTxt) ?></p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <script>
        window.datosCabanaReserva = {
            idCabana: <?= json_encode($idCabana) ?>,
            precioNoche: <?= json_encode($precioNoche) ?>,
            sesionIniciada: <?= json_encode($estaAutenticado) ?>,
            rangosOcupados: <?= json_encode($rangosOcupados) ?>,
            checkinInicial: <?= json_encode($_GET['checkin'] ?? '') ?>,
            checkoutInicial: <?= json_encode($_GET['checkout'] ?? '') ?>
        };
    </script>

    <script src="js/calendario.js"></script>

    <script>
        (function () {
            const datos = window.datosCabanaReserva;
            const spanNoches = document.getElementById("cantidadNoches");
            const divTotal = document.getElementById("precioTotal");
            const botonConfirmar = document.getElementById("botonConfirmar");
            const selectorHuespedes = document.getElementById("selectorHuespedes");
            const etiquetaMes = document.getElementById("etiquetaMes");
            const cuadriculaDias = document.getElementById("cuadriculaDias");

            const mesesEspanol = {
                enero: "01", febrero: "02", marzo: "03", abril: "04",
                mayo: "05", junio: "06", julio: "07", agosto: "08",
                septiembre: "09", octubre: "10", noviembre: "11", diciembre: "12"
            };

            function actualizarPrecioReal() {
                const noches = parseInt(spanNoches.textContent, 10) || 0;
                const total = noches * datos.precioNoche;
                divTotal.textContent = `Total: $${total.toLocaleString("es-CO")}`;
            }

            const observadorNoches = new MutationObserver(actualizarPrecioReal);
            observadorNoches.observe(spanNoches, { childList: true, characterData: true, subtree: true });
            actualizarPrecioReal();

            function obtenerFechasSeleccionadas() {
                const textoMesAnio = etiquetaMes.textContent.trim().toLowerCase();
                const partes = textoMesAnio.split(/\s+/);
                const numeroMes = mesesEspanol[partes[0]] || "";
                const anio = partes[1] || "";

                const celdaEntrada = cuadriculaDias.querySelector(".entrada, .check-in, .selected-start");
                const celdaSalida  = cuadriculaDias.querySelector(".salida, .check-out, .selected-end");

                if (celdaEntrada && celdaSalida && numeroMes && anio) {
                    const diaIn  = celdaEntrada.textContent.trim().padStart(2, "0");
                    const diaOut = celdaSalida.textContent.trim().padStart(2, "0");
                    return {
                        checkin: `${anio}-${numeroMes}-${diaIn}`,
                        checkout: `${anio}-${numeroMes}-${diaOut}`
                    };
                }

                return {
                    checkin: datos.checkinInicial,
                    checkout: datos.checkoutInicial
                };
            }

            botonConfirmar.addEventListener("click", (evento) => {
                evento.stopImmediatePropagation();

                if (!datos.sesionIniciada) {
                    alert("Debes iniciar sesión con tu cuenta de huésped para continuar con la reserva y el pago.");
                    window.location.href = "login.php";
                    return;
                }

                const noches = parseInt(spanNoches.textContent, 10) || 0;
                if (noches <= 0) {
                    alert("Selecciona las fechas de entrada y salida en el calendario antes de continuar.");
                    return;
                }

                const fechas = obtenerFechasSeleccionadas();
                const parametros = new URLSearchParams({
                    id_cabana: datos.idCabana,
                    huespedes: selectorHuespedes.value
                });

                if (fechas.checkin) parametros.set("checkin", fechas.checkin);
                if (fechas.checkout) parametros.set("checkout", fechas.checkout);

                window.location.href = `confirmar.php?${parametros.toString()}`;
            }, true);
        })();
    </script>
</body>
</html>
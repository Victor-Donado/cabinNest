<?php
require_once __DIR__ . '/seguridad/proteger_admin.php';
require_once __DIR__ . '/configuracion/conexion.php';

$conexion       = obtenerConexion();
$documentoAdmin = (int) ($_SESSION['documento'] ?? 0);

// Nombre del administrador autenticado desde `usuario`
$consultaAdmin = $conexion->prepare('SELECT nombre FROM `usuario` WHERE documento = :doc LIMIT 1');
$consultaAdmin->execute([':doc' => $documentoAdmin]);
$nombreAdmin = $consultaAdmin->fetchColumn() ?: 'Administrador';

// KPI 1: Ocupación actual desde `cabaña`
$datosOcupacion = $conexion->query(
    'SELECT COUNT(*) AS total_cabanas,
            COALESCE(SUM(CASE WHEN ocupado = 1 THEN 1 ELSE 0 END), 0) AS cabanas_ocupadas
     FROM `cabaña`'
)->fetch();

$totalCabanas        = (int) ($datosOcupacion['total_cabanas'] ?? 0);
$cabanasOcupadas     = (int) ($datosOcupacion['cabanas_ocupadas'] ?? 0);
$porcentajeOcupacion = $totalCabanas > 0 ? (int) round(($cabanasOcupadas / $totalCabanas) * 100) : 0;

// KPI 2: Ingresos totales e ingresos del mes actual desde `pago` + `reserva`
$ingresosTotales = (float) $conexion->query(
    'SELECT COALESCE(SUM(p.monto), 0)
     FROM `pago` p
     INNER JOIN `reserva` r ON p.id_reserva = r.id_reserva
     WHERE r.estado <> \'Cancelada\''
)->fetchColumn();

$ingresosMesActual = (float) $conexion->query(
    'SELECT COALESCE(SUM(p.monto), 0)
     FROM `pago` p
     INNER JOIN `reserva` r ON p.id_reserva = r.id_reserva
     WHERE r.estado <> \'Cancelada\'
       AND YEAR(p.fecha_pago) = YEAR(CURDATE())
       AND MONTH(p.fecha_pago) = MONTH(CURDATE())'
)->fetchColumn();

// KPI 3: Check-ins y Check-outs programados para hoy
$checkinsHoy = (int) $conexion->query(
    'SELECT COUNT(*) FROM `reserva` WHERE fecha_checkin = CURDATE() AND estado <> \'Cancelada\''
)->fetchColumn();

$checkoutsHoy = (int) $conexion->query(
    'SELECT COUNT(*) FROM `reserva` WHERE fecha_checkout = CURDATE() AND estado <> \'Cancelada\''
)->fetchColumn();

// KPI 4: Reservas activas vs total histórico
$reservasActivas = (int) $conexion->query(
    'SELECT COUNT(*) FROM `reserva` WHERE estado IN (\'Confirmada\', \'En curso\', \'Pendiente\')'
)->fetchColumn();

$totalReservas = (int) $conexion->query('SELECT COUNT(*) FROM `reserva`')->fetchColumn();

// Actividad reciente combinando últimas reservas y últimos pagos
$ultimasReservas = $conexion->query(
    'SELECT r.id_reserva, r.fecha_reserva AS fecha_evento, r.estado,
            c.nombre AS nombre_cabana, u.nombre, u.apellido
     FROM `reserva` r
     INNER JOIN `tarifa` t ON r.id_tarifa = t.id_tarifa
     INNER JOIN `cabaña` c ON t.`id_cabaña` = c.`id_cabaña`
     INNER JOIN `usuario` u ON r.id_cliente = u.documento
     ORDER BY r.fecha_reserva DESC
     LIMIT 5'
)->fetchAll();

$ultimosPagos = $conexion->query(
    'SELECT pg.id_pago, pg.id_reserva, pg.fecha_pago AS fecha_evento, pg.monto, pg.metodo_pago
     FROM `pago` pg
     ORDER BY pg.fecha_pago DESC
     LIMIT 5'
)->fetchAll();

$eventosActividad = [];

foreach ($ultimasReservas as $res) {
    $esCancelada = ($res['estado'] === 'Cancelada');
    $eventosActividad[] = [
        'timestamp' => strtotime($res['fecha_evento']),
        'clase'     => $esCancelada ? 'rojo' : 'verde-claro',
        'icono'     => $esCancelada ? 'fa-regular fa-calendar-xmark' : 'fa-regular fa-calendar-check',
        'titulo'    => $esCancelada ? 'Reserva cancelada' : 'Reserva ' . mb_strtolower($res['estado']),
        'detalle'   => $res['nombre_cabana'] . ' • ' . $res['nombre'] . ' ' . $res['apellido'],
    ];
}

foreach ($ultimosPagos as $pag) {
    $eventosActividad[] = [
        'timestamp' => strtotime($pag['fecha_evento']),
        'clase'     => 'naranja',
        'icono'     => 'fa-regular fa-credit-card',
        'titulo'    => 'Pago recibido (' . $pag['metodo_pago'] . ')',
        'detalle'   => '$' . number_format((float) $pag['monto'], 2) . ' - Reserva #' . (int) $pag['id_reserva'],
    ];
}

usort($eventosActividad, static fn(array $a, array $b): int => $b['timestamp'] <=> $a['timestamp']);
$eventosActividad = array_slice($eventosActividad, 0, 5);

function tiempoRelativo(int $marcaTiempo): string
{
    $diferencia = max(0, time() - $marcaTiempo);
    if ($diferencia < 60) {
        return 'Hace un momento';
    }
    if ($diferencia < 3600) {
        $minutos = (int) floor($diferencia / 60);
        return "Hace {$minutos} min";
    }
    if ($diferencia < 86400) {
        $horas = (int) floor($diferencia / 3600);
        return $horas === 1 ? 'Hace 1 hora' : "Hace {$horas} horas";
    }
    $dias = (int) floor($diferencia / 86400);
    return $dias === 1 ? 'Hace 1 día' : "Hace {$dias} días";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CabinNest - DashBoard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lora:wght@600;700&family=Roboto+Slab:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/dashboard.css">
    <script src="https://kit.fontawesome.com/3d84e79734.js" crossorigin="anonymous"></script>
    <link rel="icon" href="img/icono.png" type="image/png">
</head>
<body>

    <!-- Control oculto para abrir/cerrar menú desplegable en <= 740px -->
    <input type="checkbox" id="menu-toggle" class="menu-checkbox" aria-hidden="true">

    <!-- ================= MENÚ LATERAL / DESPLEGABLE ================= -->
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
            <a class="activo" href="dashboard.php" aria-current="page">
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
            <a href="usuarios.php">
                <i class="fa-solid fa-users" aria-hidden="true"></i>
                <span>Usuarios</span>
            </a>
        </nav>

        <img class="img-footer" src="img/menu-admin.jpg" alt="Ilustración natural CabinNest">
    </aside>

    <!-- Fondo oscuro para cerrar el menú al hacer clic afuera -->
    <label for="menu-toggle" class="menu-overlay" aria-label="Cerrar menú"></label>

    <!-- ================= CONTENIDO PRINCIPAL ================= -->
    <main class="dashboard">

        <!-- Cabecera Superior -->
        <header class="cabeza-dashboard">
            <label for="menu-toggle" class="btn-hamburguesa" role="button" tabindex="0" aria-label="Abrir menú">
                <span></span>
                <span></span>
                <span></span>
            </label>

            <div class="cabeza-txt">
                <h1>Bienvenido, <?= limpiarTexto($nombreAdmin) ?></h1>
                <p>Aquí tienes un resumen general en tiempo real</p>
            </div>

            <a href="acciones/cerrar_sesion.php" id="cerrar-sesion">
                <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                <span>Cerrar Sesión</span>
            </a>
        </header>

        <div class="dashboard-contenido">

            <div class="grid-kpis">

                <div class="tarjeta-kpi">
                    <div class="icono-circulo verde grande">
                        <i class="fa-solid fa-house-chimney"></i>
                    </div>
                    <div class="kpi-datos">
                        <span class="kpi-titulo">Ocupación Actual</span>
                        <strong class="kpi-valor"><?= $porcentajeOcupacion ?>%</strong>
                        <span class="kpi-tendencia">
                            <i class="fa-solid fa-campground"></i> <?= $cabanasOcupadas ?> de <?= $totalCabanas ?> cabañas ocupadas
                        </span>
                    </div>
                    <svg class="sparkline" viewBox="0 0 90 42">
                        <defs>
                            <linearGradient id="grad-kpi-1" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#3a6b28" stop-opacity="0.28"/>
                                <stop offset="100%" stop-color="#3a6b28" stop-opacity="0"/>
                            </linearGradient>
                        </defs>
                        <path d="M2 34 C14 34, 18 22, 30 22 C42 22, 46 28, 58 18 C70 8, 76 10, 88 6 L88 40 L2 40 Z" fill="url(#grad-kpi-1)"/>
                        <path d="M2 34 C14 34, 18 22, 30 22 C42 22, 46 28, 58 18 C70 8, 76 10, 88 6" fill="none" stroke="#304026" stroke-width="2.2" stroke-linecap="round"/>
                    </svg>
                </div>

                <div class="tarjeta-kpi">
                    <div class="icono-circulo naranja grande">
                        <i class="fa-solid fa-dollar-sign"></i>
                    </div>
                    <div class="kpi-datos">
                        <span class="kpi-titulo">Ingresos Totales</span>
                        <strong class="kpi-valor">$<?= number_format($ingresosTotales, 0) ?></strong>
                        <span class="kpi-tendencia">
                            <i class="fa-solid fa-arrow-up"></i> $<?= number_format($ingresosMesActual, 0) ?> este mes
                        </span>
                    </div>
                    <svg class="sparkline" viewBox="0 0 90 42">
                        <defs>
                            <linearGradient id="grad-kpi-2" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#DF6D10" stop-opacity="0.28"/>
                                <stop offset="100%" stop-color="#DF6D10" stop-opacity="0"/>
                            </linearGradient>
                        </defs>
                        <path d="M2 35 C15 33, 22 25, 34 26 C46 27, 52 17, 64 19 C76 21, 80 10, 88 7 L88 40 L2 40 Z" fill="url(#grad-kpi-2)"/>
                        <path d="M2 35 C15 33, 22 25, 34 26 C46 27, 52 17, 64 19 C76 21, 80 10, 88 7" fill="none" stroke="#DF6D10" stroke-width="2.2" stroke-linecap="round"/>
                    </svg>
                </div>

                <div class="tarjeta-kpi">
                    <div class="icono-circulo verde grande">
                        <i class="fa-regular fa-calendar-check"></i>
                    </div>
                    <div class="kpi-datos">
                        <span class="kpi-titulo">Check-ins Hoy</span>
                        <strong class="kpi-valor"><?= $checkinsHoy ?></strong>
                        <span class="kpi-tendencia">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i> <?= $checkoutsHoy ?> check-outs hoy
                        </span>
                    </div>
                    <svg class="sparkline" viewBox="0 0 90 42">
                        <defs>
                            <linearGradient id="grad-kpi-3" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#3a6b28" stop-opacity="0.28"/>
                                <stop offset="100%" stop-color="#3a6b28" stop-opacity="0"/>
                            </linearGradient>
                        </defs>
                        <path d="M2 36 C16 35, 22 24, 34 24 C46 24, 52 15, 64 15 C75 15, 80 8, 88 6 L88 40 L2 40 Z" fill="url(#grad-kpi-3)"/>
                        <path d="M2 36 C16 35, 22 24, 34 24 C46 24, 52 15, 64 15 C75 15, 80 8, 88 6" fill="none" stroke="#304026" stroke-width="2.2" stroke-linecap="round"/>
                    </svg>
                </div>

                <div class="tarjeta-kpi">
                    <div class="icono-circulo naranja grande">
                        <i class="fa-solid fa-bed"></i>
                    </div>
                    <div class="kpi-datos">
                        <span class="kpi-titulo">Reservas Activas</span>
                        <strong class="kpi-valor"><?= $reservasActivas ?></strong>
                        <span class="kpi-tendencia">
                            <i class="fa-regular fa-calendar"></i> <?= $totalReservas ?> reservas registradas
                        </span>
                    </div>
                    <svg class="sparkline" viewBox="0 0 90 42">
                        <defs>
                            <linearGradient id="grad-kpi-4" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#DF6D10" stop-opacity="0.28"/>
                                <stop offset="100%" stop-color="#DF6D10" stop-opacity="0"/>
                            </linearGradient>
                        </defs>
                        <path d="M2 34 C14 32, 20 20, 32 21 C44 22, 50 18, 62 14 C74 10, 80 12, 88 6 L88 40 L2 40 Z" fill="url(#grad-kpi-4)"/>
                        <path d="M2 34 C14 32, 20 20, 32 21 C44 22, 50 18, 62 14 C74 10, 80 12, 88 6" fill="none" stroke="#DF6D10" stroke-width="2.2" stroke-linecap="round"/>
                    </svg>
                </div>

            </div>

            <div class="grid-analitica">

                <div class="tarjeta-panel panel-estadisticas">
                    <input type="radio" name="periodo-chart" id="tab-30d" class="radio-oculto" checked>
                    <input type="radio" name="periodo-chart" id="tab-3m" class="radio-oculto">
                    <input type="radio" name="periodo-chart" id="tab-1a" class="radio-oculto">

                    <div class="panel-encabezado">
                        <div class="panel-titulo">
                            <i class="fa-solid fa-chart-simple"></i>
                            <h2>Estadísticas de Reservas</h2>
                        </div>

                        <div class="filtros-periodo">
                            <label for="tab-30d" class="btn-periodo" id="lbl-30d">30 Días</label>
                            <label for="tab-3m" class="btn-periodo" id="lbl-3m">3 Meses</label>
                            <label for="tab-1a" class="btn-periodo" id="lbl-1a">1 Año</label>
                        </div>
                    </div>

                    <div class="leyenda-grafica">
                        <span class="item-leyenda"><i class="punto verde"></i> Reservas</span>
                        <span class="item-leyenda"><i class="punto naranja"></i> Ingresos</span>
                    </div>

                    <div class="contenedor-svg vista-30d">
                        <svg viewBox="0 0 600 225" class="grafica-lineas">
                            <defs>
                                <linearGradient id="area-verde" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#3a6b28" stop-opacity="0.22"/>
                                    <stop offset="100%" stop-color="#3a6b28" stop-opacity="0.01"/>
                                </linearGradient>
                                <linearGradient id="area-naranja" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#DF6D10" stop-opacity="0.22"/>
                                    <stop offset="100%" stop-color="#DF6D10" stop-opacity="0.01"/>
                                </linearGradient>
                            </defs>

                            <g class="cuadricula-svg">
                                <line x1="42" y1="24" x2="582" y2="24"/>
                                <line x1="42" y1="64" x2="582" y2="64"/>
                                <line x1="42" y1="104" x2="582" y2="104"/>
                                <line x1="42" y1="144" x2="582" y2="144"/>
                                <line x1="42" y1="184" x2="582" y2="184"/>
                            </g>

                            <g class="etiquetas-eje">
                                <text x="30" y="28" text-anchor="end">20</text>
                                <text x="30" y="68" text-anchor="end">15</text>
                                <text x="30" y="108" text-anchor="end">10</text>
                                <text x="30" y="148" text-anchor="end">5</text>
                                <text x="30" y="188" text-anchor="end">0</text>
                            </g>

                            <path d="M52 132 C85 124, 100 94, 128 94 C156 94, 172 114, 198 106 C225 98, 240 82, 266 84 C292 86, 308 112, 336 108 C364 104, 380 86, 408 86 C436 86, 452 106, 480 96 C508 86, 535 56, 574 42 L574 184 L52 184 Z" fill="url(#area-verde)"/>
                            <path d="M52 132 C85 124, 100 94, 128 94 C156 94, 172 114, 198 106 C225 98, 240 82, 266 84 C292 86, 308 112, 336 108 C364 104, 380 86, 408 86 C436 86, 452 106, 480 96 C508 86, 535 56, 574 42" fill="none" stroke="#2d4a22" stroke-width="2.6" stroke-linecap="round"/>

                            <path d="M52 150 C85 144, 100 118, 128 118 C156 118, 172 134, 198 126 C225 118, 240 108, 266 110 C292 112, 308 134, 336 128 C364 122, 380 104, 408 104 C436 104, 452 122, 480 112 C508 102, 535 76, 574 64 L574 184 L52 184 Z" fill="url(#area-naranja)"/>
                            <path d="M52 150 C85 144, 100 118, 128 118 C156 118, 172 134, 198 126 C225 118, 240 108, 266 110 C292 112, 308 134, 336 128 C364 122, 380 104, 408 104 C436 104, 452 122, 480 112 C508 102, 535 76, 574 64" fill="none" stroke="#DF6D10" stroke-width="2.6" stroke-linecap="round"/>

                            <g fill="#2d4a22">
                                <circle cx="52" cy="132" r="3.5"/><circle cx="128" cy="94" r="3.5"/><circle cx="198" cy="106" r="3.5"/>
                                <circle cx="266" cy="84" r="3.5"/><circle cx="336" cy="108" r="3.5"/><circle cx="408" cy="86" r="3.5"/>
                                <circle cx="480" cy="96" r="3.5"/><circle cx="574" cy="42" r="4"/>
                            </g>
                            <g fill="#DF6D10">
                                <circle cx="52" cy="150" r="3.5"/><circle cx="128" cy="118" r="3.5"/><circle cx="198" cy="126" r="3.5"/>
                                <circle cx="266" cy="110" r="3.5"/><circle cx="336" cy="128" r="3.5"/><circle cx="408" cy="104" r="3.5"/>
                                <circle cx="480" cy="112" r="3.5"/><circle cx="574" cy="64" r="4"/>
                            </g>

                            <g class="etiquetas-eje">
                                <text x="52" y="210" text-anchor="middle">Sem 1</text>
                                <text x="182" y="210" text-anchor="middle">Sem 2</text>
                                <text x="313" y="210" text-anchor="middle">Sem 3</text>
                                <text x="443" y="210" text-anchor="middle">Sem 4</text>
                                <text x="570" y="210" text-anchor="middle">Hoy</text>
                            </g>
                        </svg>
                    </div>

                    <div class="contenedor-svg vista-3m">
                        <svg viewBox="0 0 600 225" class="grafica-lineas">
                            <g class="cuadricula-svg">
                                <line x1="42" y1="24" x2="582" y2="24"/><line x1="42" y1="64" x2="582" y2="64"/>
                                <line x1="42" y1="104" x2="582" y2="104"/><line x1="42" y1="144" x2="582" y2="144"/>
                                <line x1="42" y1="184" x2="582" y2="184"/>
                            </g>
                            <g class="etiquetas-eje">
                                <text x="30" y="28" text-anchor="end">60</text><text x="30" y="68" text-anchor="end">45</text>
                                <text x="30" y="108" text-anchor="end">30</text><text x="30" y="148" text-anchor="end">15</text>
                                <text x="30" y="188" text-anchor="end">0</text>
                            </g>
                            <path d="M52 118 C140 96, 220 112, 313 78 C405 44, 490 62, 574 34 L574 184 L52 184 Z" fill="url(#area-verde)"/>
                            <path d="M52 118 C140 96, 220 112, 313 78 C405 44, 490 62, 574 34" fill="none" stroke="#2d4a22" stroke-width="2.6" stroke-linecap="round"/>
                            <path d="M52 138 C140 120, 220 130, 313 98 C405 66, 490 82, 574 54 L574 184 L52 184 Z" fill="url(#area-naranja)"/>
                            <path d="M52 138 C140 120, 220 130, 313 98 C405 66, 490 82, 574 54" fill="none" stroke="#DF6D10" stroke-width="2.6" stroke-linecap="round"/>
                            <g class="etiquetas-eje">
                                <text x="52" y="210" text-anchor="middle">Mes -2</text>
                                <text x="313" y="210" text-anchor="middle">Mes -1</text>
                                <text x="570" y="210" text-anchor="middle">Actual</text>
                            </g>
                        </svg>
                    </div>

                    <div class="contenedor-svg vista-1a">
                        <svg viewBox="0 0 600 225" class="grafica-lineas">
                            <g class="cuadricula-svg">
                                <line x1="42" y1="24" x2="582" y2="24"/><line x1="42" y1="64" x2="582" y2="64"/>
                                <line x1="42" y1="104" x2="582" y2="104"/><line x1="42" y1="144" x2="582" y2="144"/>
                                <line x1="42" y1="184" x2="582" y2="184"/>
                            </g>
                            <g class="etiquetas-eje">
                                <text x="30" y="28" text-anchor="end">200</text><text x="30" y="68" text-anchor="end">150</text>
                                <text x="30" y="108" text-anchor="end">100</text><text x="30" y="148" text-anchor="end">50</text>
                                <text x="30" y="188" text-anchor="end">0</text>
                            </g>
                            <path d="M52 140 C150 110, 240 60, 313 88 C390 116, 480 48, 574 30 L574 184 L52 184 Z" fill="url(#area-verde)"/>
                            <path d="M52 140 C150 110, 240 60, 313 88 C390 116, 480 48, 574 30" fill="none" stroke="#2d4a22" stroke-width="2.6" stroke-linecap="round"/>
                            <path d="M52 156 C150 128, 240 82, 313 108 C390 132, 480 68, 574 52 L574 184 L52 184 Z" fill="url(#area-naranja)"/>
                            <path d="M52 156 C150 128, 240 82, 313 108 C390 132, 480 68, 574 52" fill="none" stroke="#DF6D10" stroke-width="2.6" stroke-linecap="round"/>
                            <g class="etiquetas-eje">
                                <text x="52" y="210" text-anchor="middle">Ene</text>
                                <text x="182" y="210" text-anchor="middle">Abr</text>
                                <text x="313" y="210" text-anchor="middle">Jul</text>
                                <text x="443" y="210" text-anchor="middle">Oct</text>
                                <text x="570" y="210" text-anchor="middle">Dic</text>
                            </g>
                        </svg>
                    </div>
                </div>

                <div class="tarjeta-panel panel-actividad">
                    <div class="panel-encabezado">
                        <div class="panel-titulo">
                            <i class="fa-regular fa-bell"></i>
                            <h2>Actividad Reciente</h2>
                        </div>
                        <a href="reservas.php" class="enlace-ver-todas">
                            <span>Ver todas</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>

                    <div class="lista-actividad">
                        <?php if (empty($eventosActividad)): ?>
                            <div class="item-actividad">
                                <div class="icono-circulo verde-claro">
                                    <i class="fa-regular fa-calendar"></i>
                                </div>
                                <div class="actividad-info">
                                    <strong>Sin actividad reciente</strong>
                                    <span>Las nuevas reservas y pagos aparecerán aquí</span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($eventosActividad as $evento): ?>
                            <div class="item-actividad">
                                <div class="icono-circulo <?= limpiarTexto($evento['clase']) ?>">
                                    <i class="<?= limpiarTexto($evento['icono']) ?>"></i>
                                </div>
                                <div class="actividad-info">
                                    <strong><?= limpiarTexto($evento['titulo']) ?></strong>
                                    <span><?= limpiarTexto($evento['detalle']) ?></span>
                                </div>
                                <span class="actividad-tiempo"><?= limpiarTexto(tiempoRelativo($evento['timestamp'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <script>
        const menuToggle = document.getElementById('menu-toggle');

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && menuToggle && menuToggle.checked) {
                menuToggle.checked = false;
            }
        });
    </script>

</body>
</html>
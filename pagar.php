<?php
require_once __DIR__ . '/seguridad/proteger_cliente.php';
require_once __DIR__ . '/configuracion/conexion.php';

$tokenCsrf        = generarTokenCsrf();
$documentoCliente = (int) $_SESSION['documento'];
$conexion         = obtenerConexion();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarTokenCsrf($_POST['token_csrf'] ?? null)) {
        $_SESSION['error_reserva'] = 'La sesión del formulario expiró. Intenta nuevamente.';
        header('Location: catalogo.php');
        exit;
    }

    $idCabana          = filter_var($_POST['id_cabana'] ?? '', FILTER_VALIDATE_INT);
    $fechaCheckin      = trim($_POST['fecha_checkin'] ?? '');
    $fechaCheckout     = trim($_POST['fecha_checkout'] ?? '');
    $cantidadHuespedes = max(1, (int) filter_var($_POST['cantidad_huespedes'] ?? 1, FILTER_VALIDATE_INT));

    $huespedesLimpios = [];
    $documentosVistos = [];

    if (!empty($_POST['huespedes']) && is_array($_POST['huespedes'])) {
        foreach ($_POST['huespedes'] as $item) {
            $doc = filter_var($item['documento'] ?? '', FILTER_VALIDATE_INT);
            $nom = trim((string) ($item['nombre'] ?? ''));
            $ape = trim((string) ($item['apellido'] ?? ''));

            if ($doc && $doc > 0 && $nom !== '' && $ape !== '') {
                if (isset($documentosVistos[$doc])) {
                    $_SESSION['error_reserva'] = "El documento {$doc} está duplicado entre los huéspedes.";
                    header('Location: confirmar.php?id_cabana=' . (int) $idCabana);
                    exit;
                }
                $documentosVistos[$doc] = true;
                $huespedesLimpios[] = [
                    'documento' => (int) $doc,
                    'nombre'    => mb_substr($nom, 0, 80),
                    'apellido'  => mb_substr($ape, 0, 80),
                ];
            }
        }
    }

    if (count($huespedesLimpios) < $cantidadHuespedes) {
        $_SESSION['error_reserva'] = 'Completa documento, nombre y apellido de todos los huéspedes.';
        header('Location: confirmar.php?id_cabana=' . (int) $idCabana);
        exit;
    }

    $_SESSION['borrador_reserva'] = [
        'id_cabana'          => $idCabana,
        'fecha_checkin'      => $fechaCheckin,
        'fecha_checkout'     => $fechaCheckout,
        'cantidad_huespedes' => $cantidadHuespedes,
        'huespedes'          => $huespedesLimpios,
    ];
}

$borrador = $_SESSION['borrador_reserva'] ?? null;

if (!$borrador || empty($borrador['id_cabana']) || empty($borrador['fecha_checkin']) || empty($borrador['fecha_checkout'])) {
    header('Location: catalogo.php');
    exit;
}

$idCabana          = (int) $borrador['id_cabana'];
$fechaCheckin      = $borrador['fecha_checkin'];
$fechaCheckout     = $borrador['fecha_checkout'];
$cantidadHuespedes = (int) ($borrador['cantidad_huespedes'] ?? 1);
$huespedesReserva  = $borrador['huespedes'] ?? [];

$objIn  = DateTime::createFromFormat('Y-m-d', $fechaCheckin);
$objOut = DateTime::createFromFormat('Y-m-d', $fechaCheckout);
$hoy    = new DateTime('today');

if (!$objIn || !$objOut || $objIn < $hoy || $objOut <= $objIn) {
    $_SESSION['error_reserva'] = 'Las fechas seleccionadas no son válidas.';
    header('Location: confirmar.php?id_cabana=' . $idCabana);
    exit;
}

// 1. Verificar disponibilidad en MySQL antes de permitir el pago
$consultaCruce = $conexion->prepare(
    'SELECT COUNT(*)
     FROM `reserva` r
     WHERE r.`id_cabaña` = :id_cabana
       AND r.estado <> \'Cancelada\'
       AND r.fecha_checkin < :fecha_checkout
       AND r.fecha_checkout > :fecha_checkin'
);
$consultaCruce->execute([
    ':id_cabana'      => $idCabana,
    ':fecha_checkin'  => $fechaCheckin,
    ':fecha_checkout' => $fechaCheckout,
]);

if ((int) $consultaCruce->fetchColumn() > 0) {
    $_SESSION['error_reserva'] = 'La cabaña ya se encuentra reservada en esas fechas. Elige otro rango.';
    header('Location: confirmar.php?id_cabana=' . $idCabana);
    exit;
}

// 2. Consultar datos reales de la cabaña y su tarifa activa
$consultaCabana = $conexion->prepare(
    'SELECT c.`id_cabaña` AS id_cabana,
            c.nombre,
            t.id_tarifa,
            t.precio_noche,
            f.foto AS foto_principal,
            COALESCE(ROUND(AVG(r.calificacion), 1), 5.0) AS promedio_calificacion,
            COUNT(DISTINCT r.id_resena) AS total_resenas
     FROM `cabaña` c
     INNER JOIN `tarifa` t ON c.`id_cabaña` = t.`id_cabaña` AND t.activa = 1
     LEFT JOIN `cabaña_foto` f ON c.`id_cabaña` = f.`id_cabaña` AND f.id_foto = 1
     LEFT JOIN `reseña` r ON c.`id_cabaña` = r.`id_cabaña`
     WHERE c.`id_cabaña` = :id_cabana
     GROUP BY c.`id_cabaña`, c.nombre, t.id_tarifa, t.precio_noche, f.foto
     LIMIT 1'
);
$consultaCabana->execute([':id_cabana' => $idCabana]);
$cabana = $consultaCabana->fetch();

if (!$cabana) {
    header('Location: catalogo.php');
    exit;
}

$cantidadNoches = (int) $objIn->diff($objOut)->days;
$precioNoche    = (float) $cabana['precio_noche'];
$totalPagar     = $precioNoche * $cantidadNoches;
$rutaImagen     = !empty($cabana['foto_principal'])
    ? 'subidas/cabanas/' . rawurlencode($cabana['foto_principal'])
    : 'img/cabaña1.jpg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CabinNest - Pago Seguro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lora:wght@600;700&family=Roboto+Slab:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/pago.css">
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

            <a href="cuenta.php" id="btn-iniciar">
                <i class="fa-solid fa-user"></i>
                <span>Cuenta</span>
            </a>
        </div>
    </div>

    <div id="confirmar">
        <div class="confirmar-contenedor">

            <div class="titulo">
                <h1>Pago Seguro</h1>
                <p class="subtitulo-pago">Completa tu reserva de forma segura y rápida</p>
            </div>

            <div class="confirmar-div">

                <div id="contenedor-pago" class="tarjeta-pago">
                    <div class="encabezado-pago">
                        <svg class="icono-titulo" viewBox="0 0 24 24" fill="currentColor">
                            <rect x="2" y="5" width="20" height="14" rx="2.5"/>
                            <rect x="2" y="8.5" width="20" height="2.5" fill="#fbf8f3"/>
                            <rect x="5" y="14" width="4" height="1.8" rx="0.5" fill="#fbf8f3"/>
                        </svg>
                        <h2 id="titulo-metodo-pago" class="titulo-pago">Método de Pago</h2>
                    </div>

                    <input type="radio" name="metodo-pago" id="radio-tarjeta" class="radio-oculto" checked>
                    <input type="radio" name="metodo-pago" id="radio-paypal" class="radio-oculto">

                    <div id="lista-metodos" class="grid-metodos">
                        <label for="radio-tarjeta" id="metodo-tarjeta" class="opcion-metodo">
                            <span class="insignia-verificado">✓</span>
                            <div class="icono-metodo">
                                <svg viewBox="0 0 36 26" width="38" height="28">
                                    <rect width="36" height="26" rx="4" fill="#264d2c"/>
                                    <rect y="5" width="36" height="4.5" fill="#e8efe9"/>
                                    <rect x="5" y="15" width="8" height="4" rx="1" fill="#8fb395"/>
                                </svg>
                            </div>
                            <span class="nombre-metodo texto-verde">Tarjeta de Crédito<br>o Débito</span>
                        </label>

                        <label for="radio-paypal" id="metodo-paypal" class="opcion-metodo">
                            <span class="insignia-verificado">✓</span>
                            <div class="icono-metodo">
                                <svg viewBox="0 0 24 28" width="26" height="30">
                                    <path fill="#003087" d="M7.5 24H3.2c-.5 0-.9-.4-.8-.9L5.6 2.8C5.7 2.3 6.1 2 6.6 2h7.8c3.7 0 6.1 1.8 5.5 5.6-.6 3.9-3.3 5.9-7.1 5.9H9.6l-1.3 9.7c-.1.5-.4.8-.8.8z"/>
                                    <path fill="#0079C1" d="M10.4 26H6.6c-.4 0-.8-.4-.7-.8l2.6-16.5c.1-.4.4-.7.9-.7h6.4c3.1 0 5.1 1.5 4.6 4.7-.5 3.3-2.8 5-6 5h-2.7l-1.1 7.6c-.1.4-.4.7-.8.7z" opacity="0.9"/>
                                </svg>
                            </div>
                            <span class="nombre-metodo texto-paypal">PayPal</span>
                        </label>
                    </div>

                    <form id="formulario-tarjeta" class="cuerpo-formulario panel-metodo" action="acciones/registrar_reserva.php" method="POST">
                        <input type="hidden" name="token_csrf" value="<?= limpiarTexto($tokenCsrf) ?>">
                        <input type="hidden" name="id_cabana" value="<?= $idCabana ?>">
                        <input type="hidden" name="fecha_checkin" value="<?= limpiarTexto($fechaCheckin) ?>">
                        <input type="hidden" name="fecha_checkout" value="<?= limpiarTexto($fechaCheckout) ?>">
                        <input type="hidden" name="metodo_pago" id="campo-metodo-pago" value="Tarjeta Crédito">
                        <input type="hidden" name="tipo_pago" value="Pago Total">

                        <?php foreach ($huespedesReserva as $indice => $huesped): ?>
                            <input type="hidden" name="huespedes[<?= (int) $indice ?>][documento]" value="<?= (int) $huesped['documento'] ?>">
                            <input type="hidden" name="huespedes[<?= (int) $indice ?>][nombre]" value="<?= limpiarTexto($huesped['nombre']) ?>">
                            <input type="hidden" name="huespedes[<?= (int) $indice ?>][apellido]" value="<?= limpiarTexto($huesped['apellido']) ?>">
                        <?php endforeach; ?>

                        <div class="fila-tres-columnas">
                            <div class="grupo-campo">
                                <label for="numero-tarjeta" class="etiqueta-campo">Número de Tarjeta</label>
                                <div class="caja-input">
                                    <span class="icono-input">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <rect x="2" y="5" width="20" height="14" rx="2"/>
                                            <line x1="2" y1="10" x2="22" y2="10"/>
                                            <line x1="6" y1="15" x2="10" y2="15"/>
                                        </svg>
                                    </span>
                                    <input type="text" id="numero-tarjeta" class="campo-texto" placeholder="1234 5678 9012 3456" maxlength="19" required>
                                </div>
                            </div>

                            <div class="grupo-campo">
                                <label for="fecha-expiracion" class="etiqueta-campo">Fecha de Expiración</label>
                                <div class="caja-input">
                                    <span class="icono-input">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <rect x="3" y="4" width="18" height="18" rx="2"/>
                                            <line x1="16" y1="2" x2="16" y2="6"/>
                                            <line x1="8" y1="2" x2="8" y2="6"/>
                                            <line x1="3" y1="10" x2="21" y2="10"/>
                                        </svg>
                                    </span>
                                    <input type="text" id="fecha-expiracion" class="campo-texto" placeholder="MM / AA" maxlength="7" required>
                                </div>
                            </div>

                            <div class="grupo-campo">
                                <label for="codigo-cvv" class="etiqueta-campo">CVV</label>
                                <div class="caja-input">
                                    <span class="icono-input">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <rect x="5" y="11" width="14" height="10" rx="2"/>
                                            <path d="M8 11V7a4 4 0 0 1 8 0v4"/>
                                        </svg>
                                    </span>
                                    <input type="text" id="codigo-cvv" class="campo-texto" placeholder="123" maxlength="4" required>
                                </div>
                            </div>
                        </div>

                        <div class="grupo-campo">
                            <label for="nombre-titular" class="etiqueta-campo">Nombre del Titular</label>
                            <div class="caja-input">
                                <span class="icono-input">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <circle cx="12" cy="8" r="4"/>
                                        <path d="M4 20c1.8-3.6 5-5 8-5s6.2 1.4 8 5"/>
                                    </svg>
                                </span>
                                <input type="text" id="nombre-titular" class="campo-texto" placeholder="Como aparece en la tarjeta" required>
                            </div>
                        </div>
                    </form>

                    <div id="panel-paypal" class="caja-paypal panel-metodo">
                        <p class="titulo-paypal-info">Pago rápido con PayPal</p>
                        <p class="descripcion-paypal-info">Al presionar <strong>Confirmar Pago</strong>, tu reserva quedará registrada mediante transferencia digital protegida.</p>
                    </div>

                    <div id="franja-seguridad" class="banner-seguridad">
                        <div class="info-seguridad">
                            <svg class="icono-escudo" viewBox="0 0 24 24" fill="#1e7e34">
                                <path d="M12 2L4 5.5v6.2c0 5.1 3.4 9.8 8 11.3 4.6-1.5 8-6.2 8-11.3V5.5L12 2z"/>
                                <path d="M9.5 12.2l1.8 1.8 3.7-3.8" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <div>
                                <p class="titulo-seguridad">Pago 100% Seguro</p>
                                <p class="subtitulo-seguridad">Tus datos están protegidos con tecnología de encriptación SSL.</p>
                            </div>
                        </div>

                        <div class="logos-tarjetas">
                            <span class="logo-visa">VISA</span>
                            <span class="logo-mastercard">
                                <span class="circulo-mc rojo"></span>
                                <span class="circulo-mc naranja"></span>
                            </span>
                            <span class="logo-amex">AMERICAN<br>EXPRESS</span>
                            <span class="logo-paypal-mini">
                                <svg viewBox="0 0 24 28" width="14" height="16">
                                    <path fill="#003087" d="M7.5 24H3.2c-.5 0-.9-.4-.8-.9L5.6 2.8C5.7 2.3 6.1 2 6.6 2h7.8c3.7 0 6.1 1.8 5.5 5.6-.6 3.9-3.3 5.9-7.1 5.9H9.6l-1.3 9.7c-.1.5-.4.8-.8.8z"/>
                                    <path fill="#0079C1" d="M10.4 26H6.6c-.4 0-.8-.4-.7-.8l2.6-16.5c.1-.4.4-.7.9-.7h6.4c3.1 0 5.1 1.5 4.6 4.7-.5 3.3-2.8 5-6 5h-2.7l-1.1 7.6c-.1.4-.4.7-.8.7z"/>
                                </svg>
                                PayPal
                            </span>
                        </div>
                    </div>

                    <div id="acciones-formulario" class="barra-botones">
                        <a href="confirmar.php?id_cabana=<?= $idCabana ?>" id="boton-volver" class="btn-volver">
                            Cancelar
                        </a>

                        <button type="submit" form="formulario-tarjeta" id="boton-confirmar" class="btn-confirmar">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                <path d="M17 9h-1V7a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2zm-7-2a2 2 0 1 1 4 0v2h-4V7zm2 9a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3z"/>
                            </svg>
                            <span>Confirmar Pago</span>
                            <span>→</span>
                        </button>
                    </div>
                </div>

                <div class="cabaña">
                    <div class="cabana-imagen-wrapper">
                        <img src="<?= limpiarTexto($rutaImagen) ?>">
                        <span class="cabana-etiqueta">
                            <i class="fa-solid fa-house-chimney"></i> Resumen de Reserva
                        </span>
                    </div>

                    <div class="cabana-contenido">
                        <div class="cabana-encabezado">
                            <h3><?= limpiarTexto($cabana['nombre']) ?></h3>
                            <div class="puntuacion">
                                <i class="fa-solid fa-star"></i>
                                <p class="reseñas"><?= number_format((float) $cabana['promedio_calificacion'], 1) ?> <span>(<?= (int) $cabana['total_resenas'] ?> reseñas)</span></p>
                            </div>
                        </div>

                        <div class="fechas-div">
                            <div class="fechas-icono">
                                <i class="fa-regular fa-calendar-days"></i>
                            </div>
                            <div class="fechas">
                                <span class="fechas-etiqueta">Fechas de estadía</span>
                                <p id="fechas-reservadas"><?= $objIn->format('d/m/Y') ?> - <?= $objOut->format('d/m/Y') ?></p>
                            </div>
                            <span class="badge-huespedes">
                                <i class="fa-solid fa-user-group"></i> <?= $cantidadHuespedes ?> <?= $cantidadHuespedes === 1 ? 'Persona' : 'Personas' ?>
                            </span>
                        </div>

                        <div class="desglose-tarifa">
                            <div class="fila-tarifa">
                                <span>$<?= number_format($precioNoche, 0) ?> × <?= $cantidadNoches ?> <?= $cantidadNoches === 1 ? 'noche' : 'noches' ?></span>
                                <span>$<?= number_format($totalPagar, 0) ?></span>
                            </div>
                            <div class="fila-tarifa">
                                <span>Impuestos y limpieza</span>
                                <span class="texto-incluido">Incluidos</span>
                            </div>
                        </div>

                        <div class="total">
                            <div class="total-info">
                                <span class="total-etiqueta">Total a pagar</span>
                                <small>USD</small>
                            </div>
                            <p class="total-monto">$<?= number_format($totalPagar, 0) ?></p>
                        </div>

                        <p class="nota-cancelacion">
                            <i class="fa-solid fa-circle-check"></i> Cancelación gratuita hasta 48 h antes
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        const radioTarjeta     = document.getElementById("radio-tarjeta");
        const radioPaypal      = document.getElementById("radio-paypal");
        const campoMetodoPago  = document.getElementById("campo-metodo-pago");
        const camposTarjeta    = document.querySelectorAll("#formulario-tarjeta .campo-texto");

        function actualizarMetodoSeleccionado() {
            const usaTarjeta = radioTarjeta.checked;
            campoMetodoPago.value = usaTarjeta ? "Tarjeta Crédito" : "Transferencia";

            camposTarjeta.forEach((campo) => {
                campo.required = usaTarjeta;
            });
        }

        radioTarjeta.addEventListener("change", actualizarMetodoSeleccionado);
        radioPaypal.addEventListener("change", actualizarMetodoSeleccionado);
    </script>

</body>
</html>
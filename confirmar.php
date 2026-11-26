<?php
require_once __DIR__ . '/seguridad/proteger_cliente.php';
require_once __DIR__ . '/configuracion/conexion.php';

$tokenCsrf        = generarTokenCsrf();
$documentoCliente = (int) $_SESSION['documento'];
$conexion         = obtenerConexion();

$idCabana = filter_var(
    $_GET['id_cabana'] ?? ($_SESSION['borrador_reserva']['id_cabana'] ?? ''),
    FILTER_VALIDATE_INT
);

if (!$idCabana) {
    header('Location: catalogo.php');
    exit;
}

// 1. Obtener datos de la cabaña, tarifa activa y calificación
$consultaCabana = $conexion->prepare(
    'SELECT c.`id_cabaña` AS id_cabana,
            c.nombre,
            c.capacidad,
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
     GROUP BY c.`id_cabaña`, c.nombre, c.capacidad, t.id_tarifa, t.precio_noche, f.foto
     LIMIT 1'
);
$consultaCabana->execute([':id_cabana' => $idCabana]);
$cabana = $consultaCabana->fetch();

if (!$cabana) {
    $_SESSION['error_reserva'] = 'La cabaña seleccionada no está disponible en este momento.';
    header('Location: catalogo.php');
    exit;
}

// 2. Obtener documento, nombre y apellido del usuario autenticado desde `usuario`
$consultaUsuario = $conexion->prepare(
    'SELECT documento, nombre, apellido FROM `usuario` WHERE documento = :documento LIMIT 1'
);
$consultaUsuario->execute([':documento' => $documentoCliente]);
$datosHuesped = $consultaUsuario->fetch();

// 3. Obtener fechas y cantidad de huéspedes enviados desde reserva.php
$manana        = date('Y-m-d', strtotime('+1 day'));
$pasado3Dias   = date('Y-m-d', strtotime('+4 days'));
$fechaCheckin  = $_GET['checkin'] ?? ($_SESSION['borrador_reserva']['fecha_checkin'] ?? $manana);
$fechaCheckout = $_GET['checkout'] ?? ($_SESSION['borrador_reserva']['fecha_checkout'] ?? $pasado3Dias);

$objIn  = DateTime::createFromFormat('Y-m-d', $fechaCheckin);
$objOut = DateTime::createFromFormat('Y-m-d', $fechaCheckout);
$hoy    = new DateTime('today');

if (!$objIn || !$objOut || $objIn < $hoy || $objOut <= $objIn) {
    $fechaCheckin  = $manana;
    $fechaCheckout = $pasado3Dias;
    $objIn         = new DateTime($fechaCheckin);
    $objOut        = new DateTime($fechaCheckout);
}

$cantidadNoches      = (int) $objIn->diff($objOut)->days;
$precioNoche         = (float) $cabana['precio_noche'];
$totalInicial        = $precioNoche * $cantidadNoches;
$capacidadMax        = max(1, (int) $cabana['capacidad']);
$huespedesSolicitado = filter_var(
    $_GET['huespedes'] ?? ($_SESSION['borrador_reserva']['cantidad_huespedes'] ?? 1),
    FILTER_VALIDATE_INT
);
$huespedesInit       = max(1, min($capacidadMax, $huespedesSolicitado ?: 1));
$huespedesBorrador   = array_values($_SESSION['borrador_reserva']['huespedes'] ?? []);

$rutaImagen = !empty($cabana['foto_principal'])
    ? 'subidas/cabanas/' . rawurlencode($cabana['foto_principal'])
    : 'img/cabaña1.jpg';

$mensajeError = $_SESSION['error_reserva'] ?? '';
unset($_SESSION['error_reserva']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CabinNest - Confirmar Reserva</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lora:wght@600;700&family=Roboto+Slab:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/confirmar.css">
    <script src="https://kit.fontawesome.com/3d84e79734.js" crossorigin="anonymous"></script>
    <link rel="icon" href="img/icono.png" type="image/png">

    <style>
        .campo-bloqueado {
            background-color: #f4efe8 !important;
            color: #4f4a44;
            cursor: not-allowed;
            pointer-events: none;
        }

        .bloque-huesped-adicional {
            background: #fbf8f3;
            border-radius: 20px;
            padding: 26px 30px;
            margin-top: 20px;
            box-shadow: 0 10px 30px rgba(48, 64, 38, 0.08);
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

            <a href="cuenta.php" id="btn-iniciar">
                <i class="fa-solid fa-user"></i>
                <span>Cuenta</span>
            </a>
        </div>
    </div>

    <div id="confirmar">
        <div class="confirmar-div">

            <div class="registrar">
                <div class="registrar-header">
                    <h1 id="titulo-confirmar">Completa tu Reserva</h1>
                    <p class="subtitulo-confirmar">Verifica tus datos, las fechas y registra los acompañantes antes de continuar al pago</p>
                </div>

                <?php if ($mensajeError !== ''): ?>
                    <p style="margin-bottom: 12px; background: #fbeae6; color: #c84b31; border: 1px solid rgba(200, 75, 49, 0.35); border-radius: 10px; padding: 9px 12px; font-family: 'Inter', sans-serif; font-size: 13px; font-weight: 600;">
                        <i class="fa-solid fa-triangle-exclamation"></i> <?= limpiarTexto($mensajeError) ?>
                    </p>
                <?php endif; ?>

                <form id="form-reserva" action="pagar.php" method="POST">
                    <input type="hidden" name="token_csrf" value="<?= limpiarTexto($tokenCsrf) ?>">
                    <input type="hidden" name="id_cabana" value="<?= $idCabana ?>">

                    <div id="informacion-huesped">
                        <div class="huesped-titulo">
                            <i class="fa-solid fa-user"></i>
                            <h2>Información del Huésped y Estadía</h2>
                        </div>

                        <div class="formulario">
                            <div class="campo">
                                <label for="documento-titular">Documento</label>
                                <input type="text" id="documento-titular" class="campo-bloqueado" name="huespedes[1][documento]" value="<?= limpiarTexto((string) ($datosHuesped['documento'] ?? $documentoCliente)) ?>" readonly tabindex="-1">
                            </div>

                            <div class="campo">
                                <label for="nombre-titular">Nombre</label>
                                <input type="text" id="nombre-titular" class="campo-bloqueado" name="huespedes[1][nombre]" value="<?= limpiarTexto($datosHuesped['nombre'] ?? '') ?>" readonly tabindex="-1">
                            </div>

                            <div class="campo">
                                <label for="apellido-titular">Apellido</label>
                                <input type="text" id="apellido-titular" class="campo-bloqueado" name="huespedes[1][apellido]" value="<?= limpiarTexto($datosHuesped['apellido'] ?? '') ?>" readonly tabindex="-1">
                            </div>

                            <div class="campo">
                                <label for="cantidad-huespedes">Cantidad de Huéspedes</label>
                                <input type="number" id="cantidad-huespedes" class="campo-bloqueado" name="cantidad_huespedes" value="<?= $huespedesInit ?>" readonly tabindex="-1">
                            </div>

                            <div class="campo">
                                <label for="fecha-checkin">Fecha de Entrada (Check-in)</label>
                                <input type="date" id="fecha-checkin" class="campo-bloqueado" name="fecha_checkin" value="<?= limpiarTexto($fechaCheckin) ?>" readonly tabindex="-1">
                            </div>

                            <div class="campo">
                                <label for="fecha-checkout">Fecha de Salida (Check-out)</label>
                                <input type="date" id="fecha-checkout" class="campo-bloqueado" name="fecha_checkout" value="<?= limpiarTexto($fechaCheckout) ?>" readonly tabindex="-1">
                            </div>
                        </div>
                    </div>

                    <?php for ($numHuesped = 2; $numHuesped <= $huespedesInit; $numHuesped++):
                        $previo = $huespedesBorrador[$numHuesped - 1] ?? [];
                    ?>
                        <div class="bloque-huesped-adicional">
                            <div class="huesped-titulo">
                                <i class="fa-solid fa-user-plus"></i>
                                <h2>Información del Huésped <?= $numHuesped ?></h2>
                            </div>

                            <div class="formulario">
                                <div class="campo">
                                    <label for="huesped-<?= $numHuesped ?>-documento">Documento</label>
                                    <input type="number"
                                           id="huesped-<?= $numHuesped ?>-documento"
                                           name="huespedes[<?= $numHuesped ?>][documento]"
                                           min="1"
                                           value="<?= limpiarTexto((string) ($previo['documento'] ?? '')) ?>"
                                           placeholder="Ingresa el documento"
                                           required>
                                </div>

                                <div class="campo">
                                    <label for="huesped-<?= $numHuesped ?>-nombre">Nombre</label>
                                    <input type="text"
                                           id="huesped-<?= $numHuesped ?>-nombre"
                                           name="huespedes[<?= $numHuesped ?>][nombre]"
                                           maxlength="80"
                                           value="<?= limpiarTexto((string) ($previo['nombre'] ?? '')) ?>"
                                           placeholder="Ingresa el nombre"
                                           required>
                                </div>

                                <div class="campo">
                                    <label for="huesped-<?= $numHuesped ?>-apellido">Apellido</label>
                                    <input type="text"
                                           id="huesped-<?= $numHuesped ?>-apellido"
                                           name="huespedes[<?= $numHuesped ?>][apellido]"
                                           maxlength="80"
                                           value="<?= limpiarTexto((string) ($previo['apellido'] ?? '')) ?>"
                                           placeholder="Ingresa el apellido"
                                           required>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </form>
            </div>

            <div class="cabaña">
                <div class="cabana-imagen-wrapper">
                    <img src="<?= limpiarTexto($rutaImagen) ?>">
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
                        <span class="badge-huespedes" id="resumen-huespedes">
                            <i class="fa-solid fa-user-group"></i> <?= $huespedesInit ?> <?= $huespedesInit === 1 ? 'Persona' : 'Personas' ?>
                        </span>
                    </div>

                    <div class="total">
                        <div class="total-desglose">
                            <span class="total-etiqueta">Total a pagar</span>
                            <p id="desglose-noches">$<?= number_format($precioNoche, 0) ?> × <?= $cantidadNoches ?> <?= $cantidadNoches === 1 ? 'noche' : 'noches' ?></p>
                        </div>
                        <p class="total-monto" id="monto-total">$<?= number_format($totalInicial, 0) ?></p>
                    </div>

                    <button type="submit" form="form-reserva" id="confirmar-btn">
                        <i class="fa-regular fa-calendar-check"></i>
                        <span>Continuar al Pago</span>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <script>
        document.getElementById("form-reserva").addEventListener("submit", (evento) => {
            const inputsDoc = document.querySelectorAll('input[name$="[documento]"]');
            const vistos = new Set();

            for (const input of inputsDoc) {
                const doc = input.value.trim();
                if (vistos.has(doc)) {
                    evento.preventDefault();
                    alert(`El documento ${doc} está repetido. Cada huésped debe tener un documento único.`);
                    input.focus();
                    return;
                }
                vistos.add(doc);
            }
        });
    </script>

</body>
</html>
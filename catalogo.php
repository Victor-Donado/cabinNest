<?php
require_once __DIR__ . '/seguridad/sesion.php';
require_once __DIR__ . '/configuracion/conexion.php';

iniciarSesionSegura();

$estaAutenticado = !empty($_SESSION['documento']) && (($_SESSION['tipo_cuenta'] ?? '') === 'huesped');
$conexion        = obtenerConexion();

$consultaCabanas = $conexion->query(
    'SELECT c.`id_cabaña` AS id_cabana,
            c.nombre,
            c.capacidad,
            c.descripcion,
            c.ocupado,
            t.id_tarifa,
            t.precio_noche,
            f.foto AS foto_principal,
            COALESCE(ROUND(AVG(r.calificacion), 1), 5.0) AS promedio_calificacion,
            COUNT(DISTINCT r.id_resena) AS total_resenas
     FROM `cabaña` c
     INNER JOIN `tarifa` t ON c.`id_cabaña` = t.`id_cabaña` AND t.activa = 1
     LEFT JOIN `cabaña_foto` f ON c.`id_cabaña` = f.`id_cabaña` AND f.id_foto = 1
     LEFT JOIN `reseña` r ON c.`id_cabaña` = r.`id_cabaña`
     GROUP BY c.`id_cabaña`, c.nombre, c.capacidad, c.descripcion, c.ocupado,
              t.id_tarifa, t.precio_noche, f.foto
     ORDER BY t.precio_noche ASC'
);
$listaCabanas = $consultaCabanas->fetchAll();

$mensajeError = $_SESSION['error_reserva'] ?? '';
unset($_SESSION['error_reserva']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CabinNest - Catálogo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lora:wght@600;700&family=Roboto+Slab:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/catalogo.css">
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
                <a href="catalogo.php" class="division-btn a-activo">
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

            <?php if ($estaAutenticado): ?>
                <a href="cuenta.php" id="btn-iniciar">
                    <i class="fa-solid fa-user"></i>
                    <span>Mi Cuenta</span>
                </a>
            <?php else: ?>
                <a href="login.php" id="btn-iniciar">
                    <i class="fa-solid fa-user"></i>
                    <span>Iniciar Sesión</span>
                </a>
            <?php endif; ?>
        </div>

        <div class="cabeza-titulos">
            <h1>Catálogo de Cabañas</h1>
            <p class="cabeza-subtitulo">Encuentra la cabaña perfecta para tu próxima aventura</p>
        </div>
    </div>

    <div id="catalogo">

        <div id="filtro">
            <div id="filtro-titulo">
                <i class="fa-solid fa-filter"></i>
                <span>Filtros</span>
                <i class="fa-solid fa-chevron-down flecha-filtro"></i>
            </div>

            <div id="filtro-contenido">
                <div class="filtro-inner">
                    <div class="filtro-seccion">
                        <h2 id="rango-titulo" class="filtro-subtitulo">
                            <i class="fa-solid fa-tag"></i>
                            <span>Rango de precio</span>
                        </h2>

                        <div class="range-slider-container">
                            <div class="range-values-top">
                                <span id="current-range">$50 - $500</span>
                            </div>

                            <div class="slider-wrapper">
                                <div class="slider-track"></div>
                                <div class="slider-fill" id="slider-fill"></div>
                                <input type="range" min="0" max="1000" value="50" id="slider-1">
                                <input type="range" min="0" max="1000" value="500" id="slider-2">
                            </div>

                            <div class="range-labels-bottom">
                                <span>$0</span>
                                <span>$1000</span>
                            </div>
                        </div>
                    </div>

                    <div class="filtro-seccion">
                        <h2 id="huespedes-titulo" class="filtro-subtitulo filtro-separador">
                            <i class="fa-solid fa-users"></i>
                            <span>Capacidad</span>
                        </h2>

                        <form class="grupo-personas" id="form-capacidad">
                            <label class="opcion">
                                <input type="radio" name="personas" value="1-2">
                                <span>1 – 2 personas</span>
                            </label>
                            <label class="opcion">
                                <input type="radio" name="personas" value="3-4">
                                <span>3 – 4 personas</span>
                            </label>
                            <label class="opcion">
                                <input type="radio" name="personas" value="5-6">
                                <span>5 – 6 personas</span>
                            </label>
                            <label class="opcion">
                                <input type="radio" name="personas" value="7+">
                                <span>7+ personas</span>
                            </label>
                        </form>
                    </div>

                    <div class="filtro-seccion">
                        <h2 id="fecha-titulo" class="filtro-subtitulo filtro-separador">
                            <i class="fa-regular fa-calendar-days"></i>
                            <span>Fechas</span>
                        </h2>

                        <div id="fechas">
                            <div id="checkin" class="campo-fecha">
                                <label for="date-in">Check-in</label>
                                <input type="date" id="date-in" name="checkin" min="<?= date('Y-m-d') ?>">
                            </div>
                            <div id="checkout" class="campo-fecha">
                                <label for="date-out">Check-out</label>
                                <input type="date" id="date-out" name="checkout" min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                            </div>
                        </div>
                    </div>

                    <div id="filtro-btn">
                        <button type="button" id="btn-buscar" class="btn-filtro">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <span>Buscar</span>
                        </button>
                        <button type="button" id="btn-limpiar" class="btn-filtro">
                            <i class="fa-solid fa-rotate"></i>
                            <span>Limpiar</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="cabañas">
            <?php if ($mensajeError !== ''): ?>
                <p style="margin-bottom: 12px; background: #fbeae6; color: #c84b31; border: 1px solid rgba(200, 75, 49, 0.35); border-radius: 10px; padding: 10px 14px; font-family: 'Inter', sans-serif; font-size: 13.5px; font-weight: 600;">
                    <i class="fa-solid fa-triangle-exclamation"></i> <?= limpiarTexto($mensajeError) ?>
                </p>
            <?php endif; ?>

            <div id="cabañas-cabeza">
                <p id="cabañas-resultados">
                    Resultados: <span id="resultados"><?= count($listaCabanas) ?></span> cabañas encontradas
                </p>

                <div id="ordenar">
                    <label for="ordernar-por">Ordenar por:</label>
                    <select id="ordernar-por" name="ordenar-por">
                        <option value="1">Más Populares</option>
                        <option value="2" selected>Más Baratos</option>
                        <option value="3">Más Caros</option>
                    </select>
                </div>
            </div>

            <div id="cajas">
                <?php if (empty($listaCabanas)): ?>
                    <p style="grid-column: 1 / -1; text-align: center; padding: 30px; font-family: 'Inter', sans-serif; color: #6b655e;">
                        Por el momento no hay cabañas con tarifa activa disponibles.
                    </p>
                <?php endif; ?>

                <?php foreach ($listaCabanas as $cabana):
                    $idCabana    = (int) $cabana['id_cabana'];
                    $precioNoche = (float) $cabana['precio_noche'];
                    $capacidad   = (int) $cabana['capacidad'];
                    $calificacion = number_format((float) $cabana['promedio_calificacion'], 1);
                    $totalResenas = (int) $cabana['total_resenas'];
                    $rutaImagen  = !empty($cabana['foto_principal'])
                        ? 'subidas/cabanas/' . rawurlencode($cabana['foto_principal'])
                        : 'img/cabaña1.jpg';
                ?>
                    <div class="cards"
                         data-id-cabana="<?= $idCabana ?>"
                         data-precio="<?= $precioNoche ?>"
                         data-capacidad="<?= $capacidad ?>"
                         data-resenas="<?= $totalResenas ?>">
                        <img src="<?= limpiarTexto($rutaImagen) ?>">
                        <h3 class="titulo-cabaña"><?= limpiarTexto($cabana['nombre']) ?></h3>
                        <div class="puntuacion">
                            <i class="fa-solid fa-star"></i>
                            <p class="reseñas"><?= $calificacion ?> (<?= $totalResenas ?> reseñas)</p>
                        </div>
                        <div class="footer">
                            <p class="precio">$<?= number_format($precioNoche, 0) ?> noche</p>
                            <button type="button" class="btn-reservar" data-id-cabana="<?= $idCabana ?>">
                                <i class="fa-regular fa-calendar-days"></i>
                                <span>Reservar</span>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <script>
        const sesionIniciada = <?= json_encode($estaAutenticado) ?>;

        // 1. Control deslizante de rango de precios
        const deslizadorMinimo = document.getElementById("slider-1");
        const deslizadorMaximo = document.getElementById("slider-2");
        const rellenoRango     = document.getElementById("slider-fill");
        const textoRangoActual = document.getElementById("current-range");
        const separacionMinima = 10;

        function actualizarDeslizador() {
            let valor1 = parseInt(deslizadorMinimo.value, 10);
            let valor2 = parseInt(deslizadorMaximo.value, 10);

            if (valor2 - valor1 <= separacionMinima) {
                if (this && this.id === "slider-1") {
                    deslizadorMinimo.value = valor2 - separacionMinima;
                    valor1 = parseInt(deslizadorMinimo.value, 10);
                } else {
                    deslizadorMaximo.value = valor1 + separacionMinima;
                    valor2 = parseInt(deslizadorMaximo.value, 10);
                }
            }

            textoRangoActual.textContent = `$${valor1} - $${valor2}`;

            const maximo = parseInt(deslizadorMinimo.max, 10);
            const minimo = parseInt(deslizadorMinimo.min, 10);
            const porcentaje1 = ((valor1 - minimo) / (maximo - minimo)) * 100;
            const porcentaje2 = ((valor2 - minimo) / (maximo - minimo)) * 100;

            rellenoRango.style.left = `${porcentaje1}%`;
            rellenoRango.style.width = `${porcentaje2 - porcentaje1}%`;
        }

        deslizadorMinimo.addEventListener("input", actualizarDeslizador);
        deslizadorMaximo.addEventListener("input", actualizarDeslizador);
        actualizarDeslizador();

        // 2. Filtro desplegable en tablets y teléfonos
        const panelFiltro  = document.getElementById("filtro");
        const tituloFiltro = document.getElementById("filtro-titulo");

        function alternarFiltro() {
            if (window.innerWidth <= 900) {
                const abierto = panelFiltro.classList.toggle("desplegado");
                tituloFiltro.setAttribute("aria-expanded", abierto);
            }
        }

        tituloFiltro.addEventListener("click", alternarFiltro);
        tituloFiltro.addEventListener("keydown", (evento) => {
            if (evento.key === "Enter" || evento.key === " ") {
                evento.preventDefault();
                alternarFiltro();
            }
        });

        // 3. Filtrado y ordenamiento de cabañas
        const contenedorTarjetas = document.getElementById("cajas");
        const contadorResultados = document.getElementById("resultados");
        const selectorOrden      = document.getElementById("ordernar-por");

        function aplicarFiltros() {
            const precioMin = parseFloat(deslizadorMinimo.value);
            const precioMax = parseFloat(deslizadorMaximo.value);
            const opcionCapacidad = document.querySelector('input[name="personas"]:checked');
            const rangoCapacidad = opcionCapacidad ? opcionCapacidad.value : "";
            const tarjetas = Array.from(contenedorTarjetas.querySelectorAll(".cards"));
            let visibles = 0;

            tarjetas.forEach((tarjeta) => {
                const precio = parseFloat(tarjeta.dataset.precio);
                const capacidad = parseInt(tarjeta.dataset.capacidad, 10);

                let cumplePrecio = precio >= precioMin && precio <= precioMax;
                let cumpleCapacidad = true;

                if (rangoCapacidad === "1-2") cumpleCapacidad = capacidad >= 1 && capacidad <= 2;
                else if (rangoCapacidad === "3-4") cumpleCapacidad = capacidad >= 3 && capacidad <= 4;
                else if (rangoCapacidad === "5-6") cumpleCapacidad = capacidad >= 5 && capacidad <= 6;
                else if (rangoCapacidad === "7+") cumpleCapacidad = capacidad >= 7;

                const mostrar = cumplePrecio && cumpleCapacidad;
                tarjeta.style.display = mostrar ? "" : "none";
                if (mostrar) visibles++;
            });

            contadorResultados.textContent = visibles;
        }

        document.getElementById("btn-buscar").addEventListener("click", aplicarFiltros);

        document.getElementById("btn-limpiar").addEventListener("click", () => {
            deslizadorMinimo.value = 50;
            deslizadorMaximo.value = 500;
            actualizarDeslizador();
            document.getElementById("form-capacidad").reset();
            document.getElementById("date-in").value = "";
            document.getElementById("date-out").value = "";
            aplicarFiltros();
        });

        selectorOrden.addEventListener("change", () => {
            const criterio = selectorOrden.value;
            const tarjetas = Array.from(contenedorTarjetas.querySelectorAll(".cards"));

            tarjetas.sort((a, b) => {
                const precioA = parseFloat(a.dataset.precio);
                const precioB = parseFloat(b.dataset.precio);
                const resenasA = parseInt(a.dataset.resenas, 10);
                const resenasB = parseInt(b.dataset.resenas, 10);

                if (criterio === "1") return resenasB - resenasA;
                if (criterio === "3") return precioB - precioA;
                return precioA - precioB;
            });

            tarjetas.forEach((tarjeta) => contenedorTarjetas.appendChild(tarjeta));
        });

        // 4. Botón Reservar: abre el detalle y calendario de la cabaña
        contenedorTarjetas.addEventListener("click", (evento) => {
            const botonReservar = evento.target.closest(".btn-reservar");
            if (!botonReservar) return;

            const idCabana = botonReservar.dataset.idCabana;
            const fechaEntrada = document.getElementById("date-in").value;
            const fechaSalida  = document.getElementById("date-out").value;

            const parametros = new URLSearchParams({ id_cabana: idCabana });
            if (fechaEntrada) parametros.set("checkin", fechaEntrada);
            if (fechaSalida) parametros.set("checkout", fechaSalida);

            window.location.href = `reserva.php?${parametros.toString()}`;
        });
    </script>
</body>
</html>
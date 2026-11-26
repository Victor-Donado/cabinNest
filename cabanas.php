<?php
require_once __DIR__ . '/seguridad/proteger_admin.php';
require_once __DIR__ . '/configuracion/conexion.php';

$tokenCsrf    = generarTokenCsrf();
$mensajeExito = $_SESSION['exito_cabana'] ?? '';
$mensajeError = $_SESSION['error_cabana'] ?? '';
unset($_SESSION['exito_cabana'], $_SESSION['error_cabana']);

$conexion = obtenerConexion();

// 1. Consultar todas las cabañas junto con su fotografía principal (id_foto = 1)
$consultaCabanas = $conexion->query(
    'SELECT c.`id_cabaña` AS id_cabana, c.nombre, c.capacidad, c.descripcion, c.ocupado,
            f.foto AS foto_principal
     FROM `cabaña` c
     LEFT JOIN `cabaña_foto` f ON c.`id_cabaña` = f.`id_cabaña` AND f.id_foto = 1
     ORDER BY c.`id_cabaña` DESC'
);
$listaCabanas = $consultaCabanas->fetchAll();

// 2. Preparar consultas auxiliares por cabaña (tarifas, inventario y huésped activo si está ocupada)
$consultaTarifas = $conexion->prepare(
    'SELECT id_tarifa, temporada, precio_noche, activa
     FROM `tarifa`
     WHERE `id_cabaña` = :id_cabana
     ORDER BY activa DESC, precio_noche ASC'
);

$consultaInventario = $conexion->prepare(
    'SELECT nombre_elemento, cantidad, estado
     FROM `inventario`
     WHERE `id_cabaña` = :id_cabana
     ORDER BY id_inventario ASC'
);

$consultaHuespedActivo = $conexion->prepare(
    'SELECT u.nombre, u.apellido, u.correo,
            (SELECT COUNT(*) FROM `hospedaje` h WHERE h.id_reserva = r.id_reserva) AS cantidad_huespedes
     FROM `reserva` r
     INNER JOIN `usuario` u ON r.id_cliente = u.documento
     WHERE r.`id_cabaña` = :id_cabana
       AND r.estado IN (\'Confirmada\', \'En curso\', \'Activa\')
     ORDER BY r.fecha_checkin DESC
     LIMIT 1'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CabinNest - Cabañas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lora:wght@600;700&family=Roboto+Slab:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/cabanas.css">
    <script src="https://kit.fontawesome.com/3d84e79734.js" crossorigin="anonymous"></script>
    <link rel="icon" href="img/icono.png" type="image/png">
</head>
<body>

    <input type="checkbox" id="menu-toggle" class="menu-checkbox">

    <div class="menu">
        <div class="menu-movil-top">
            <span>Menú</span>
            <label for="menu-toggle" class="btn-cerrar-menu">
                <i class="fa-solid fa-xmark"></i>
            </label>
        </div>

        <div class="img-cabeza">
            <a href="index.php">
                <img class="icono-menu" src="img/icono-largo.png">
            </a>
        </div>

        <div class="botones">
            <a href="dashboard.php">
                <i class="fa-solid fa-chess-board"></i>
                <span>DashBoard</span>
            </a>
            <a href="reservas.php">
                <i class="fa-regular fa-calendar-days"></i>
                <span>Reservas</span>
            </a>
            <a href="pagos.php">
                <i class="fa-solid fa-credit-card"></i>
                <span>Pagos</span>
            </a>
            <a class="activo" href="cabanas.php">
                <i class="fa-solid fa-campground"></i>
                <span>Cabañas</span>
            </a>
            <a href="usuarios.php">
                <i class="fa-solid fa-users"></i>
                <span>Usuarios</span>
            </a>
        </div>

        <img class="img-footer" src="img/menu-admin.jpg">
    </div>

    <label for="menu-toggle" class="menu-overlay"></label>

    <div class="dashboard">

        <div class="cabeza-dashboard">
            <label for="menu-toggle" class="btn-hamburguesa">
                <span></span>
                <span></span>
                <span></span>
            </label>

            <div class="cabeza-txt">
                <h1>Gestión de Cabañas</h1>
                <p>Administra disponibilidad, tarifas activas e inventario</p>
            </div>

            <a href="acciones/cerrar_sesion.php" id="cerrar-sesion">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Cerrar Sesión</span>
            </a>
        </div>

        <div class="dashboard-contenido">
            <div class="cabanas-contenedor">

                <div class="cabanas-barra">
                    <div class="cabanas-titulo">
                        <i class="fa-solid fa-campground"></i>
                        <h2 id="titulo-lista-cabanas">Cabañas Registradas</h2>
                        <span id="contador-cabanas" class="contador-cabanas">
                            <?= count($listaCabanas) ?> <?= count($listaCabanas) === 1 ? 'cabaña' : 'cabañas' ?>
                        </span>
                    </div>

                    <div class="cabanas-controles">
                        <div class="buscador-box">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="search" id="buscador-cabanas" placeholder="Buscar cabaña, estado o huésped...">
                        </div>

                        <button type="button" id="btn-abrir-nueva" class="btn-agregar-cabana" title="Agregar nueva cabaña">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                </div>

                <?php if ($mensajeExito !== ''): ?>
                    <p style="margin-top: 10px; background: #e9f0e4; color: #304026; border: 1px solid rgba(48, 64, 38, 0.3); border-radius: 10px; padding: 8px 14px; font-family: 'Inter', sans-serif; font-size: 13px; font-weight: 600;">
                        <i class="fa-solid fa-circle-check"></i> <?= limpiarTexto($mensajeExito) ?>
                    </p>
                <?php endif; ?>

                <?php if ($mensajeError !== ''): ?>
                    <p style="margin-top: 10px; background: #fbeae6; color: #c84b31; border: 1px solid rgba(200, 75, 49, 0.35); border-radius: 10px; padding: 8px 14px; font-family: 'Inter', sans-serif; font-size: 13px; font-weight: 600;">
                        <i class="fa-solid fa-triangle-exclamation"></i> <?= limpiarTexto($mensajeError) ?>
                    </p>
                <?php endif; ?>

                <div id="lista-cabanas" class="lista-cabanas">

                    <?php if (empty($listaCabanas)): ?>
                        <div class="sin-resultados">
                            <i class="fa-solid fa-campground"></i>
                            <p>Aún no hay cabañas registradas en la base de datos. Haz clic en el botón <strong>+</strong> para registrar la primera.</p>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($listaCabanas as $cabana):
                        $idCabana    = (int) $cabana['id_cabana'];
                        $estaOcupada = (bool) $cabana['ocupado'];
                        $rutaImagen  = !empty($cabana['foto_principal'])
                            ? 'subidas/cabanas/' . rawurlencode($cabana['foto_principal'])
                            : 'img/cabaña1.jpg';

                        $consultaTarifas->execute([':id_cabana' => $idCabana]);
                        $tarifasCabana = $consultaTarifas->fetchAll();

                        $consultaInventario->execute([':id_cabana' => $idCabana]);
                        $inventarioCabana = $consultaInventario->fetchAll();

                        $huespedActivo = false;
                        if ($estaOcupada) {
                            $consultaHuespedActivo->execute([':id_cabana' => $idCabana]);
                            $huespedActivo = $consultaHuespedActivo->fetch();
                        }
                    ?>
                        <div class="cabana-card" data-id-cabana="<?= $idCabana ?>" data-nombre-cabana="<?= limpiarTexto($cabana['nombre']) ?>">
                            <div class="cabana-foto-box">
                                <img src="<?= limpiarTexto($rutaImagen) ?>">
                                <?php if ($estaOcupada): ?>
                                    <span class="estado-pill ocupada">
                                        <i class="fa-solid fa-user-lock"></i> Ocupada
                                    </span>
                                <?php else: ?>
                                    <span class="estado-pill desocupada">
                                        <i class="fa-solid fa-circle-check"></i> Desocupada
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="cabana-info">
                                <div class="cabana-top">
                                    <h3><?= limpiarTexto($cabana['nombre']) ?></h3>
                                    <span class="capacidad-tag"><i class="fa-solid fa-users"></i> Máx. <?= (int) $cabana['capacidad'] ?></span>
                                </div>

                                <?php if ($estaOcupada && $huespedActivo): ?>
                                    <div class="huesped-box">
                                        <div class="huesped-fila">
                                            <i class="fa-solid fa-user"></i>
                                            <strong><?= limpiarTexto($huespedActivo['nombre'] . ' ' . $huespedActivo['apellido']) ?></strong>
                                            <span class="huesped-cant">• <?= max(1, (int) $huespedActivo['cantidad_huespedes']) ?> huéspedes</span>
                                        </div>
                                        <div class="huesped-correo">
                                            <i class="fa-regular fa-envelope"></i>
                                            <span><?= limpiarTexto($huespedActivo['correo'] ?? 'Sin correo registrado') ?></span>
                                        </div>
                                    </div>
                                <?php elseif ($estaOcupada): ?>
                                    <div class="huesped-box">
                                        <div class="huesped-fila">
                                            <i class="fa-solid fa-user-lock"></i>
                                            <strong>Cabaña marcada como ocupada</strong>
                                        </div>
                                        <div class="huesped-correo">
                                            <i class="fa-solid fa-circle-info"></i>
                                            <span>Sin reserva activa vinculada</span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="libre-box">
                                        <i class="fa-solid fa-door-open"></i>
                                        <span>Disponible para asignación o reserva inmediata</span>
                                    </div>
                                <?php endif; ?>

                                <div class="tarifa-control">
                                    <label for="tarifa-cab-<?= $idCabana ?>"><i class="fa-solid fa-tag"></i> Tarifa actual:</label>
                                    <select id="tarifa-cab-<?= $idCabana ?>" class="select-tarifa" data-id-cabana="<?= $idCabana ?>">
                                        <?php if (empty($tarifasCabana)): ?>
                                            <option value="">Sin tarifas registradas</option>
                                        <?php else: ?>
                                            <?php foreach ($tarifasCabana as $tarifa): ?>
                                                <option value="<?= (int) $tarifa['id_tarifa'] ?>" <?= ((int) $tarifa['activa'] === 1) ? 'selected' : '' ?>>
                                                    <?= limpiarTexto($tarifa['temporada']) ?> — $<?= number_format((float) $tarifa['precio_noche'], 2) ?> / noche
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <div class="cabana-acciones">
                                    <button type="button" class="btn-inventario">
                                        <i class="fa-solid fa-boxes-stacked"></i>
                                        <span>Inventario</span>
                                    </button>
                                    <button type="button" class="btn-eliminar">
                                        <i class="fa-solid fa-trash-can"></i>
                                        <span>Eliminar</span>
                                    </button>
                                </div>
                            </div>

                            <div class="inventario-data" hidden>
                                <?php if (empty($inventarioCabana)): ?>
                                    <p style="font-family: 'Inter', sans-serif; font-size: 13px; color: #6b655e;">No hay elementos registrados en el inventario de esta cabaña.</p>
                                <?php else: ?>
                                    <?php foreach ($inventarioCabana as $item):
                                        $claseEstado = 'optimo';
                                        if ($item['estado'] === 'Bueno') {
                                            $claseEstado = 'bueno';
                                        } elseif ($item['estado'] === 'En revisión') {
                                            $claseEstado = 'revision';
                                        }
                                    ?>
                                        <div class="inv-item">
                                            <div class="inv-nombre">
                                                <strong><?= limpiarTexto($item['nombre_elemento']) ?></strong>
                                                <span>Cantidad: <?= (int) $item['cantidad'] ?></span>
                                            </div>
                                            <span class="inv-estado <?= $claseEstado ?>"><?= limpiarTexto($item['estado']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div id="sin-resultados-cabanas" class="sin-resultados" hidden>
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <p>No se encontraron cabañas que coincidan con tu búsqueda.</p>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div id="modal-inventario" class="modal-fondo">
        <div class="modal-ventana modal-mediana">
            <div class="modal-encabezado">
                <button type="button" class="btn-cerrar-modal" data-cerrar="modal-inventario">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <div class="modal-titulo-grupo">
                    <h3 id="titulo-modal-inventario">Inventario de Cabaña</h3>
                    <span>Elementos registrados en la base de datos</span>
                </div>
            </div>

            <div id="cuerpo-modal-inventario" class="modal-cuerpo"></div>
        </div>
    </div>

    <div id="modal-nueva-cabana" class="modal-fondo">
        <div class="modal-ventana modal-formulario">
            <div class="modal-encabezado">
                <button type="button" class="btn-cerrar-modal" data-cerrar="modal-nueva-cabana">
                    <i class="fa-solid fa-xmark"></i>
                </button>
                <div class="modal-titulo-grupo">
                    <h3 id="titulo-modal-nueva">Registrar Nueva Cabaña</h3>
                    <span>Completa los datos de cabaña, 3 fotografías obligatorias y tarifas</span>
                </div>
            </div>

            <form id="form-nueva-cabana" class="modal-cuerpo form-grid" action="acciones/guardar_cabana.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="token_csrf" value="<?= limpiarTexto($tokenCsrf) ?>">

                <fieldset class="bloque-form">
                    <legend><i class="fa-solid fa-campground"></i> Datos de la Cabaña</legend>
                    <div class="campos-dos-col">
                        <div class="campo-grupo">
                            <label for="cab-nombre">Nombre de la cabaña *</label>
                            <input type="text" id="cab-nombre" name="nombre" maxlength="80" placeholder="Ej: Cabaña Refugio del Río" required>
                        </div>

                        <div class="campo-grupo">
                            <label for="cab-capacidad">Capacidad (huéspedes) *</label>
                            <input type="number" id="cab-capacidad" name="capacidad" min="1" max="20" placeholder="Ej: 4" required>
                        </div>

                        <div class="campo-grupo">
                            <label for="cab-estado">Estado inicial *</label>
                            <select id="cab-estado" name="estado" required>
                                <option value="Desocupada" selected>Desocupada</option>
                                <option value="Ocupada">Ocupada</option>
                            </select>
                        </div>

                        <div class="campo-grupo campo-span-2">
                            <label for="cab-descripcion">Descripción</label>
                            <textarea id="cab-descripcion" name="descripcion" rows="2" placeholder="Describe las comodidades, vista y características de la cabaña..."></textarea>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="bloque-form">
                    <legend><i class="fa-regular fa-images"></i> Fotografías de la Cabaña (3 obligatorias)</legend>
                    <div class="fotos-grid">
                        <div class="campo-foto principal">
                            <label for="foto-principal">
                                <i class="fa-solid fa-camera"></i>
                                <strong>1. Foto Principal *</strong>
                                <span>Exterior de la cabaña</span>
                            </label>
                            <input type="file" id="foto-principal" name="foto_principal" accept="image/jpeg,image/png,image/webp" required>
                        </div>

                        <div class="campo-foto">
                            <label for="foto-secundaria-1">
                                <i class="fa-regular fa-image"></i>
                                <strong>2. Foto Secundaria *</strong>
                                <span>Interior / Habitación</span>
                            </label>
                            <input type="file" id="foto-secundaria-1" name="foto_secundaria_1" accept="image/jpeg,image/png,image/webp" required>
                        </div>

                        <div class="campo-foto">
                            <label for="foto-secundaria-2">
                                <i class="fa-regular fa-image"></i>
                                <strong>3. Foto Secundaria *</strong>
                                <span>Terraza / Amenidades</span>
                            </label>
                            <input type="file" id="foto-secundaria-2" name="foto_secundaria_2" accept="image/jpeg,image/png,image/webp" required>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="bloque-form">
                    <div class="legend-barra">
                        <legend><i class="fa-solid fa-tags"></i> Tarifas por Temporada</legend>
                        <button type="button" id="btn-mas-tarifa" class="btn-mini-add">
                            <i class="fa-solid fa-plus"></i> Añadir tarifa
                        </button>
                    </div>

                    <div id="contenedor-tarifas" class="lista-subfilas">
                        <div class="subfila-tarifa">
                            <div class="campo-grupo">
                                <label>Temporada *</label>
                                <input type="text" name="temporada[]" maxlength="40" placeholder="Ej: Temporada Regular" required>
                            </div>
                            <div class="campo-grupo">
                                <label>Precio / noche ($) *</label>
                                <input type="number" name="precio_noche[]" step="0.01" min="1" placeholder="180.00" required>
                            </div>
                            <div class="campo-grupo">
                                <label>Fecha inicio *</label>
                                <input type="date" name="fecha_inicio[]" required>
                            </div>
                            <div class="campo-grupo">
                                <label>Fecha fin *</label>
                                <input type="date" name="fecha_fin[]" required>
                            </div>
                            <div class="campo-grupo campo-span-2">
                                <label>¿Tarifa activa inicial? *</label>
                                <select name="activa[]" required>
                                    <option value="1" selected>Sí, tarifa activa</option>
                                    <option value="0">No, tarifa secundaria</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="bloque-form">
                    <div class="legend-barra">
                        <legend><i class="fa-solid fa-boxes-stacked"></i> Inventario Inicial</legend>
                        <button type="button" id="btn-mas-inventario" class="btn-mini-add">
                            <i class="fa-solid fa-plus"></i> Añadir elemento
                        </button>
                    </div>

                    <div id="contenedor-inventario" class="lista-subfilas">
                        <div class="subfila-inventario">
                            <div class="campo-grupo">
                                <label>Elemento *</label>
                                <input type="text" name="nombre_elemento[]" maxlength="100" placeholder="Ej: Cama King Size" required>
                            </div>
                            <div class="campo-grupo">
                                <label>Cantidad *</label>
                                <input type="number" name="cantidad[]" min="1" value="1" required>
                            </div>
                            <div class="campo-grupo">
                                <label>Estado *</label>
                                <select name="estado_inv[]" required>
                                    <option value="Óptimo" selected>Óptimo</option>
                                    <option value="Bueno">Bueno</option>
                                    <option value="En revisión">En revisión</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <div class="form-pie">
                    <button type="submit" class="btn-guardar-cabana">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Guardar Cabaña</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const tokenCsrf = <?= json_encode($tokenCsrf) ?>;
        const listaCabanas = document.getElementById('lista-cabanas');
        const contadorCabanas = document.getElementById('contador-cabanas');
        const inputBuscador = document.getElementById('buscador-cabanas');
        const mensajeSinResultados = document.getElementById('sin-resultados-cabanas');
        const menuToggle = document.getElementById('menu-toggle');

        const modalInventario = document.getElementById('modal-inventario');
        const tituloModalInv = document.getElementById('titulo-modal-inventario');
        const cuerpoModalInv = document.getElementById('cuerpo-modal-inventario');

        const modalNueva = document.getElementById('modal-nueva-cabana');
        const btnAbrirNueva = document.getElementById('btn-abrir-nueva');

        // Filtrado en tiempo real
        const filtrarCabanas = () => {
            const termino = inputBuscador.value.toLowerCase().trim();
            const tarjetas = listaCabanas.querySelectorAll('.cabana-card');
            let visibles = 0;

            tarjetas.forEach((card) => {
                const textoCard = card.textContent.toLowerCase();
                const coincide = textoCard.includes(termino);
                card.style.display = coincide ? '' : 'none';
                if (coincide) visibles++;
            });

            contadorCabanas.textContent = `${visibles} ${visibles === 1 ? 'cabaña' : 'cabañas'}`;
            mensajeSinResultados.hidden = tarjetas.length === 0 || visibles > 0;
        };

        inputBuscador.addEventListener('input', filtrarCabanas);

        // Actualizar tarifa activa en MySQL al cambiar el selector
        listaCabanas.addEventListener('change', async (evento) => {
            if (!evento.target.classList.contains('select-tarifa')) return;

            const selector = evento.target;
            const idCabana = selector.dataset.idCabana;
            const idTarifa = selector.value;

            if (!idTarifa) return;

            const datos = new FormData();
            datos.append('token_csrf', tokenCsrf);
            datos.append('id_cabana', idCabana);
            datos.append('id_tarifa', idTarifa);

            try {
                const respuesta = await fetch('acciones/actualizar_tarifa.php', {
                    method: 'POST',
                    body: datos
                });
                const resultado = await respuesta.json();
                if (!resultado.exito) {
                    alert(resultado.mensaje || 'No se pudo actualizar la tarifa.');
                }
            } catch (error) {
                alert('Error de conexión al actualizar la tarifa.');
            }
        });

        // Ver inventario y eliminar cabaña en MySQL
        listaCabanas.addEventListener('click', async (evento) => {
            const btnInv = evento.target.closest('.btn-inventario');
            const btnDel = evento.target.closest('.btn-eliminar');

            if (btnInv) {
                const card = btnInv.closest('.cabana-card');
                const nombre = card.dataset.nombreCabana;
                const invData = card.querySelector('.inventario-data');

                tituloModalInv.textContent = `Inventario — ${nombre}`;
                cuerpoModalInv.innerHTML = invData ? invData.innerHTML : '';
                modalInventario.classList.add('activo');
            }

            if (btnDel) {
                const card = btnDel.closest('.cabana-card');
                const idCabana = card.dataset.idCabana;
                const nombre = card.dataset.nombreCabana;

                if (!confirm(`¿Deseas eliminar "${nombre}" permanentemente de la base de datos?`)) {
                    return;
                }

                const datos = new FormData();
                datos.append('token_csrf', tokenCsrf);
                datos.append('id_cabana', idCabana);

                try {
                    const respuesta = await fetch('acciones/eliminar_cabana.php', {
                        method: 'POST',
                        body: datos
                    });
                    const resultado = await respuesta.json();

                    if (resultado.exito) {
                        card.remove();
                        filtrarCabanas();
                    } else {
                        alert(resultado.mensaje || 'No se pudo eliminar la cabaña.');
                    }
                } catch (error) {
                    alert('Error de conexión al intentar eliminar la cabaña.');
                }
            }
        });

        // Abrir modal de Nueva Cabaña (+)
        btnAbrirNueva.addEventListener('click', () => {
            modalNueva.classList.add('activo');
        });

        // Añadir filas extra de tarifas e inventario en el formulario
        document.getElementById('btn-mas-tarifa').addEventListener('click', () => {
            const fila = document.createElement('div');
            fila.className = 'subfila-tarifa';
            fila.innerHTML = `
                <div class="campo-grupo">
                    <label>Temporada *</label>
                    <input type="text" name="temporada[]" maxlength="40" placeholder="Ej: Temporada Alta" required>
                </div>
                <div class="campo-grupo">
                    <label>Precio / noche ($) *</label>
                    <input type="number" name="precio_noche[]" step="0.01" min="1" placeholder="250.00" required>
                </div>
                <div class="campo-grupo">
                    <label>Fecha inicio *</label>
                    <input type="date" name="fecha_inicio[]" required>
                </div>
                <div class="campo-grupo">
                    <label>Fecha fin *</label>
                    <input type="date" name="fecha_fin[]" required>
                </div>
                <div class="campo-grupo campo-span-2">
                    <label>¿Tarifa activa inicial? *</label>
                    <select name="activa[]" required>
                        <option value="0" selected>No, tarifa secundaria</option>
                        <option value="1">Sí, tarifa activa</option>
                    </select>
                </div>
            `;
            document.getElementById('contenedor-tarifas').appendChild(fila);
        });

        document.getElementById('btn-mas-inventario').addEventListener('click', () => {
            const fila = document.createElement('div');
            fila.className = 'subfila-inventario';
            fila.innerHTML = `
                <div class="campo-grupo">
                    <label>Elemento *</label>
                    <input type="text" name="nombre_elemento[]" maxlength="100" placeholder="Ej: Juego de Toallas" required>
                </div>
                <div class="campo-grupo">
                    <label>Cantidad *</label>
                    <input type="number" name="cantidad[]" min="1" value="1" required>
                </div>
                <div class="campo-grupo">
                    <label>Estado *</label>
                    <select name="estado_inv[]" required>
                        <option value="Óptimo" selected>Óptimo</option>
                        <option value="Bueno">Bueno</option>
                        <option value="En revisión">En revisión</option>
                    </select>
                </div>
            `;
            document.getElementById('contenedor-inventario').appendChild(fila);
        });

        // Cerrar ventanas con la X o haciendo clic afuera
        document.querySelectorAll('.btn-cerrar-modal').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.getElementById(btn.dataset.cerrar).classList.remove('activo');
            });
        });

        [modalInventario, modalNueva].forEach((modal) => {
            modal.addEventListener('click', (evento) => {
                if (evento.target === modal) modal.classList.remove('activo');
            });
        });

        document.addEventListener('keydown', (evento) => {
            if (evento.key === 'Escape') {
                modalInventario.classList.remove('activo');
                modalNueva.classList.remove('activo');
                if (menuToggle && menuToggle.checked) menuToggle.checked = false;
            }
        });
    </script>

</body>
</html>
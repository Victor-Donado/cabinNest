<?php
require_once __DIR__ . '/seguridad/proteger_admin.php';
require_once __DIR__ . '/configuracion/conexion.php';

$tokenCsrf = generarTokenCsrf();
$conexion  = obtenerConexion();

$consultaReservas = $conexion->query(
    'SELECT r.id_reserva,
            r.id_cliente,
            r.`id_cabaña`,
            r.fecha_reserva,
            r.fecha_checkin,
            r.fecha_checkout,
            r.estado,
            r.total,
            u.nombre AS nombre_cliente,
            u.apellido AS apellido_cliente,
            c.nombre AS nombre_cabana
     FROM `reserva` r
     INNER JOIN `usuario` u ON r.id_cliente = u.documento
     INNER JOIN `cabaña` c ON r.`id_cabaña` = c.`id_cabaña`
     ORDER BY r.fecha_reserva DESC, r.id_reserva DESC'
);
$listaReservas = $consultaReservas->fetchAll();

// Consulta preparada para obtener todos los huéspedes asociados a cada reserva
$consultaHuespedesReserva = $conexion->prepare(
    'SELECT hu.documento,
            hu.nombre,
            hu.apellido
     FROM `hospedaje` ho
     INNER JOIN `huesped` hu ON ho.id_huesped = hu.documento
     WHERE ho.id_reserva = :id_reserva
     ORDER BY CASE WHEN hu.documento = :id_cliente THEN 0 ELSE 1 END ASC,
              hu.nombre ASC,
              hu.apellido ASC'
);

function claseBadgeReserva(string $estado): string
{
    return match ($estado) {
        'Confirmada' => 'confirmada',
        'Pendiente'  => 'pendiente',
        'En curso'   => 'en-curso',
        'Completada' => 'completada',
        'Cancelada'  => 'cancelada',
        default      => 'confirmada',
    };
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CabinNest - Reservas</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lora:wght@600;700&family=Roboto+Slab:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/reservas.css">
    <script src="https://kit.fontawesome.com/3d84e79734.js" crossorigin="anonymous"></script>
    <link rel="icon" href="img/icono.png" type="image/png">
    <style>
        .acciones-reserva-celda {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 6px;
        }

        .fila-botones-estado {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .btn-ver-huespedes {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border: none;
            border-radius: 999px;
            background: #DF6D10;
            color: #ffffff;
            font-family: 'Inter', sans-serif;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease, transform 0.15s ease;
            white-space: nowrap;
        }

        .btn-ver-huespedes:hover {
            background: #c55d0a;
            transform: translateY(-1px);
        }

        /* Modal de Huéspedes (estilo consistente con Gestión de Usuarios) */
        .modal-fondo {
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

        .modal-fondo.activo {
            display: flex;
        }

        .modal-ventana {
            width: 100%;
            max-width: 500px;
            background: #ffffff;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.28);
            animation: aparecerModal 0.18s ease-out;
        }

        @keyframes aparecerModal {
            from { opacity: 0; transform: translateY(8px) scale(0.98); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .modal-encabezado {
            background: #304026;
            color: #ffffff;
            padding: 18px 22px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .btn-cerrar-modal {
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
            transition: background-color 0.2s ease;
        }

        .btn-cerrar-modal:hover {
            background: rgba(255, 255, 255, 0.28);
        }

        .modal-titulo-grupo h3 {
            margin: 0;
            font-family: 'Lora', serif;
            font-size: 19px;
            font-weight: 700;
            color: #ffffff;
        }

        .modal-titulo-grupo span {
            display: block;
            margin-top: 3px;
            font-family: 'Inter', sans-serif;
            font-size: 12.5px;
            color: #dce3d6;
        }

        .modal-cuerpo {
            padding: 20px 22px 22px;
            max-height: 65vh;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .modal-meta-badges {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .meta-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 11px;
            border-radius: 999px;
            background: #f1ece2;
            color: #304026;
            font-family: 'Inter', sans-serif;
            font-size: 12px;
            font-weight: 600;
        }

        .meta-chip i {
            color: #DF6D10;
        }

        .tarjeta-huesped-item {
            background: #fbf8f3;
            border: 1px solid #ebe4d8;
            border-radius: 12px;
            padding: 13px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .huesped-item-info strong {
            display: block;
            font-family: 'Inter', sans-serif;
            font-size: 14.5px;
            color: #2b2723;
        }

        .huesped-item-doc {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 4px;
            font-family: 'Inter', sans-serif;
            font-size: 12.5px;
            color: #6b655e;
        }

        .huesped-item-doc i {
            color: #DF6D10;
        }

        .badge-rol-huesped {
            padding: 4px 10px;
            border-radius: 999px;
            font-family: 'Inter', sans-serif;
            font-size: 11.5px;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-rol-huesped.titular {
            background: #e6efe2;
            color: #304026;
        }

        .badge-rol-huesped.acompanante {
            background: #fdf0e6;
            color: #DF6D10;
        }

        .sin-huespedes-modal {
            margin: 0;
            padding: 18px;
            text-align: center;
            background: #fbf8f3;
            border: 1px dashed #dcd4c8;
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
            font-size: 13.5px;
            color: #6b655e;
        }
    </style>
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
            <a href="dashboard.php">
                <i class="fa-solid fa-chess-board" aria-hidden="true"></i>
                <span>DashBoard</span>
            </a>
            <a class="activo" href="reservas.php" aria-current="page">
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
                <h1>Gestión de Reservas</h1>
                <p>Visualiza las reservas registradas recientemente y administra sus estados</p>
            </div>

            <a href="acciones/cerrar_sesion.php" id="cerrar-sesion">
                <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                <span>Cerrar Sesión</span>
            </a>
        </header>

        <!-- Cuerpo: Tabla de Reservas Recientes -->
        <div class="dashboard-contenido">
            <section class="reservas-caja" aria-labelledby="titulo-lista-reservas">

                <div class="reservas-barra">
                    <div class="reservas-titulo">
                        <i class="fa-regular fa-calendar-days" aria-hidden="true"></i>
                        <h2 id="titulo-lista-reservas">Reservas Recientes</h2>
                        <span id="contador-reservas" class="contador-reservas" aria-live="polite">
                            <?= count($listaReservas) ?> <?= count($listaReservas) === 1 ? 'registro' : 'registros' ?>
                        </span>
                    </div>

                    <div class="reservas-controles">
                        <div class="buscador-box">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <input type="search" id="buscador-reservas" placeholder="Buscar por ID, cliente, cabaña o fecha..." aria-label="Buscar reservas" autocomplete="off">
                        </div>

                        <select id="filtro-estado" class="select-filtro" aria-label="Filtrar por estado">
                            <option value="todos">Todos los estados</option>
                            <option value="Confirmada">Confirmada</option>
                            <option value="Pendiente">Pendiente</option>
                            <option value="En curso">En curso</option>
                            <option value="Completada">Completada</option>
                            <option value="Cancelada">Cancelada</option>
                        </select>
                    </div>
                </div>

                <!-- Encabezado de Columnas -->
                <div class="tabla-encabezado" aria-hidden="true">
                    <span>ID Reserva</span>
                    <span>Cliente (Doc.)</span>
                    <span>Fecha Reserva</span>
                    <span>Check-in</span>
                    <span>Check-out</span>
                    <span class="col-estado">Estado</span>
                </div>

                <!-- Listado de Reservas desde MySQL -->
                <div id="lista-reservas" class="lista-reservas">

                    <?php if (empty($listaReservas)): ?>
                        <div class="sin-resultados">
                            <i class="fa-regular fa-calendar-xmark" aria-hidden="true"></i>
                            <p>Aún no hay reservas registradas en la base de datos.</p>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($listaReservas as $reserva):
                        $idReserva  = (int) $reserva['id_reserva'];
                        $idCabana   = (int) $reserva['id_cabaña'];
                        $idCliente  = (int) $reserva['id_cliente'];
                        $estado     = $reserva['estado'];
                        $claseBadge = claseBadgeReserva($estado);

                        $consultaHuespedesReserva->execute([
                            ':id_reserva' => $idReserva,
                            ':id_cliente' => $idCliente,
                        ]);
                        $huespedesReserva = $consultaHuespedesReserva->fetchAll();
                        $totalHuespedes   = count($huespedesReserva);
                    ?>
                        <article class="reserva-item" data-estado="<?= limpiarTexto($estado) ?>">
                            <div class="dato-id">
                                <span class="badge-id">#<?= $idReserva ?></span>
                            </div>
                            <div class="dato-cliente">
                                <span class="id-cliente-tag"><?= limpiarTexto((string) $idCliente) ?></span>
                                <small style="display: block; font-size: 11.5px; color: #6b655e;">id_cabaña: <?= $idCabana ?></small>
                            </div>
                            <div class="dato-fecha-reg" data-etiqueta="Registrada:">
                                <i class="fa-regular fa-clock" aria-hidden="true"></i>
                                <span><?= date('d/m/Y H:i', strtotime($reserva['fecha_reserva'])) ?></span>
                            </div>
                            <div class="dato-fecha" data-etiqueta="Check-in:">
                                <i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i>
                                <span><?= date('d/m/Y', strtotime($reserva['fecha_checkin'])) ?></span>
                            </div>
                            <div class="dato-fecha" data-etiqueta="Check-out:">
                                <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                                <span><?= date('d/m/Y', strtotime($reserva['fecha_checkout'])) ?></span>
                            </div>
                            <div class="col-estado acciones-reserva-celda">
                                <div class="fila-botones-estado">
                                    <button type="button"
                                            class="btn-ver-huespedes"
                                            data-id-reserva="<?= $idReserva ?>"
                                            data-id-cabana="<?= $idCabana ?>"
                                            data-nombre-cabana="<?= limpiarTexto($reserva['nombre_cabana']) ?>"
                                            aria-label="Ver huéspedes de la reserva #<?= $idReserva ?>">
                                        <i class="fa-solid fa-users" aria-hidden="true"></i>
                                        <span>Huéspedes (<?= $totalHuespedes ?>)</span>
                                    </button>
                                    <span class="badge-estado <?= $claseBadge ?>"><?= limpiarTexto($estado) ?></span>
                                </div>

                                <select class="select-cambiar-estado"
                                        data-id-reserva="<?= $idReserva ?>"
                                        aria-label="Cambiar estado de reserva #<?= $idReserva ?>"
                                        style="font-family: 'Inter', sans-serif; font-size: 11.5px; padding: 3px 6px; border-radius: 6px; border: 1px solid #d5cfc7; background: #fbf8f3; color: #3d3832; cursor: pointer;">
                                    <?php foreach (['Pendiente', 'Confirmada', 'En curso', 'Completada', 'Cancelada'] as $opcionEstado): ?>
                                        <option value="<?= $opcionEstado ?>" <?= ($estado === $opcionEstado) ? 'selected' : '' ?>>
                                            <?= $opcionEstado ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Datos ocultos de los huéspedes de esta reserva para el modal -->
                            <div class="huespedes-data" hidden>
                                <div class="modal-meta-badges">
                                    <span class="meta-chip">
                                        <i class="fa-solid fa-hashtag" aria-hidden="true"></i> ID Reserva: #<?= $idReserva ?>
                                    </span>
                                    <span class="meta-chip">
                                        <i class="fa-solid fa-campground" aria-hidden="true"></i> ID Cabaña: <?= $idCabana ?> (<?= limpiarTexto($reserva['nombre_cabana']) ?>)
                                    </span>
                                </div>

                                <?php if (empty($huespedesReserva)): ?>
                                    <p class="sin-huespedes-modal">
                                        No hay huéspedes registrados en el hospedaje de esta reserva.
                                    </p>
                                <?php else: ?>
                                    <?php foreach ($huespedesReserva as $huesped):
                                        $esTitular = ((int) $huesped['documento'] === $idCliente);
                                    ?>
                                        <div class="tarjeta-huesped-item">
                                            <div class="huesped-item-info">
                                                <strong><?= limpiarTexto(trim($huesped['nombre'] . ' ' . $huesped['apellido'])) ?></strong>
                                                <span class="huesped-item-doc">
                                                    <i class="fa-regular fa-id-card" aria-hidden="true"></i>
                                                    Documento: <?= limpiarTexto((string) $huesped['documento']) ?>
                                                </span>
                                            </div>
                                            <span class="badge-rol-huesped <?= $esTitular ? 'titular' : 'acompanante' ?>">
                                                <?= $esTitular ? 'Titular' : 'Huésped' ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>

                    <!-- Mensaje cuando no hay coincidencias -->
                    <div id="sin-resultados-reservas" class="sin-resultados" hidden>
                        <i class="fa-regular fa-calendar-xmark" aria-hidden="true"></i>
                        <p>No se encontraron reservas que coincidan con el filtro aplicado.</p>
                    </div>

                </div>
            </section>
        </div>
    </main>

    <!-- ================= MODAL: HUÉSPEDES DE LA RESERVA ================= -->
    <div id="modal-huespedes" class="modal-fondo" role="dialog" aria-modal="true" aria-labelledby="titulo-modal-huespedes">
        <div class="modal-ventana">
            <header class="modal-encabezado">
                <button type="button" id="btn-cerrar-modal-huespedes" class="btn-cerrar-modal" aria-label="Cerrar ventana">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
                <div class="modal-titulo-grupo">
                    <h3 id="titulo-modal-huespedes">Huéspedes de la Reserva</h3>
                    <span id="subtitulo-modal-huespedes">Detalle de hospedaje registrado en CabinNest</span>
                </div>
            </header>

            <div id="cuerpo-modal-huespedes" class="modal-cuerpo"></div>
        </div>
    </div>

    <script>
        const tokenCsrf         = <?= json_encode($tokenCsrf) ?>;
        const listaReservas     = document.getElementById('lista-reservas');
        const inputBuscador     = document.getElementById('buscador-reservas');
        const selectEstado      = document.getElementById('filtro-estado');
        const contadorReservas  = document.getElementById('contador-reservas');
        const mensajeVacio      = document.getElementById('sin-resultados-reservas');
        const menuToggle        = document.getElementById('menu-toggle');

        const modalHuespedes     = document.getElementById('modal-huespedes');
        const tituloModalHuesp   = document.getElementById('titulo-modal-huespedes');
        const subtituloModalHues = document.getElementById('subtitulo-modal-huespedes');
        const cuerpoModalHuesp   = document.getElementById('cuerpo-modal-huespedes');
        const btnCerrarModal     = document.getElementById('btn-cerrar-modal-huespedes');

        const clasesEstado = {
            'Confirmada': 'confirmada',
            'Pendiente': 'pendiente',
            'En curso': 'en-curso',
            'Completada': 'completada',
            'Cancelada': 'cancelada'
        };

        const filtrarReservas = () => {
            const termino      = inputBuscador.value.toLowerCase().trim();
            const estadoFiltro = selectEstado.value;
            const filas        = listaReservas.querySelectorAll('.reserva-item');
            let visibles       = 0;

            filas.forEach((fila) => {
                const textoFila      = fila.textContent.toLowerCase();
                const estadoFila     = fila.dataset.estado;
                const coincideTexto  = textoFila.includes(termino);
                const coincideEstado = estadoFiltro === 'todos' || estadoFila === estadoFiltro;

                const mostrar = coincideTexto && coincideEstado;
                fila.style.display = mostrar ? '' : 'none';
                if (mostrar) visibles++;
            });

            contadorReservas.textContent = `${visibles} ${visibles === 1 ? 'registro' : 'registros'}`;
            mensajeVacio.hidden = filas.length === 0 || visibles > 0;
        };

        inputBuscador.addEventListener('input', filtrarReservas);
        selectEstado.addEventListener('change', filtrarReservas);

        // Abrir modal con los huéspedes de la reserva seleccionada
        listaReservas.addEventListener('click', (evento) => {
            const boton = evento.target.closest('.btn-ver-huespedes');
            if (!boton) return;

            const fila         = boton.closest('.reserva-item');
            const idReserva    = boton.dataset.idReserva;
            const idCabana     = boton.dataset.idCabana;
            const nombreCabana = boton.dataset.nombreCabana;
            const datosOcultos = fila.querySelector('.huespedes-data');

            tituloModalHuesp.textContent   = `Huéspedes de la Reserva #${idReserva}`;
            subtituloModalHues.textContent = `ID Reserva: #${idReserva} • ID Cabaña: ${idCabana} (${nombreCabana})`;
            cuerpoModalHuesp.innerHTML     = datosOcultos ? datosOcultos.innerHTML : '';
            modalHuespedes.classList.add('activo');
        });

        const cerrarModalHuespedes = () => {
            modalHuespedes.classList.remove('activo');
        };

        btnCerrarModal.addEventListener('click', cerrarModalHuespedes);
        modalHuespedes.addEventListener('click', (evento) => {
            if (evento.target === modalHuespedes) {
                cerrarModalHuespedes();
            }
        });

        // Actualizar estado de reserva en MySQL al cambiar el selector
        listaReservas.addEventListener('change', async (evento) => {
            if (!evento.target.classList.contains('select-cambiar-estado')) return;

            const selector    = evento.target;
            const fila        = selector.closest('.reserva-item');
            const badge       = fila.querySelector('.badge-estado');
            const idReserva   = selector.dataset.idReserva;
            const nuevoEstado = selector.value;

            const datos = new FormData();
            datos.append('token_csrf', tokenCsrf);
            datos.append('id_reserva', idReserva);
            datos.append('estado', nuevoEstado);

            try {
                const respuesta = await fetch('acciones/cambiar_estado_reserva.php', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: datos
                });
                const resultado = await respuesta.json();

                if (resultado.exito) {
                    fila.dataset.estado = nuevoEstado;
                    badge.textContent   = nuevoEstado;
                    badge.className     = `badge-estado ${clasesEstado[nuevoEstado] || 'confirmada'}`;
                    filtrarReservas();
                } else {
                    alert(resultado.mensaje || 'No se pudo actualizar el estado.');
                }
            } catch (error) {
                alert('Error de conexión al actualizar el estado de la reserva.');
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                cerrarModalHuespedes();
                if (menuToggle && menuToggle.checked) {
                    menuToggle.checked = false;
                }
            }
        });
    </script>

</body>
</html>
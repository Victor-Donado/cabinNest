<?php
require_once __DIR__ . '/seguridad/proteger_admin.php';
require_once __DIR__ . '/configuracion/conexion.php';

$conexion = obtenerConexion();

$consultaPagos = $conexion->query(
    'SELECT pg.id_pago,
            pg.id_reserva,
            pg.fecha_pago,
            pg.monto,
            pg.metodo_pago,
            pg.tipo_pago,
            r.estado AS estado_reserva
     FROM `pago` pg
     INNER JOIN `reserva` r ON pg.id_reserva = r.id_reserva
     ORDER BY pg.fecha_pago DESC, pg.id_pago DESC'
);
$listaPagos = $consultaPagos->fetchAll();

function obtenerEstadoPago(string $estadoReserva): array
{
    if ($estadoReserva === 'Cancelada') {
        return ['etiqueta' => 'Reembolsado', 'clase' => 'reembolsado'];
    }
    if ($estadoReserva === 'Pendiente') {
        return ['etiqueta' => 'Pendiente', 'clase' => 'pendiente'];
    }
    return ['etiqueta' => 'Aprobado', 'clase' => 'aprobado'];
}

function obtenerIconoMetodo(string $metodo): string
{
    return match ($metodo) {
        'Transferencia' => 'fa-solid fa-building-columns',
        'PSE'           => 'fa-solid fa-money-bill-transfer',
        'Efectivo'      => 'fa-solid fa-wallet',
        default         => 'fa-regular fa-credit-card',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CabinNest - Pagos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lora:wght@600;700&family=Roboto+Slab:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/pagos.css">
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
            <a class="activo" href="pagos.php">
                <i class="fa-solid fa-credit-card"></i>
                <span>Pagos</span>
            </a>
            <a href="cabanas.php">
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
                <h1>Gestión de Pagos</h1>
                <p>Consulta los pagos registrados, métodos y estados de transacción</p>
            </div>

            <a href="acciones/cerrar_sesion.php" id="cerrar-sesion">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Cerrar Sesión</span>
            </a>
        </div>

        <div class="dashboard-contenido">
            <div class="pagos-caja">

                <div class="pagos-barra">
                    <div class="pagos-titulo">
                        <i class="fa-solid fa-money-check-dollar"></i>
                        <h2 id="titulo-lista-pagos">Pagos Recientes</h2>
                        <span id="contador-pagos" class="contador-pagos">
                            <?= count($listaPagos) ?> <?= count($listaPagos) === 1 ? 'pago' : 'pagos' ?>
                        </span>
                    </div>

                    <div class="pagos-controles">
                        <div class="buscador-box">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="search" id="buscador-pagos" placeholder="Buscar ID pago, reserva o método...">
                        </div>

                        <select id="filtro-estado-pago" class="select-filtro">
                            <option value="todos">Todos los estados</option>
                            <option value="Aprobado">Aprobado</option>
                            <option value="Pendiente">Pendiente</option>
                            <option value="Reembolsado">Reembolsado</option>
                        </select>
                    </div>
                </div>

                <div class="tabla-encabezado">
                    <span>ID Pago</span>
                    <span>ID Reserva</span>
                    <span>Fecha Pago</span>
                    <span>Monto</span>
                    <span>Método Pago</span>
                    <span>Tipo Pago</span>
                    <span class="col-estado">Estado</span>
                </div>

                <div id="lista-pagos" class="lista-pagos">

                    <?php if (empty($listaPagos)): ?>
                        <div class="sin-resultados">
                            <i class="fa-solid fa-receipt"></i>
                            <p>Aún no hay pagos registrados en la base de datos.</p>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($listaPagos as $pago):
                        $estadoPago = obtenerEstadoPago($pago['estado_reserva']);
                        $iconoMetodo = obtenerIconoMetodo($pago['metodo_pago']);
                    ?>
                        <div class="pago-item" data-estado="<?= limpiarTexto($estadoPago['etiqueta']) ?>">
                            <div class="dato-id">
                                <span class="badge-id">#<?= (int) $pago['id_pago'] ?></span>
                            </div>
                            <div class="dato-reserva" data-etiqueta="Reserva:">
                                <span class="badge-reserva"><i class="fa-regular fa-calendar"></i> #<?= (int) $pago['id_reserva'] ?></span>
                            </div>
                            <div class="dato-fecha" data-etiqueta="Fecha:">
                                <i class="fa-regular fa-clock"></i>
                                <span><?= date('d/m/Y H:i', strtotime($pago['fecha_pago'])) ?></span>
                            </div>
                            <div class="dato-monto" data-etiqueta="Monto:">
                                <strong>$<?= number_format((float) $pago['monto'], 2) ?></strong>
                            </div>
                            <div class="dato-metodo" data-etiqueta="Método:">
                                <i class="<?= $iconoMetodo ?>"></i>
                                <span><?= limpiarTexto($pago['metodo_pago']) ?></span>
                            </div>
                            <div class="dato-tipo" data-etiqueta="Tipo:">
                                <span class="tag-tipo"><?= limpiarTexto($pago['tipo_pago']) ?></span>
                            </div>
                            <div class="col-estado">
                                <span class="badge-estado <?= $estadoPago['clase'] ?>"><?= limpiarTexto($estadoPago['etiqueta']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div id="sin-resultados-pagos" class="sin-resultados" hidden>
                        <i class="fa-solid fa-receipt"></i>
                        <p>No se encontraron pagos que coincidan con el filtro aplicado.</p>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
        const listaPagos    = document.getElementById('lista-pagos');
        const inputBuscador = document.getElementById('buscador-pagos');
        const selectEstado  = document.getElementById('filtro-estado-pago');
        const contadorPagos = document.getElementById('contador-pagos');
        const mensajeVacio  = document.getElementById('sin-resultados-pagos');
        const menuToggle    = document.getElementById('menu-toggle');

        const filtrarPagos = () => {
            const termino      = inputBuscador.value.toLowerCase().trim();
            const estadoFiltro = selectEstado.value;
            const filas        = listaPagos.querySelectorAll('.pago-item');
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

            contadorPagos.textContent = `${visibles} ${visibles === 1 ? 'pago' : 'pagos'}`;
            mensajeVacio.hidden = filas.length === 0 || visibles > 0;
        };

        inputBuscador.addEventListener('input', filtrarPagos);
        selectEstado.addEventListener('change', filtrarPagos);

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && menuToggle && menuToggle.checked) {
                menuToggle.checked = false;
            }
        });
    </script>

</body>
</html>
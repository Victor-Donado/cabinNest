<?php
// acciones/guardar_cabana.php

require_once __DIR__ . '/../configuracion/conexion.php';
require_once __DIR__ . '/../seguridad/proteger_admin.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../cabanas.php');
    exit;
}

if (!verificarTokenCsrf($_POST['token_csrf'] ?? null)) {
    $_SESSION['error_cabana'] = 'La sesión del formulario expiró. Intenta nuevamente.';
    header('Location: ../cabanas.php');
    exit;
}

$nombre      = trim($_POST['nombre'] ?? '');
$capacidad   = filter_var($_POST['capacidad'] ?? '', FILTER_VALIDATE_INT);
$ocupado     = (($_POST['estado'] ?? '') === 'Ocupada') ? 1 : 0;
$descripcion = trim($_POST['descripcion'] ?? '');

if ($nombre === '' || mb_strlen($nombre) > 80 || !$capacidad || $capacidad < 1 || $capacidad > 20) {
    $_SESSION['error_cabana'] = 'Verifica el nombre (máx. 80 caracteres) y la capacidad (1 a 20 huéspedes).';
    header('Location: ../cabanas.php');
    exit;
}

// Validar arreglos de tarifas e inventario
$temporadas   = $_POST['temporada'] ?? [];
$preciosNoche = $_POST['precio_noche'] ?? [];
$fechasInicio = $_POST['fecha_inicio'] ?? [];
$fechasFin    = $_POST['fecha_fin'] ?? [];
$tarifasAct   = $_POST['activa'] ?? [];

$elementosInv  = $_POST['nombre_elemento'] ?? [];
$cantidadesInv = $_POST['cantidad'] ?? [];
$estadosInv    = $_POST['estado_inv'] ?? [];

if (count($temporadas) === 0 || count($elementosInv) === 0) {
    $_SESSION['error_cabana'] = 'Debes registrar al menos una tarifa y un elemento de inventario.';
    header('Location: ../cabanas.php');
    exit;
}

// Función para validar cada una de las 3 imágenes obligatorias
function validarImagenSubida(array $archivo): string
{
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Las 3 fotografías de la cabaña son obligatorias.');
    }

    if (($archivo['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Cada fotografía debe pesar máximo 5 MB.');
    }

    $infoMime = new finfo(FILEINFO_MIME_TYPE);
    $tipoMime = $infoMime->file($archivo['tmp_name']);

    $extensionesPermitidas = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($extensionesPermitidas[$tipoMime])) {
        throw new RuntimeException('Solo se permiten imágenes en formato JPG, PNG o WEBP.');
    }

    return $extensionesPermitidas[$tipoMime];
}

function borrarArchivosSubidos(array $rutas): void
{
    foreach ($rutas as $ruta) {
        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }
}

$camposFotos = ['foto_principal', 'foto_secundaria_1', 'foto_secundaria_2'];
$extensionesFotos = [];

try {
    foreach ($camposFotos as $campo) {
        if (!isset($_FILES[$campo])) {
            throw new RuntimeException('Faltan fotografías por adjuntar.');
        }
        $extensionesFotos[$campo] = validarImagenSubida($_FILES[$campo]);
    }
} catch (RuntimeException $errorValidacion) {
    $_SESSION['error_cabana'] = $errorValidacion->getMessage();
    header('Location: ../cabanas.php');
    exit;
}

$carpetaSubidas = __DIR__ . '/../subidas/cabanas';
if (!is_dir($carpetaSubidas)) {
    mkdir($carpetaSubidas, 0755, true);
}

$conexion = obtenerConexion();
$archivosMovidos = [];

try {
    $conexion->beginTransaction();

    // 1. Insertar registro principal en `cabaña`
    $insertarCabana = $conexion->prepare(
        'INSERT INTO `cabaña` (nombre, capacidad, descripcion, ocupado)
         VALUES (:nombre, :capacidad, :descripcion, :ocupado)'
    );
    $insertarCabana->execute([
        ':nombre'      => $nombre,
        ':capacidad'   => $capacidad,
        ':descripcion' => $descripcion !== '' ? $descripcion : null,
        ':ocupado'     => $ocupado,
    ]);

    $idCabana = (int) $conexion->lastInsertId();

    // 2. Guardar las 3 fotos en disco y en `cabaña_foto` (nombre <= 25 chars para VARCHAR(50))
    $insertarFoto = $conexion->prepare(
        'INSERT INTO `cabaña_foto` (`id_cabaña`, id_foto, foto)
         VALUES (:id_cabana, :id_foto, :foto)'
    );

    $numeroFoto = 1;
    foreach ($camposFotos as $campo) {
        $extension = $extensionesFotos[$campo];
        $nombreArchivo = sprintf('cab_%d_%d_%s.%s', $idCabana, $numeroFoto, bin2hex(random_bytes(3)), $extension);
        $rutaDestino = $carpetaSubidas . '/' . $nombreArchivo;

        if (!move_uploaded_file($_FILES[$campo]['tmp_name'], $rutaDestino)) {
            throw new RuntimeException('No se pudo guardar una de las fotografías en el servidor.');
        }

        $archivosMovidos[] = $rutaDestino;

        $insertarFoto->execute([
            ':id_cabana' => $idCabana,
            ':id_foto'   => $numeroFoto,
            ':foto'      => $nombreArchivo,
        ]);

        $numeroFoto++;
    }

    // 3. Insertar tarifas en `tarifa` asegurando al menos una activa
    $hayActiva = false;
    foreach ($tarifasAct as $valorActiva) {
        if ((int) $valorActiva === 1) {
            $hayActiva = true;
            break;
        }
    }

    $insertarTarifa = $conexion->prepare(
        'INSERT INTO `tarifa` (`id_cabaña`, temporada, fecha_inicio, fecha_fin, precio_noche, activa)
         VALUES (:id_cabana, :temporada, :fecha_inicio, :fecha_fin, :precio_noche, :activa)'
    );

    foreach ($temporadas as $indice => $temporadaRaw) {
        $temporada   = trim($temporadaRaw);
        $precio      = filter_var($preciosNoche[$indice] ?? '', FILTER_VALIDATE_FLOAT);
        $fechaInicio = $fechasInicio[$indice] ?? '';
        $fechaFin    = $fechasFin[$indice] ?? '';
        $esActiva    = ((int) ($tarifasAct[$indice] ?? 0) === 1) ? 1 : 0;

        if (!$hayActiva && $indice === 0) {
            $esActiva = 1;
        }

        if ($temporada === '' || mb_strlen($temporada) > 40 || !$precio || $precio <= 0 || $fechaInicio === '' || $fechaFin === '') {
            throw new RuntimeException('Verifica que todas las tarifas tengan temporada, precio y fechas válidas.');
        }

        $insertarTarifa->execute([
            ':id_cabana'    => $idCabana,
            ':temporada'    => $temporada,
            ':fecha_inicio' => $fechaInicio,
            ':fecha_fin'    => $fechaFin,
            ':precio_noche' => number_format($precio, 2, '.', ''),
            ':activa'       => $esActiva,
        ]);
    }

    // 4. Insertar elementos en `inventario`
    $estadosPermitidosInv = ['Óptimo', 'Bueno', 'En revisión'];
    $insertarInventario = $conexion->prepare(
        'INSERT INTO `inventario` (nombre_elemento, cantidad, estado, `id_cabaña`)
         VALUES (:nombre_elemento, :cantidad, :estado, :id_cabana)'
    );

    foreach ($elementosInv as $indice => $elementoRaw) {
        $nombreElemento = trim($elementoRaw);
        $cantidad       = filter_var($cantidadesInv[$indice] ?? '', FILTER_VALIDATE_INT);
        $estadoInv      = $estadosInv[$indice] ?? 'Óptimo';

        if ($nombreElemento === '' || mb_strlen($nombreElemento) > 100 || !$cantidad || $cantidad < 1) {
            throw new RuntimeException('Verifica el nombre y la cantidad de cada elemento del inventario.');
        }

        if (!in_array($estadoInv, $estadosPermitidosInv, true)) {
            $estadoInv = 'Óptimo';
        }

        $insertarInventario->execute([
            ':nombre_elemento' => $nombreElemento,
            ':cantidad'        => $cantidad,
            ':estado'          => $estadoInv,
            ':id_cabana'       => $idCabana,
        ]);
    }

    $conexion->commit();

    $_SESSION['exito_cabana'] = "La cabaña \"{$nombre}\" se registró correctamente.";
    header('Location: ../cabanas.php');
    exit;
} catch (Throwable $error) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    borrarArchivosSubidos($archivosMovidos);
    error_log('Error al guardar cabaña: ' . $error->getMessage());

    $_SESSION['error_cabana'] = ($error instanceof RuntimeException)
        ? $error->getMessage()
        : 'Ocurrió un error interno al guardar la cabaña.';
    header('Location: ../cabanas.php');
    exit;
}
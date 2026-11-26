<?php
// configuracion/conexion.php

function obtenerConexion(): PDO
{
    static $conexion = null;

    if ($conexion !== null) {
        return $conexion;
    }

    $datosEntorno = require __DIR__ . '/entorno.php';

    $cadenaConexion = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $datosEntorno['servidor'],
        $datosEntorno['base_datos'],
        $datosEntorno['codificacion']
    );

    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $conexion = new PDO(
            $cadenaConexion,
            $datosEntorno['usuario'],
            $datosEntorno['contrasena'],
            $opciones
        );
        return $conexion;
    } catch (PDOException $error) {
        error_log('Error de conexión a la base de datos CabinNest: ' . $error->getMessage());
        http_response_code(500);
        exit('Error interno de conexión con el servidor.');
    }
}
<?php
// seguridad/proteger_cliente.php

require_once __DIR__ . '/sesion.php';
iniciarSesionSegura();

if (empty($_SESSION['documento']) || ($_SESSION['tipo_cuenta'] ?? '') !== 'huesped') {
    header('Location: login.php');
    exit;
}
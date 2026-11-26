<?php
// seguridad/proteger_admin.php

require_once __DIR__ . '/sesion.php';
iniciarSesionSegura();

if (empty($_SESSION['documento']) || ($_SESSION['tipo_cuenta'] ?? '') !== 'administrador') {
    header('Location: admin.php');
    exit;
}
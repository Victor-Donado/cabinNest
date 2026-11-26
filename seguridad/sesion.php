<?php
// seguridad/sesion.php

function iniciarSesionSegura(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function generarTokenCsrf(): string
{
    iniciarSesionSegura();

    if (empty($_SESSION['token_csrf'])) {
        $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['token_csrf'];
}

function verificarTokenCsrf(?string $tokenRecibido): bool
{
    iniciarSesionSegura();

    if (empty($_SESSION['token_csrf']) || empty($tokenRecibido)) {
        return false;
    }

    return hash_equals($_SESSION['token_csrf'], $tokenRecibido);
}

function limpiarTexto(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}
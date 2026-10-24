<?php
// Incluir configurações de segurança
require_once __DIR__ . '/config/security.php';

session_start();
require_once __DIR__ . '/src/Utils/Auth.php';
require_once __DIR__ . '/src/Utils/ErrorHandler.php';

// Inicializar manipulador de erros
ErrorHandler::initialize();

if (Auth::check()) {
    header('Location: public/dashboard.php');
    exit;
} else {
    header('Location: public/index.php');
    exit;
} 
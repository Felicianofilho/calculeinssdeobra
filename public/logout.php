<?php
// Caminho: /public/logout.php

session_start();
require_once __DIR__ . '/../src/Controllers/AuthController.php';

AuthController::logout();
header('Location: ../index.php');
exit;
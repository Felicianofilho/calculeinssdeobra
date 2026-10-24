<?php
session_start();
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Utils/Database.php';

if (!Auth::check() || !Auth::isAdmin()) {
    header('Location: index.php');
    exit;
}

$pdo = Database::getConnection();
$pdo->exec("TRUNCATE TABLE vau");

header('Location: admin_vau.php?msg=VAU+limpo+com+sucesso');
exit;
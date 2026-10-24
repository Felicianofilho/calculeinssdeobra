<?php
require_once __DIR__ . '/../Utils/Database.php';

class Setting
{
    public static function get($chave)
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT valor FROM settings WHERE chave = ?");
        $stmt->execute([$chave]);
        $row = $stmt->fetch();
        return $row ? $row['valor'] : null;
    }

    public static function set($chave, $valor)
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO settings (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
        return $stmt->execute([$chave, $valor]);
    }
}
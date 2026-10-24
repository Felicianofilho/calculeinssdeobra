<?php
require_once __DIR__ . '/../Utils/Database.php';

class Vau
{
    public static function find($uf, $destinacao, $mes, $ano)
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM vau WHERE uf = ? AND destinacao = ? AND mes = ? AND ano = ? LIMIT 1");
        $stmt->execute([$uf, $destinacao, $mes, $ano]);
        return $stmt->fetch();
    }

    public static function all()
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT * FROM vau ORDER BY uf, destinacao, ano DESC, mes DESC");
        return $stmt->fetchAll();
    }

    public static function create($data)
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO vau (uf, destinacao, tipo_obra, mes, ano, valor) VALUES (?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            $data['uf'],
            $data['destinacao'],
            $data['tipo_obra'],
            $data['mes'],
            $data['ano'],
            $data['valor']
        ]);
    }
}
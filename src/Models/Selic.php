<?php
require_once __DIR__ . '/../Utils/Database.php';

class Selic {
    public static function listarTodos() {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT * FROM selic ORDER BY ano DESC, mes DESC");
        return $stmt->fetchAll();
    }

    public static function buscar($mes, $ano) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM selic WHERE mes = ? AND ano = ? LIMIT 1");
        $stmt->execute([$mes, $ano]);
        return $stmt->fetch();
    }

    public static function adicionar($mes, $ano, $selic_acumulada) {
        $pdo = Database::getConnection();
        // Arredondar para 2 casas decimais
        $selic_arredondada = round(floatval($selic_acumulada), 2);
        $stmt = $pdo->prepare("INSERT INTO selic (mes, ano, selic_acumulada) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE selic_acumulada = VALUES(selic_acumulada)");
        return $stmt->execute([$mes, $ano, $selic_arredondada]);
    }

    public static function editar($id, $mes, $ano, $selic_acumulada) {
        $pdo = Database::getConnection();
        // Arredondar para 2 casas decimais
        $selic_arredondada = round(floatval($selic_acumulada), 2);
        $stmt = $pdo->prepare("UPDATE selic SET mes = ?, ano = ?, selic_acumulada = ? WHERE id = ?");
        return $stmt->execute([$mes, $ano, $selic_arredondada, $id]);
    }

    public static function excluir($id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("DELETE FROM selic WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function importarLote($dados) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO selic (mes, ano, selic_acumulada) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE selic_acumulada = VALUES(selic_acumulada)");
        $importados = 0;
        foreach ($dados as $linha) {
            if (count($linha) < 3) continue;
            $mes = intval($linha[0]);
            $ano = intval($linha[1]);
            $selic = floatval($linha[2]);
            
            // Aceitar valores 0 e valores válidos
            if ($mes && $ano && $selic >= 0) {
                // Arredondar para 2 casas decimais
                $selic_arredondada = round($selic, 2);
                if ($stmt->execute([$mes, $ano, $selic_arredondada])) {
                    $importados++;
                }
            }
        }
        return $importados;
    }

    public static function listarAnosDisponiveis() {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT DISTINCT ano FROM selic ORDER BY ano DESC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function listarPorAno($ano) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM selic WHERE ano = ? ORDER BY mes ASC");
        $stmt->execute([$ano]);
        return $stmt->fetchAll();
    }

    public static function listarUltimos12Meses() {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT * FROM selic WHERE selic_acumulada IS NOT NULL AND selic_acumulada != 0 ORDER BY ano DESC, mes DESC");
        $result = $stmt->fetchAll();
        $result = array_slice($result, 0, 12);
        // Não reordenar, manter do mais recente ao mais antigo
        return $result;
    }
} 
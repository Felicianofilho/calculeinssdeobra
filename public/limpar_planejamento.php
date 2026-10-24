<?php
require_once 'src/Utils/Database.php';

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("DELETE FROM planejamento_tributario WHERE simulacao_id = ?");
    $stmt->execute([611]);
    
    echo "Registros deletados com sucesso! Linhas afetadas: " . $stmt->rowCount();
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?> 
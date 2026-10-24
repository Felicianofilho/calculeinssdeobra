<?php
// Script de emergência para limpar locks travados
// ATENÇÃO: Use apenas em caso de emergência!

require_once __DIR__ . '/../src/Utils/Database.php';
require_once __DIR__ . '/../src/Utils/ProcessLock.php';

echo "=== Limpeza de Locks Travados ===\n";

try {
    $pdo = Database::getConnection();
    echo "✅ Conexão com banco OK\n";
    
    // Verificar locks ativos
    $stmt = $pdo->query("SELECT * FROM system_locks WHERE is_locked = 1");
    $locks_ativos = $stmt->fetchAll();
    
    if (count($locks_ativos) == 0) {
        echo "✅ Nenhum lock ativo encontrado\n";
    } else {
        echo "🔒 Locks ativos encontrados: " . count($locks_ativos) . "\n";
        
        foreach ($locks_ativos as $lock) {
            $tempo_ativo = time() - strtotime($lock['locked_at']);
            echo "   - {$lock['lock_name']} ativo há {$tempo_ativo} segundos (por: {$lock['locked_by']})\n";
            
            // Liberar lock
            if (ProcessLock::forceReleaseLock($lock['lock_name'])) {
                echo "      ✅ Lock liberado\n";
            } else {
                echo "      ❌ Erro ao liberar lock\n";
            }
        }
    }
    
    // Verificar se a tabela system_locks existe e criar se necessário
    $stmt = $pdo->query("SHOW TABLES LIKE 'system_locks'");
    if ($stmt->rowCount() == 0) {
        echo "🔧 Criando tabela system_locks...\n";
        
        $sql = "CREATE TABLE system_locks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lock_name VARCHAR(100) UNIQUE NOT NULL,
            is_locked BOOLEAN DEFAULT FALSE,
            locked_at TIMESTAMP NULL,
            locked_by VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        $pdo->exec($sql);
        echo "✅ Tabela system_locks criada\n";
        
        // Inserir lock padrão para INSS
        $stmt = $pdo->prepare("INSERT INTO system_locks (lock_name, is_locked) VALUES (?, 0)");
        $stmt->execute(['inss_calculation']);
        echo "✅ Lock 'inss_calculation' criado\n";
    }
    
    echo "\n✅ Limpeza concluída!\n";
    echo "Agora você pode tentar calcular o INSS novamente.\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}

echo "=====================================\n";
?>

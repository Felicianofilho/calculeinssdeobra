<?php
require_once '../src/Utils/Database.php';

try {
    $pdo = Database::getConnection();
    
    echo "<h2>Verificando estrutura da tabela VAU...</h2>";
    
    // Verificar se a coluna 'tipo_obra' existe
    $stmt = $pdo->query("SHOW COLUMNS FROM vau LIKE 'tipo_obra'");
    $tipo_obra_exists = $stmt->fetch();
    
    // Verificar se a coluna 'destinacao' existe
    $stmt = $pdo->query("SHOW COLUMNS FROM vau LIKE 'destinacao'");
    $destinacao_exists = $stmt->fetch();
    
    echo "<p>Coluna 'tipo_obra' existe: " . ($tipo_obra_exists ? 'SIM' : 'NÃO') . "</p>";
    echo "<p>Coluna 'destinacao' existe: " . ($destinacao_exists ? 'SIM' : 'NÃO') . "</p>";
    
    if ($tipo_obra_exists && !$destinacao_exists) {
        echo "<p>Renomeando 'tipo_obra' para 'destinacao'...</p>";
        $pdo->exec("ALTER TABLE vau CHANGE tipo_obra destinacao VARCHAR(50) NOT NULL");
        echo "<p>✅ Coluna renomeada com sucesso!</p>";
    } elseif (!$tipo_obra_exists && $destinacao_exists) {
        echo "<p>✅ Estrutura já está correta!</p>";
    } else {
        echo "<p>⚠️ Situação inesperada. Verificando estrutura completa:</p>";
        $stmt = $pdo->query("DESCRIBE vau");
        $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($colunas as $coluna) {
            echo "<tr>";
            echo "<td>" . $coluna['Field'] . "</td>";
            echo "<td>" . $coluna['Type'] . "</td>";
            echo "<td>" . $coluna['Null'] . "</td>";
            echo "<td>" . $coluna['Key'] . "</td>";
            echo "<td>" . $coluna['Default'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<h2>Testando consulta VAU...</h2>";
    try {
        $stmt = $pdo->prepare("SELECT valor FROM vau WHERE uf = ? AND destinacao = ? AND mes = ? AND ano = ? LIMIT 1");
        $stmt->execute(['SP', 'Residencial Unifamiliar', 1, 2024]);
        $result = $stmt->fetch();
        echo "<p>✅ Consulta VAU funcionando corretamente!</p>";
    } catch (Exception $e) {
        echo "<p>❌ Erro na consulta VAU: " . $e->getMessage() . "</p>";
    }
    
} catch (Exception $e) {
    echo "<h2>Erro:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?> 
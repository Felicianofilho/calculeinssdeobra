<?php
// Endpoint para ações administrativas VAU via AJAX
session_start();
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Utils/Database.php';

if (!Auth::check() || !Auth::isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

$pdo = Database::getConnection();

// Listas de opções
$destinacoes = [
    'Casa Popular',
    'Comercial Salas/Lojas',
    'Conj. Hab. Popular',
    'Galpão Ind.',
    'Res. Multifamiliar',
    'Res. Unifamiliar',
    'Edifício de Garagens'
];

$tipos_obra = [
    'Alvenaria',
    'Madeira',
    'Mista'
];

$ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'adicionar':
            $data = [
                'uf' => $input['uf'],
                'destinacao' => $input['destinacao'],
                'tipo_obra' => $input['tipo_obra'] ?? 'Alvenaria',
                'mes' => intval($input['mes']),
                'ano' => intval($input['ano']),
                'valor' => floatval(str_replace(',', '.', $input['valor']))
            ];
            
            $stmt = $pdo->prepare("INSERT INTO vau (uf, destinacao, tipo_obra, mes, ano, valor) VALUES (?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([$data['uf'], $data['destinacao'], $data['tipo_obra'], $data['mes'], $data['ano'], $data['valor']]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'VAU adicionado com sucesso!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao adicionar VAU.']);
            }
            break;
            
        case 'editar':
            $id = intval($input['id']);
            $data = [
                'uf' => $input['uf'],
                'destinacao' => $input['destinacao'],
                'tipo_obra' => $input['tipo_obra'] ?? 'Alvenaria',
                'mes' => intval($input['mes']),
                'ano' => intval($input['ano']),
                'valor' => floatval(str_replace(',', '.', $input['valor']))
            ];
            
            $stmt = $pdo->prepare("UPDATE vau SET uf = ?, destinacao = ?, tipo_obra = ?, mes = ?, ano = ?, valor = ? WHERE id = ?");
            $result = $stmt->execute([$data['uf'], $data['destinacao'], $data['tipo_obra'], $data['mes'], $data['ano'], $data['valor'], $id]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'VAU atualizado com sucesso!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao atualizar VAU.']);
            }
            break;
            
        case 'excluir':
            $id = intval($input['id']);
            $stmt = $pdo->prepare("DELETE FROM vau WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'VAU excluído com sucesso!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao excluir VAU.']);
            }
            break;
            
        case 'importar_csv':
            $csv_data = $input['csv_data'];
            $importados = 0;
            $erros = [];
            
            foreach ($csv_data as $linha) {
                if (count($linha) < 6) continue;
                
                $uf = trim($linha[0]);
                $destinacao = trim($linha[1]);
                $tipo_obra = trim($linha[2]);
                $mes = intval($linha[3]);
                $ano = intval($linha[4]);
                $valor = str_replace(['.', ' '], '', trim($linha[5]));
                $valor = str_replace(',', '.', $valor);
                $valor = floatval($valor);
                
                if (!in_array($uf, $ufs)) {
                    $erros[] = "UF inválida: $uf";
                    continue;
                }
                
                $stmt = $pdo->prepare("SELECT id FROM vau WHERE uf = ? AND destinacao = ? AND tipo_obra = ? AND mes = ? AND ano = ?");
                $stmt->execute([$uf, $destinacao, $tipo_obra, $mes, $ano]);
                if ($stmt->fetch()) {
                    $pdo->prepare("UPDATE vau SET valor = ? WHERE uf = ? AND destinacao = ? AND tipo_obra = ? AND mes = ? AND ano = ?")
                        ->execute([$valor, $uf, $destinacao, $tipo_obra, $mes, $ano]);
                } else {
                    $pdo->prepare("INSERT INTO vau (uf, destinacao, tipo_obra, mes, ano, valor) VALUES (?, ?, ?, ?, ?, ?)")
                        ->execute([$uf, $destinacao, $tipo_obra, $mes, $ano, $valor]);
                }
                $importados++;
            }
            
            echo json_encode([
                'success' => true, 
                'message' => "$importados registros importados com sucesso!",
                'erros' => $erros
            ]);
            break;
            
        case 'listar':
            $where = [];
            $params = [];
            
            if (!empty($input['filtro_uf'])) {
                $where[] = 'uf = ?';
                $params[] = $input['filtro_uf'];
            }
            if (!empty($input['filtro_destinacao'])) {
                $where[] = 'destinacao LIKE ?';
                $params[] = '%' . $input['filtro_destinacao'] . '%';
            }
            if (!empty($input['filtro_tipo_obra'])) {
                $where[] = 'tipo_obra LIKE ?';
                $params[] = '%' . $input['filtro_tipo_obra'] . '%';
            }
            if (!empty($input['filtro_ano'])) {
                $where[] = 'ano = ?';
                $params[] = intval($input['filtro_ano']);
            }
            if (!empty($input['filtro_mes'])) {
                $where[] = 'mes = ?';
                $params[] = intval($input['filtro_mes']);
            }
            
            $sql = "SELECT * FROM vau";
            if ($where) {
                $sql .= " WHERE " . implode(' AND ', $where);
            }
            $sql .= " ORDER BY uf, destinacao, tipo_obra, ano DESC, mes DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $vau_list = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $vau_list]);
            break;
            
        case 'exportar_csv':
            $stmt = $pdo->query("SELECT * FROM vau ORDER BY uf, destinacao, tipo_obra, ano DESC, mes DESC");
            $vau_list = $stmt->fetchAll();
            
            $csv = "UF;Destinação;Tipo de Obra;Mes;Ano;Valor\n";
            foreach ($vau_list as $row) {
                $csv .= "{$row['uf']};{$row['destinacao']};{$row['tipo_obra']};{$row['mes']};{$row['ano']};{$row['valor']}\n";
            }
            
            echo json_encode(['success' => true, 'csv' => $csv]);
            break;
            
        case 'estatisticas':
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM vau");
            $total = $stmt->fetch()['total'];
            
            $stmt = $pdo->query("SELECT COUNT(DISTINCT uf) as total FROM vau");
            $ufs_cobertas = $stmt->fetch()['total'];
            
            $stmt = $pdo->query("SELECT COUNT(DISTINCT destinacao) as total FROM vau");
            $destinacoes = $stmt->fetch()['total'];
            
            $stmt = $pdo->query("SELECT COUNT(DISTINCT tipo_obra) as total FROM vau");
            $tipos_obra = $stmt->fetch()['total'];
            
            $stmt = $pdo->query("SELECT AVG(valor) as media FROM vau");
            $valor_medio = $stmt->fetch()['media'];
            
            echo json_encode([
                'success' => true,
                'stats' => [
                    'total_registros' => $total,
                    'ufs_cobertas' => $ufs_cobertas,
                    'destinacoes' => $destinacoes,
                    'tipos_obra' => $tipos_obra,
                    'valor_medio' => $valor_medio
                ]
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
            break;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}
?>


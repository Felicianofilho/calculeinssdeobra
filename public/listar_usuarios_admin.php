<?php
session_start();
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Utils/Database.php';

header('Content-Type: application/json');

if (!Auth::canAccessAdminPanel()) {
    http_response_code(403);
    exit('Acesso negado');
}

$pdo = Database::getConnection();

// Parâmetros de paginação
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;

// Filtros
$filtroNome = $_GET['nome'] ?? '';
$filtroEmail = $_GET['email'] ?? '';
$filtroCategoria = $_GET['categoria'] ?? '';
$filtroStatus = $_GET['status'] ?? '';

// Construir query SQL com filtros
$where = ['1=1']; // Base sempre verdadeira
$params = [];

if (!empty($filtroNome)) {
    $where[] = 'nome LIKE ?';
    $params[] = "%$filtroNome%";
}

if (!empty($filtroEmail)) {
    $where[] = 'email LIKE ?';
    $params[] = "%$filtroEmail%";
}

if (!empty($filtroCategoria) && $filtroCategoria !== 'todas') {
    $where[] = 'user_type = ?';
    $params[] = $filtroCategoria;
}

if (!empty($filtroStatus) && $filtroStatus !== 'todas') {
    $where[] = 'ativo = ?';
    $params[] = $filtroStatus === 'ativo' ? 1 : 0;
}

$whereClause = implode(' AND ', $where);

// Contar total de registros
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE $whereClause");
$stmt->execute($params);
$total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Buscar registros paginados (LIMIT e OFFSET devem ser inteiros diretos, não placeholders)
$sql = "SELECT * FROM users WHERE $whereClause ORDER BY id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Gerar HTML
$html = '';
foreach ($usuarios as $u) {
    $userType = $u['user_type'] ?? 'lead';
    $badgeClass = '';
    $displayName = '';
    
    switch($userType) {
        case 'superadmin':
            $badgeClass = 'bg-danger';
            $displayName = 'SuperAdmin';
            break;
        case 'admin':
            $badgeClass = 'bg-warning';
            $displayName = 'Admin';
            break;
        case 'lead':
            $badgeClass = 'bg-primary';
            $displayName = 'Lead';
            break;
        case 'client':
            $badgeClass = 'bg-info';
            $displayName = 'Cliente';
            break;
        default:
            $badgeClass = 'bg-secondary';
            $displayName = ucfirst($userType);
    }
    
    $ativo = $u['ativo'] ? 'Ativo' : 'Inativo';
    $ativoClass = $u['ativo'] ? 'success' : 'warning';
    
    $html .= '<tr>';
    $html .= '<td>' . $u['id'] . '</td>';
    $html .= '<td>' . htmlspecialchars($u['nome']) . '</td>';
    $html .= '<td>' . htmlspecialchars($u['email']) . '</td>';
    $html .= '<td>' . htmlspecialchars($u['telefone'] ?? '-') . '</td>';
    $html .= '<td><span class="badge ' . $badgeClass . '">' . $displayName . '</span></td>';
    $html .= '<td><span class="badge bg-' . $ativoClass . '">' . $ativo . '</span></td>';
    
    // Botões de ação (mesma estrutura do admin.php)
    $html .= '<td>';
    
    // Verificar permissões (simular Auth::isSuperAdmin)
    $html .= '<div class="btn-group" role="group">';
    $html .= '<button type="button" class="btn btn-outline-primary" title="Editar dados do usuário" ';
    $html .= 'data-bs-toggle="modal" data-bs-target="#editUserModal" ';
    $html .= 'data-user-id="' . $u['id'] . '" ';
    $html .= 'data-user-nome="' . htmlspecialchars($u['nome']) . '" ';
    $html .= 'data-user-email="' . htmlspecialchars($u['email']) . '" ';
    $html .= 'data-user-telefone="' . htmlspecialchars($u['telefone'] ?? '') . '" ';
    $html .= 'data-user-type="' . $userType . '">';
    $html .= '<i class="fas fa-edit"></i></button>';
    
    $html .= '<button type="button" class="btn btn-outline-info" title="Alterar senha" ';
    $html .= 'data-bs-toggle="modal" data-bs-target="#changePasswordModal" ';
    $html .= 'data-user-id="' . $u['id'] . '">';
    $html .= '<i class="fas fa-key"></i></button>';
    
    $html .= '<div class="btn-group" role="group">';
    $html .= '<button type="button" class="btn btn-outline-warning dropdown-toggle" title="Alterar categoria" ';
    $html .= 'data-bs-toggle="dropdown" aria-expanded="false">';
    $html .= '<i class="fas fa-user-tag"></i></button>';
    $html .= '<ul class="dropdown-menu">';
    
    // Opções do dropdown
    if ($userType !== 'admin') {
        $html .= '<li><a class="dropdown-item" href="#" onclick="alterarCategoria(' . $u['id'] . ', \'admin\')">';
        $html .= '<i class="fas fa-arrow-up text-success me-2"></i>Promover para Admin</a></li>';
    }
    if ($userType !== 'lead') {
        $html .= '<li><a class="dropdown-item" href="#" onclick="alterarCategoria(' . $u['id'] . ', \'lead\')">';
        $html .= '<i class="fas fa-user text-primary me-2"></i>Definir como Lead</a></li>';
    }
    if ($userType !== 'client') {
        $html .= '<li><a class="dropdown-item" href="#" onclick="alterarCategoria(' . $u['id'] . ', \'client\')">';
        $html .= '<i class="fas fa-user-tie text-info me-2"></i>Definir como Cliente</a></li>';
    }
    
    $html .= '</ul></div>';
    
    // Botão Ativar/Desativar
    $btnStatusClass = $u['ativo'] ? 'secondary' : 'success';
    $btnStatusIcon = $u['ativo'] ? 'user-slash' : 'user-check';
    $btnStatusTitle = $u['ativo'] ? 'Desativar usuário' : 'Ativar usuário';
    
    $html .= '<button type="button" class="btn btn-outline-' . $btnStatusClass . '" ';
    $html .= 'title="' . $btnStatusTitle . '" ';
    $html .= 'onclick="toggleStatus(' . $u['id'] . ', ' . ($u['ativo'] ? 1 : 0) . ')">';
    $html .= '<i class="fas fa-' . $btnStatusIcon . '"></i></button>';
    
    // Botão Excluir
    $html .= '<button type="button" class="btn btn-outline-danger" title="Excluir usuário" ';
    $html .= 'onclick="excluirUsuario(' . $u['id'] . ')">';
    $html .= '<i class="fas fa-trash"></i></button>';
    
    $html .= '</div></td>';
    $html .= '</tr>';
}

if (empty($html)) {
    $html = '<tr><td colspan="7" class="text-center py-4">';
    $html .= '<i class="fas fa-inbox fa-3x text-muted mb-3"></i>';
    $html .= '<h5 class="text-muted">Nenhum usuário encontrado</h5>';
    $html .= '</td></tr>';
}

echo json_encode([
    'html' => $html,
    'total' => $total
]);

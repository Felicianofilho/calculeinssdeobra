<?php
session_start();
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Utils/Database.php';

header('Content-Type: application/json');

// 1. Verificar se o usuário é admin
if (!Auth::canAccessAdminPanel()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do planejamento inválido.']);
    exit;
}

$pdo = Database::getConnection();

// 2. Obter informações do usuário logado
$logged_user_id = $_SESSION['user_id'] ?? null;
$logged_user_type = $_SESSION['user_type'] ?? 'lead';

// 3. Obter informações do proprietário do planejamento (através da simulação)
$stmt = $pdo->prepare("SELECT u.id as owner_id, u.user_type as owner_type
                       FROM planejamento_tributario pt
                       JOIN simulacoes s ON pt.simulacao_id = s.id
                       JOIN users u ON s.user_id = u.id
                       WHERE pt.id = ?");
$stmt->execute([$id]);
$target = $stmt->fetch();

if (!$target) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Planejamento não encontrado.']);
    exit;
}

$owner_id = $target['owner_id'];
$owner_type = $target['owner_type'];

// 4. Aplicar regras de permissão
$can_delete = false;
if ($logged_user_type === 'superadmin') {
    $can_delete = true; // SuperAdmin pode tudo
} elseif ($logged_user_id == $owner_id) {
    $can_delete = true; // Dono pode excluir o seu
} elseif ($logged_user_type === 'admin' && ($owner_type === 'lead' || $owner_type === 'client')) {
    $can_delete = true; // Admin pode excluir de leads e clientes
}

if (!$can_delete) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Você não tem permissão para excluir este planejamento.']);
    exit;
}

// 5. Executar exclusão
$stmt = $pdo->prepare("DELETE FROM planejamento_tributario WHERE id = ?");
$result = $stmt->execute([$id]);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Planejamento excluído com sucesso.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir o planejamento.']);
}

exit;
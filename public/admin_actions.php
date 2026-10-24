<?php
// Endpoint para ações administrativas via AJAX
session_start();
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Utils/Database.php';
require_once __DIR__ . '/../src/Controllers/AdminController.php';

// Verificar se pode acessar o painel admin
if (!Auth::canAccessAdminPanel()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Obter dados JSON
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'criar_usuario':
            $data = [
                'nome' => $input['nome'],
                'email' => $input['email'],
                'telefone' => $input['telefone'] ?? '',
                'senha' => $input['senha'],
                'user_type' => $input['user_type'] ?? 'lead'
            ];
            
            $result = AdminController::criarUsuario($data);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Usuário criado com sucesso']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao criar usuário']);
            }
            break;

        case 'toggle_usuario':
            $id = $input['id'];
            $status = $input['status'];
            
            $result = AdminController::toggleUsuario($id, $status);
            if ($result) {
                $statusText = $status ? 'ativado' : 'desativado';
                echo json_encode(['success' => true, 'message' => "Usuário {$statusText} com sucesso"]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao alterar status do usuário']);
            }
            break;

        case 'promover_usuario':
            $id = $input['id'];
            
            $result = AdminController::promoverUsuario($id);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Usuário promovido a administrador']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao promover usuário']);
            }
            break;

        case 'rebaixar_usuario':
            $id = $input['id'];
            
            $result = AdminController::rebaixarUsuario($id);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Usuário rebaixado para usuário comum']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao rebaixar usuário']);
            }
            break;

        case 'alterar_categoria':
            $id = $input['id'];
            $categoria = $input['categoria'];
            
            // Verificar se está tentando promover para admin
            if ($categoria === 'admin' && !Auth::isSuperAdmin()) {
                echo json_encode(['success' => false, 'message' => 'Apenas SuperAdmins podem promover usuários para Admin']);
                break;
            }
            
            // Verificar se está tentando promover para superadmin
            if ($categoria === 'superadmin' && !Auth::isSuperAdmin()) {
                echo json_encode(['success' => false, 'message' => 'Apenas SuperAdmins podem promover usuários para SuperAdmin']);
                break;
            }
            
            // Verificar se está tentando alterar um SuperAdmin (apenas SuperAdmins podem fazer isso)
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT user_type FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $targetUser = $stmt->fetch();
            
            if ($targetUser && $targetUser['user_type'] === 'superadmin' && !Auth::isSuperAdmin()) {
                echo json_encode(['success' => false, 'message' => 'Apenas SuperAdmins podem alterar a categoria de outros SuperAdmins']);
                break;
            }
            
            // Verificar se está tentando alterar a si mesmo
            if ($id == $_SESSION['user_id']) {
                echo json_encode(['success' => false, 'message' => 'Você não pode alterar sua própria categoria']);
                break;
            }
            
            $result = AdminController::alterarCategoria($id, $categoria);
            if ($result) {
                $categorias = [
                    'admin' => 'Administrador',
                    'lead' => 'Lead',
                    'client' => 'Cliente',
                    'superadmin' => 'SuperAdmin'
                ];
                $nomeCategoria = $categorias[$categoria] ?? $categoria;
                echo json_encode(['success' => true, 'message' => "Categoria alterada para {$nomeCategoria}"]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao alterar categoria']);
            }
            break;

        case 'alterar_senha':
            $id = $input['id'];
            $senha = $input['senha'];
            
            $result = AdminController::alterarSenha($id, $senha);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Senha alterada com sucesso']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao alterar senha']);
            }
            break;

        case 'change_password':
            // Tratar requisição JSON
            $user_id = $input['user_id'] ?? null;
            $new_password = $input['new_password'] ?? null;
            
            if (!$user_id || !$new_password) {
                echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
                break;
            }
            
            // Verificar se está tentando alterar a senha de um SuperAdmin
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT user_type FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $targetUser = $stmt->fetch();
            
            if ($targetUser && $targetUser['user_type'] === 'superadmin' && !Auth::isSuperAdmin()) {
                echo json_encode(['success' => false, 'message' => 'Apenas SuperAdmins podem alterar a senha de outros SuperAdmins']);
                break;
            }
            
            $result = AdminController::alterarSenha($user_id, $new_password);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Senha alterada com sucesso']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao alterar senha']);
            }
            break;

        case 'edit_user':
            // Tratar edição de dados do usuário
            $user_id = $input['user_id'] ?? null;
            $nome = $input['nome'] ?? null;
            $email = $input['email'] ?? null;
            $telefone = $input['telefone'] ?? '';
            $user_type = $input['user_type'] ?? null;
            
            if (!$user_id || !$nome || !$email) {
                echo json_encode(['success' => false, 'message' => 'Dados obrigatórios não informados (nome e email são obrigatórios)']);
                break;
            }
            
            // Sanitizar telefone - remover caracteres não numéricos
            if (!empty($telefone)) {
                $telefone = preg_replace('/\D/', '', $telefone);
                // Validar telefone se fornecido
                if (strlen($telefone) < 10 || strlen($telefone) > 11) {
                    echo json_encode(['success' => false, 'message' => 'Telefone deve ter 10 ou 11 dígitos (com DDD)']);
                    break;
                }
            }
            
            // Verificar se está tentando editar um SuperAdmin
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT user_type FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $targetUser = $stmt->fetch();
            
            if ($targetUser && $targetUser['user_type'] === 'superadmin' && !Auth::isSuperAdmin()) {
                echo json_encode(['success' => false, 'message' => 'Apenas SuperAdmins podem editar dados de outros SuperAdmins']);
                break;
            }
            
            // Verificar se email já está em uso por outro usuário
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            $emailExists = $stmt->fetch();
            
            if ($emailExists) {
                echo json_encode(['success' => false, 'message' => 'Este e-mail já está sendo usado por outro usuário']);
                break;
            }
            
            // Preparar dados para atualização
            $updateData = [
                'nome' => $nome,
                'email' => $email,
                'telefone' => $telefone
            ];
            
            // Incluir user_type apenas se SuperAdmin está fazendo a alteração
            if (Auth::isSuperAdmin() && $user_type !== null) {
                $updateData['user_type'] = $user_type;
            }
            
            $result = AdminController::editarUsuario($user_id, $updateData);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Dados do usuário atualizados com sucesso']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao atualizar dados do usuário']);
            }
            break;

        case 'excluir_usuario':
            $id = $input['id'];
            
            // Verificar se está tentando excluir um SuperAdmin
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT user_type FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $targetUser = $stmt->fetch();
            
            if ($targetUser && $targetUser['user_type'] === 'superadmin' && !Auth::isSuperAdmin()) {
                echo json_encode(['success' => false, 'message' => 'Apenas SuperAdmins podem excluir outros SuperAdmins']);
                break;
            }
            
            $result = AdminController::excluirUsuario($id);
            if ($result === true) {
                echo json_encode(['success' => true, 'message' => 'Usuário excluído com sucesso']);
            } else if ($result === 'has_simulacoes') {
                echo json_encode(['success' => false, 'message' => 'Este usuário possui simulações registradas e não pode ser excluído. Ele será desativado, mas os dados das simulações serão preservados.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao excluir usuário']);
            }
            break;

        case 'excluir_simulacao':
            $simulacao_id = $input['id'];
            $result = AdminController::excluirSimulacao($simulacao_id);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Simulação excluída com sucesso']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao excluir simulação ou permissão insuficiente']);
            }
            break;

        case 'exportar_dados':
            $tipo = $input['tipo'] ?? 'usuarios';
            
            $dados = AdminController::exportarDados($tipo);
            if ($dados) {
                echo json_encode(['success' => true, 'dados' => $dados, 'tipo' => $tipo]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao exportar dados']);
            }
            break;

        case 'limpar_logs':
            $dias = $input['dias'] ?? 30;
            
            $result = AdminController::limparLogsAntigos($dias);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Logs antigos removidos com sucesso']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao limpar logs']);
            }
            break;

        case 'estatisticas':
            $stats = AdminController::getEstatisticasGerais();
            echo json_encode(['success' => true, 'estatisticas' => $stats]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
            break;
    }

} catch (Exception $e) {
    error_log("Erro no admin_actions.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>


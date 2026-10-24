<?php
session_start();
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Models/Simulacao.php';
require_once __DIR__ . '/../src/Utils/Database.php';
require_once __DIR__ . '/../src/Models/PlanejamentoTributario.php';

// --- AJAX Request Handler ---
// This new block handles the modern AJAX request from 'minhas_simulacoes.php'
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    header('Content-Type: application/json');
    
    if (!Auth::check()) {
        echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
        exit;
    }

    $response = ['success' => false, 'message' => ''];
    $id = $_POST['id'];
    $user = Auth::user();
    $pdo = Database::getConnection();

    $sim = Simulacao::find($id);

    // Check for permission
    if ($sim && ($sim['user_id'] == $user['id'] || Auth::isAdmin())) {
        try {
            $pdo->beginTransaction();

            // Delete associated planning records first to maintain integrity
            $stmt_plan = $pdo->prepare("DELETE FROM planejamento_tributario WHERE simulacao_id = ?");
            $stmt_plan->execute([$id]);

            // Delete the simulation itself
            $stmt_sim = $pdo->prepare("DELETE FROM simulacoes WHERE id = ?");
            if ($stmt_sim->execute([$id])) {
                $pdo->commit();
                $response['success'] = true;
                $response['message'] = 'Simulação excluída com sucesso.';
            } else {
                $pdo->rollBack();
                $response['message'] = 'Erro ao executar a exclusão da simulação.';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $response['message'] = 'Ocorreu um erro no banco de dados: ' . $e->getMessage();
        }
    } else {
        $response['message'] = 'Simulação não encontrada ou você não tem permissão para excluí-la.';
    }
    
    echo json_encode($response);
    exit;
}

// --- Legacy Handlers (for old forms or direct access) ---

if (!Auth::check()) {
    header('Location: login.php');
    exit;
}

$pdo = Database::getConnection();

// Exclusão múltipla (from original form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ids'])) {
    $ids = json_decode($_POST['ids'], true);
    if (is_array($ids)) {
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $user_id_check = Auth::isAdmin() ? '' : ' AND user_id = ?';
        
        // Also delete associated plannings
        $stmt_plan = $pdo->prepare("DELETE FROM planejamento_tributario WHERE simulacao_id IN ($placeholders)");
        $stmt_plan->execute($ids);

        $stmt = $pdo->prepare("DELETE FROM simulacoes WHERE id IN ($placeholders) $user_id_check");
        $params = $ids;
        if (!Auth::isAdmin()) {
            $params[] = $_SESSION['user_id'];
        }

        if ($stmt->execute($params)) {
            $_SESSION['sucesso_exclusao_simulacao'] = count($ids) . ' simulação(ões) excluída(s) com sucesso.';
        } else {
            $_SESSION['erro_exclusao_simulacao'] = 'Erro ao excluir simulação(ões).';
        }
    } else {
        $_SESSION['erro_exclusao_simulacao'] = 'IDs de simulação inválidos para exclusão múltipla.';
    }
    header('Location: minhas_simulacoes.php');
    exit;
}

// Exclusão individual (legacy GET request)
$id = $_GET['id'] ?? null;
if ($id) {
    $sim = Simulacao::find($id);
    if ($sim && ($sim['user_id'] == $_SESSION['user_id'] || Auth::isAdmin())) {
        // Also delete associated plannings
        $stmt_plan = $pdo->prepare("DELETE FROM planejamento_tributario WHERE simulacao_id = ?");
        $stmt_plan->execute([$id]);

        $stmt = $pdo->prepare("DELETE FROM simulacoes WHERE id = ?");
        if ($stmt->execute([$id])) {
            $_SESSION['sucesso_exclusao_simulacao'] = 'Simulação excluída com sucesso.';
        } else {
            $_SESSION['erro_exclusao_simulacao'] = 'Erro ao excluir simulação.';
        }
    } else {
        $_SESSION['erro_exclusao_simulacao'] = 'Simulação não encontrada ou você não tem permissão para excluí-la.';
    }
} else {
    $_SESSION['erro_exclusao_simulacao'] = 'ID da simulação não fornecido.';
}

header('Location: minhas_simulacoes.php');
exit;

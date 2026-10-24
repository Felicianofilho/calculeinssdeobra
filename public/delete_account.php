<?php
session_start();
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Utils/Database.php';

if (!Auth::check()) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

$action = $_POST['action'] ?? '';
$user = Auth::user();
$pdo = Database::getConnection();

if ($action === 'check') {
    try {
        $stmt_sim = $pdo->prepare("SELECT COUNT(*) FROM simulacoes WHERE user_id = ?");
        $stmt_sim->execute([$user['id']]);
        $simulacoes_count = $stmt_sim->fetchColumn();

        $stmt_plan = $pdo->prepare("SELECT COUNT(*) FROM planejamento_tributario WHERE user_id = ?");
        $stmt_plan->execute([$user['id']]);
        $planejamentos_count = $stmt_plan->fetchColumn();

        echo json_encode([
            'success' => true,
            'simulacoes' => $simulacoes_count,
            'planejamentos' => $planejamentos_count
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao verificar dados: ' . $e->getMessage()]);
    }
    exit;
}

    }
    exit;
}

if ($action === 'delete') {
    try {
        $pdo->beginTransaction();

        // 1. Excluir planejamentos
        $stmt_plan = $pdo->prepare("DELETE FROM planejamento_tributario WHERE user_id = ?");
        $stmt_plan->execute([$user['id']]);

        // 2. Excluir simulações
        $stmt_sim = $pdo->prepare("DELETE FROM simulacoes WHERE user_id = ?");
        $stmt_sim->execute([$user['id']]);

        // 3. Excluir o usuário
        $stmt_user = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt_user->execute([$user['id']]);

        $pdo->commit();

        // Destruir a sessão e deslogar
        Auth::logout();

        echo json_encode(['success' => true, 'message' => 'Sua conta foi excluída com sucesso.']);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir a conta: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Ação inválida.']);

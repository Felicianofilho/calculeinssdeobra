<?php
session_start();
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Models/User.php';
require_once __DIR__ . '/../src/Models/PlanejamentoTributario.php';
require_once __DIR__ . '/../src/Models/Simulacao.php';

if (!Auth::check()) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = Auth::user();
    $userId = $user['id'];

    // Excluir planejamentos tributários
    PlanejamentoTributario::deleteByUser($userId);

    // Excluir simulações
    Simulacao::deleteByUser($userId);

    // Excluir usuário
    User::delete($userId);

    // Limpar sessão e redirecionar
    Auth::logout();
    session_start();
    $_SESSION['success_message'] = 'Sua conta foi excluída com sucesso.';
    header('Location: login.php');
    exit;
}

// Redirecionar se o acesso não for via POST
header('Location: perfil.php');
exit;
?>
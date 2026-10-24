<?php
session_start();
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Models/Simulacao.php';

if (!Auth::check()) {
    header('Location: login.php');
    exit;
}

$user = Auth::user();
$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: minhas_simulacoes.php?error=not_found');
    exit;
}

// Buscar a simulação original
$simulacao = Simulacao::find($id);

// Verificar se a simulação existe e pertence ao usuário (ou se é admin)
if (!$simulacao || ($simulacao['user_id'] != $user['id'] && !Auth::isAdmin())) {
    header('Location: minhas_simulacoes.php?error=forbidden');
    exit;
}

// Preparar dados para duplicação
$dados_duplicacao = [
    'nome' => $simulacao['nome'] . ' (Cópia)',
    'responsavel' => $simulacao['responsavel'] ?? 'Pessoa Física',
    'destinacao' => $simulacao['destinacao'],
    'tipo_obra' => $simulacao['tipo_obra'],
    'uf' => $simulacao['uf'],
    'area' => $simulacao['area'],
    'municipio' => $simulacao['municipio'],
    'endereco' => $simulacao['endereco'],
    'regime_tributario' => $simulacao['regime_tributario']
];

// Armazenar os dados na sessão para o formulário preencher
$_SESSION['duplicar_simulacao'] = $dados_duplicacao;

// Redirecionar para o formulário
header('Location: nova_simulacao.php?duplicar=1');
exit;
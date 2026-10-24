<?php
require_once '../src/Utils/Database.php';
require_once '../src/Models/PlanejamentoTributario.php';

$simulacao_id = isset($_GET['simulacao_id']) ? intval($_GET['simulacao_id']) : null;

if (!$simulacao_id) {
    echo "Informe o parâmetro simulacao_id na URL.\n";
    exit;
}

$planejamentos = PlanejamentoTributario::findBySimulacao($simulacao_id);

if (empty($planejamentos)) {
    echo "Nenhum planejamento encontrado para a simulação $simulacao_id.\n";
    exit;
}

foreach ($planejamentos as $p) {
    echo "ID: {$p['id']} | Período: {$p['periodo_inicio']} a {$p['periodo_fim']} | Área: {$p['area_obra']} | Criado: {$p['criado_em']}\n";
} 
<?php
require_once '../src/Utils/Database.php';
require_once '../src/Models/PlanejamentoTributario.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$id) {
    echo "Informe o parâmetro id na URL.\n";
    exit;
}

$result = PlanejamentoTributario::delete($id);

if ($result) {
    echo "Planejamento $id excluído com sucesso.\n";
} else {
    echo "Erro ao excluir planejamento $id.\n";
} 
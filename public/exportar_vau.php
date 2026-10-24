<?php
session_start();
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Utils/Database.php';


if (!Auth::check() || !Auth::isAdmin()) {
    header('Location: index.php');
    exit;
}

$pdo = Database::getConnection();
$stmt = $pdo->query("SELECT uf, tipo_obra, mes, ano, valor FROM vau ORDER BY uf, tipo_obra, ano DESC, mes DESC");

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="vau_export.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['UF', 'Tipo de Obra', 'Mês', 'Ano', 'Valor']);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, $row);
}
fclose($output);
exit;
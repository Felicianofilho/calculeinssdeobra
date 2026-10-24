<?php
require_once __DIR__ . '/../src/Models/Selic.php';

echo "<pre>";
for ($ano = 2022; $ano <= 2024; $ano++) {
    for ($mes = 1; $mes <= 12; $mes++) {
        $r = Selic::buscar($mes, $ano);
        if (!$r) {
            echo sprintf("FALTA: %02d/%04d\n", $mes, $ano);
        } else {
            echo sprintf("OK: %02d/%04d - %s\n", $mes, $ano, $r['selic_acumulada']);
        }
    }
}
echo "</pre>"; 
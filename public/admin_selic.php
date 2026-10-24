<?php
session_start();
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Models/Selic.php';
require_once __DIR__ . '/../src/Utils/Database.php';
require_once __DIR__ . '/../src/Utils/LocalConfig.php';

if (!Auth::check() || !Auth::isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$user = Auth::user();

// Mensagens
$message = '';
$messageType = '';

// Importação via textarea
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dados_colados'])) {
    $texto = $_POST['dados_colados'];
    $linhas = preg_split('/\r\n|\r|\n/', $texto);
    $dados = [];
    $anos = [];
    $meses = ['janeiro'=>1,'fevereiro'=>2,'março'=>3,'abril'=>4,'maio'=>5,'junho'=>6,'julho'=>7,'agosto'=>8,'setembro'=>9,'outubro'=>10,'novembro'=>11,'dezembro'=>12];
    $meses_lista = array_keys($meses);
    $linhas_trim = array_map('trim', $linhas);
    $linhas_trim = array_filter($linhas_trim, function($l) { return $l !== ''; });
    $linhas_trim = array_values($linhas_trim);
    $formato = null;
    // Detectar formato
    if (count($linhas_trim) > 0) {
        // Formato horizontal: primeira linha contém anos separados por ; ou tab
        $primeira = preg_split('/[;\t]+/', $linhas_trim[0]);
        $anos_detectados = array_filter($primeira, function($v) { return preg_match('/^\d{4}$/', trim($v)); });
        if (count($anos_detectados) >= 1) {
            $formato = 'horizontal';
        } else {
            // Formato vertical: primeiras 12 linhas são meses
            $meses_detectados = array_map('strtolower', array_slice($linhas_trim, 0, 12));
            $todos_meses = true;
            foreach ($meses_lista as $i => $mes_nome) {
                if (strpos($meses_detectados[$i] ?? '', $mes_nome) === false) {
                    $todos_meses = false;
                    break;
                }
            }
            if ($todos_meses) {
                $formato = 'vertical';
            }
        }
    }
    if ($formato === 'horizontal') {
        // Já existente: anos na primeira linha, depois mês;valor;valor...
        $anos = [];
        $header_found = false;
        foreach ($linhas_trim as $i => $linha) {
            $cols = preg_split('/[;\t]+/', $linha);
            if (!$header_found && preg_match('/\d{4}/', $linha)) {
                foreach ($cols as $col) {
                    $col_trim = trim($col);
                    if (preg_match('/^\d{4}$/', $col_trim)) {
                        $anos[] = intval($col_trim);
                    }
                }
                if (count($anos) > 0) {
                    $header_found = true;
                }
                continue;
            }
            if ($header_found && count($anos) > 0 && preg_match('/^(janeiro|fevereiro|março|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro)/i', $cols[0])) {
                $mes_nome = strtolower(trim($cols[0]));
                $mes = $meses[$mes_nome] ?? null;
                if (!$mes) continue;
                for ($j = 0; $j < count($anos); $j++) {
                    $ano = $anos[$j];
                    $col_index = $j + 1;
                    $valor = isset($cols[$col_index]) ? trim($cols[$col_index]) : '';
                    if ($valor === '' || $valor === '---' || $valor === '-' || $valor === '0' || $valor === '0,00' || $valor === '0.00') {
                        $valorNum = 0.0;
                    } else {
                        $valor_limpo = str_replace(',', '.', $valor);
                        $valorNum = floatval($valor_limpo);
                        if ($valorNum < 0) $valorNum = 0.0;
                    }
                    $valorNum = round($valorNum, 2);
                    $dados[] = [$mes, $ano, $valorNum];
                }
            }
        }
    } elseif ($formato === 'vertical') {
        // Novo: 12 meses, depois blocos de ano + 12 valores
        $meses_detectados = array_map('strtolower', array_slice($linhas_trim, 0, 12));
        $mapa_meses = [];
        foreach ($meses_detectados as $i => $mes_nome) {
            $mes_nome = preg_replace('/[^a-zçãáéíóúâêôàèìòùäëïöü]/', '', $mes_nome); // remove acentos e caracteres estranhos
            $mapa_meses[$i] = $meses[$mes_nome] ?? ($i+1);
        }
        $i = 12;
        while ($i < count($linhas_trim)) {
            $linha = $linhas_trim[$i];
            if (preg_match('/^\d{4}$/', $linha)) {
                $ano = intval($linha);
                for ($j = 0; $j < 12; $j++) {
                    $i++;
                    if (!isset($linhas_trim[$i])) break;
                    $valor = trim($linhas_trim[$i]);
                    if ($valor === '' || $valor === '---' || $valor === '-' || $valor === '0' || $valor === '0,00' || $valor === '0.00') {
                        $valorNum = 0.0;
                    } else {
                        $valor_limpo = str_replace(',', '.', $valor);
                        $valorNum = floatval($valor_limpo);
                        if ($valorNum < 0) $valorNum = 0.0;
                    }
                    $valorNum = round($valorNum, 2);
                    $mes = $mapa_meses[$j] ?? ($j+1);
                    $dados[] = [$mes, $ano, $valorNum];
                }
            } else {
                $i++;
            }
        }
    } else {
        $message = 'Formato de importação não reconhecido.';
        $messageType = 'danger';
    }
    if (!empty($dados)) {
        $importados = Selic::importarLote($dados);
        $message = "$importados registros importados com sucesso!";
        $messageType = 'success';
    } elseif (empty($message)) {
        $message = 'Nenhum dado válido para importar.';
        $messageType = 'danger';
    }
}

// Adicionar/Editar manual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $mes = intval($_POST['mes'] ?? 1);
    $ano = intval($_POST['ano'] ?? date('Y'));
    $selic = str_replace(',', '.', $_POST['selic_acumulada'] ?? '0');
    if ($_POST['acao'] === 'adicionar') {
        if (Selic::adicionar($mes, $ano, $selic)) {
            $message = 'SELIC adicionada com sucesso!';
            $messageType = 'success';
        } else {
            $message = 'Erro ao adicionar SELIC.';
            $messageType = 'danger';
        }
    } elseif ($_POST['acao'] === 'editar' && isset($_POST['id'])) {
        if (Selic::editar($_POST['id'], $mes, $ano, $selic)) {
            $message = 'SELIC atualizada com sucesso!';
            $messageType = 'success';
        } else {
            $message = 'Erro ao atualizar SELIC.';
            $messageType = 'danger';
        }
    }
}

// Excluir
if (isset($_GET['excluir'])) {
    if (Selic::excluir($_GET['excluir'])) {
        $message = 'SELIC excluída com sucesso!';
        $messageType = 'success';
    } else {
        $message = 'Erro ao excluir SELIC.';
        $messageType = 'danger';
    }
}

// Editar registro
$registro_editar = null;
if (isset($_GET['editar'])) {
    $registro_editar = Selic::buscar($_GET['mes'], $_GET['ano']);
}

// Listar todos
$selics = Selic::listarTodos();
$anos_disponiveis = Selic::listarAnosDisponiveis();
$ano_filtro = isset($_GET['ano']) ? intval($_GET['ano']) : '';
if ($ano_filtro) {
    $selics = Selic::listarPorAno($ano_filtro);
} else {
    $selics = Selic::listarUltimos12Meses();
}

function mes_nome($mes) {
    $nomes = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];
    return $nomes[intval($mes)] ?? $mes;
}
$config = LocalConfig::getAll();
$baseUrl = LocalConfig::getBaseUrl();

// Cache-busting para logo e favicon
$logoFile = $config['theme']['branding']['logo_file'] ?? '';
$logoFilePath = !empty($logoFile) ? __DIR__ . '/' . $logoFile : '';
$logoVersion = ($logoFilePath && file_exists($logoFilePath) ? filemtime($logoFilePath) : time());

$faviconFile = $config['theme']['branding']['favicon_file'] ?? '';
$faviconFilePath = !empty($faviconFile) ? __DIR__ . '/' . $faviconFile : '';
$faviconVersion = ($faviconFilePath && file_exists($faviconFilePath) ? filemtime($faviconFilePath) : time());
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administração - Tabela SELIC</title>
    <?php if (!empty($faviconFile)): ?>
    <link rel="icon" type="image/png" href="<?php echo LocalConfig::buildAssetUrl($faviconFile); ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <?php echo LocalConfig::getCustomCSS(); ?>
</head>
<body>
<div class="d-flex" id="wrapper">
    <!-- Sidebar -->
    <div class="bg-dark" id="sidebar-wrapper">
        <div class="sidebar-content-wrapper">
            <div class="sidebar-heading text-center py-4">
                <?php if (!empty($logoFile)): ?>
                    <img class="sidebar-logo" src="<?php echo LocalConfig::buildAssetUrl($logoFile); ?>" alt="Logo" style="max-width:180px;max-height:80px;object-fit:contain;background:#fff;padding:8px 12px;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.1);">
                <?php else: ?>
                    <span class="text-white fw-bold fs-5"><?php echo htmlspecialchars($config['system']['name'] ?? 'Calculadora INSS Obra Legal ®'); ?></span>
                <?php endif; ?>
                <div class="text-white mt-2">
                    <i class="fas fa-percentage me-2"></i>Gestão SELIC
                </div>
            </div>
            <div class="list-group list-group-flush">
                <a href="javascript:void(0);" onclick="window.history.back();" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="fas fa-arrow-left fa-fw me-2"></i><span class="sidebar-text">Voltar</span>
                </a>
                <a href="dashboard.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="fas fa-tachometer-alt fa-fw me-2"></i><span class="sidebar-text">Dashboard</span>
                </a>
                <a href="admin_selic.php" class="list-group-item list-group-item-action bg-dark text-white active">
                    <i class="fas fa-percentage fa-fw me-2"></i><span class="sidebar-text">Tabela SELIC</span>
                </a>
                <a href="#importacao" class="list-group-item list-group-item-action bg-dark text-white" onclick="document.getElementById('importacao').scrollIntoView({behavior:'smooth'});">
                    <i class="fas fa-upload fa-fw me-2"></i><span class="sidebar-text">Importação</span>
                </a>
                <a href="#gestao" class="list-group-item list-group-item-action bg-dark text-white" onclick="document.getElementById('gestao').scrollIntoView({behavior:'smooth'});">
                    <i class="fas fa-edit fa-fw me-2"></i><span class="sidebar-text">Gestão Manual</span>
                </a>
                <a href="#listagem" class="list-group-item list-group-item-action bg-dark text-white" onclick="document.getElementById('listagem').scrollIntoView({behavior:'smooth'});">
                    <i class="fas fa-list fa-fw me-2"></i><span class="sidebar-text">Listagem</span>
                </a>
            </div>
        </div>
        <div class="sidebar-toggler-wrapper">
            <button class="sidebar-toggler" id="sidebar-toggle-btn">
                <i class="fas fa-angle-double-left"></i>
            </button>
        </div>
    </div>
    <!-- /#sidebar-wrapper -->

    <!-- Page Content -->
    <div id="page-content-wrapper">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark border-bottom">
            <div class="container-fluid">
                
                <div class="navbar-nav ms-auto">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-user-shield me-2"></i><?= htmlspecialchars($user['nome']) ?> (Admin)
                        </a>
                        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <a class="dropdown-item" href="admin.php">Dashboard Admin</a>
                            <a class="dropdown-item" href="dashboard.php">Dashboard Usuário</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="logout.php">Sair</a>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <div class="container-fluid p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0 text-gray-800">
                    <i class="fas fa-percentage me-2"></i>Gestão da Tabela SELIC
                </h1>
            </div>

            <!-- Mensagens -->
            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Importação -->
            <div class="card mb-4" id="importacao">
                <div class="card-header bg-primary text-white"><i class="fas fa-upload me-2"></i>Importar Tabela SELIC (Colar ou CSV)</div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <textarea name="dados_colados" rows="4" class="form-control mb-2" placeholder="Exemplo de linha: 2023;1;0.98\n2023;2;0.87\nAno;Mês;Taxa SELIC acumulada\nCole aqui os dados no formato: ano;mes;taxa (um por linha)"></textarea>
                        <div class="form-text mb-2">
                            Você pode copiar a tabela SELIC oficial diretamente do site da Receita Federal do Brasil:<br>
                            <a href="https://sicalc.receita.economia.gov.br/sicalc/selic/consulta" target="_blank">https://sicalc.receita.economia.gov.br/sicalc/selic/consulta</a>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-2"></i>Importar</button>
                    </form>
                </div>
            </div>

            <!-- Gestão Manual -->
            <div class="card mb-4" id="gestao">
                <div class="card-header bg-info text-white"><i class="fas fa-edit me-2"></i><?= $registro_editar ? 'Editar SELIC' : 'Adicionar SELIC Mensal' ?></div>
                <div class="card-body">
                    <form method="post" class="row g-3 align-items-end">
                        <input type="hidden" name="acao" value="<?= $registro_editar ? 'editar' : 'adicionar' ?>">
                        <?php if ($registro_editar): ?>
                            <input type="hidden" name="id" value="<?= $registro_editar['id'] ?>">
                        <?php endif; ?>
                        <div class="col-md-3">
                            <label class="form-label">Mês:</label>
                            <select name="mes" class="form-select" required>
                                <?php for ($m=1;$m<=12;$m++): ?>
                                    <option value="<?= $m ?>" <?= ($registro_editar && $registro_editar['mes']==$m) ? 'selected' : '' ?>><?= mes_nome($m) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Ano:</label>
                            <input type="number" name="ano" value="<?= $registro_editar['ano'] ?? date('Y') ?>" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">SELIC acumulada:</label>
                            <input type="text" name="selic_acumulada" value="<?= $registro_editar['selic_acumulada'] ?? '' ?>" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-info w-100"><?= $registro_editar ? 'Salvar' : 'Adicionar' ?></button>
                        </div>
                        <?php if ($registro_editar): ?>
                            <div class="col-md-2">
                                <a href="admin_selic.php" class="btn btn-secondary w-100">Cancelar</a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Filtro por Ano -->
            <form method="get" class="mb-3 d-flex align-items-center gap-2">
                <label for="ano" class="form-label mb-0">Filtrar por Ano:</label>
                <select name="ano" id="ano" class="form-select w-auto" onchange="this.form.submit()">
                    <option value="">Últimos 12 meses</option>
                    <?php foreach ($anos_disponiveis as $ano): ?>
                        <option value="<?= $ano ?>" <?= ($ano_filtro == $ano) ? 'selected' : '' ?>><?= $ano ?></option>
                    <?php endforeach; ?>
                </select>
            </form>

            <!-- Listagem -->
            <div class="card" id="listagem">
                <div class="card-header bg-primary text-white"><i class="fas fa-list me-2"></i>Histórico SELIC Acumulada</div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-striped table-bordered mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Mês</th>
                                    <th>Ano</th>
                                    <th>SELIC Acumulada</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($selics as $s): ?>
                                    <tr>
                                        <td><?= mes_nome($s['mes']) ?></td>
                                        <td><?= $s['ano'] ?></td>
                                        <td><?= number_format($s['selic_acumulada'], 6, ',', '.') ?></td>
                                        <td>
                                            <a href="admin_selic.php?editar=1&mes=<?= $s['mes'] ?>&ano=<?= $s['ano'] ?>" class="btn btn-sm btn-warning me-1"><i class="fas fa-edit"></i></a>
                                            <a href="admin_selic.php?excluir=<?= $s['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Excluir este registro?')"><i class="fas fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            
        </div>
        <?php include 'footer_simple.php'; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
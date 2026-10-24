<?php
session_start();
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Models/Vau.php';
require_once __DIR__ . '/../src/Utils/Database.php';
require_once __DIR__ . '/../src/Utils/LocalConfig.php';

if (!Auth::check() || !Auth::isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$user = Auth::user();

// Listas de opções
$destinacoes = [
    'Casa Popular',
    'Comercial Salas/Lojas',
    'Conj. Hab. Popular',
    'Galpão Ind.',
    'Res. Multifamiliar',
    'Res. Unifamiliar',
    'Edifício de Garagens'
];

$ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

// Processar ações
$message = '';
$messageType = '';

// Importação inteligente via textarea
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dados_colados'])) {
    $texto = $_POST['dados_colados'];
    $linhas = preg_split('/\r\n|\r|\n/', $texto);

    // Encontrar o período
    $periodo = '';
    foreach ($linhas as $linha) {
        if (preg_match('/([A-Z]{3})[\\/\\-](\\d{4})/i', $linha, $m)) {
            $periodo = strtoupper($m[1]) . '/' . $m[2];
            break;
        }
        if (preg_match('/(janeiro|fevereiro|março|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro) de (\\d{4})/i', $linha, $m)) {
            $meses = ['janeiro'=>1,'fevereiro'=>2,'março'=>3,'abril'=>4,'maio'=>5,'junho'=>6,'julho'=>7,'agosto'=>8,'setembro'=>9,'outubro'=>10,'novembro'=>11,'dezembro'=>12];
            $periodo = strtoupper(substr($m[1],0,3)) . '/' . $m[2];
            break;
        }
    }
    
    if (!$periodo) {
        $message = 'Não foi possível identificar o período. Por favor, cole apenas a tabela desejada.';
        $messageType = 'danger';
    } else {
        list($mes_str, $ano) = explode('/', $periodo);
        $meses = ['JAN'=>1,'FEV'=>2,'MAR'=>3,'ABR'=>4,'MAI'=>5,'JUN'=>6,'JUL'=>7,'AGO'=>8,'SET'=>9,'OUT'=>10,'NOV'=>11,'DEZ'=>12];
        $mes = $meses[$mes_str] ?? null;
        
        if (!$mes) {
            $message = "Mês inválido: $mes_str";
            $messageType = 'danger';
        } else {
            // Processar importação
            $destinacoes = [];
            $destinacoes_linha = false;
            foreach ($linhas as $linha) {
                if (preg_match('/Casa Popular/i', $linha)) $destinacoes_linha = true;
                if ($destinacoes_linha && trim($linha) !== '' && !preg_match('/Imprimir/i', $linha)) {
                    $destinacoes[] = trim($linha);
                }
                if ($destinacoes_linha && count($destinacoes) >= 7) break;
            }
            
            if (count($destinacoes) < 2) {
                $message = 'Não foi possível identificar as destinações. Por favor, cole apenas a tabela desejada.';
                $messageType = 'danger';
            } else {
                $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
                $pdo = Database::getConnection();
                $importados = 0;
                
                for ($i = 0; $i < count($linhas); $i++) {
                    $linha = trim($linhas[$i]);
                    if (in_array($linha, $ufs)) {
                        $uf = $linha;
                        $valores = [];
                        for ($j = 1; $j <= count($destinacoes); $j++) {
                            if (isset($linhas[$i+$j])) {
                                $valor_str = trim($linhas[$i+$j]);
                                $valor_str = str_replace(['.', ' '], '', $valor_str);
                                $valor_str = str_replace(',', '.', $valor_str);
                                $valor = floatval($valor_str);
                                $valores[] = $valor;
                            }
                        }
                        if (count($valores) == count($destinacoes)) {
                            foreach ($destinacoes as $k => $destinacao) {
                                $valor = $valores[$k];
                                if ($valor !== null && is_numeric($valor)) {
                                    $stmt = $pdo->prepare("INSERT INTO vau (uf, destinacao, mes, ano, valor) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
                                    $stmt->execute([$uf, $destinacao, $mes, $ano, $valor]);
                                    $importados++;
                                }
                            }
                        }
                    }
                }
                $message = "$importados registros importados com sucesso!";
                $messageType = 'success';
            }
        }
    }
}

// Adicionar/Editar manual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $pdo = Database::getConnection();
    $uf = $_POST['uf'] ?? '';
    $destinacao = $_POST['destinacao'] ?? '';    
    $mes = intval($_POST['mes'] ?? 1);
    $ano = intval($_POST['ano'] ?? date('Y'));
    $valor = str_replace(',', '.', $_POST['valor'] ?? '0');
    
    if ($_POST['acao'] === 'adicionar') {
        $stmt = $pdo->prepare("INSERT INTO vau (uf, destinacao, mes, ano, valor) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$uf, $destinacao, $mes, $ano, $valor])) {
            $message = 'VAU adicionado com sucesso!';
            $messageType = 'success';
        } else {
            $message = 'Erro ao adicionar VAU.';
            $messageType = 'danger';
        }
    } elseif ($_POST['acao'] === 'editar' && isset($_POST['id'])) {
        $stmt = $pdo->prepare("UPDATE vau SET uf = ?, destinacao = ?, mes = ?, ano = ?, valor = ? WHERE id = ?");
        if ($stmt->execute([$uf, $destinacao, $mes, $ano, $valor, $_POST['id']])) {
            $message = 'VAU atualizado com sucesso!';
            $messageType = 'success';
        } else {
            $message = 'Erro ao atualizar VAU.';
            $messageType = 'danger';
        }
    }
}

// Excluir
if (isset($_GET['excluir'])) {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("DELETE FROM vau WHERE id = ?");
    if ($stmt->execute([$_GET['excluir']])) {
        $message = 'VAU excluído com sucesso!';
        $messageType = 'success';
    } else {
        $message = 'Erro ao excluir VAU.';
        $messageType = 'danger';
    }
}

// Upload CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
    if (is_uploaded_file($_FILES['csv']['tmp_name'])) {
        $pdo = Database::getConnection();
        $handle = fopen($_FILES['csv']['tmp_name'], 'r');
        $importados = 0;
        if ($handle) {
            fgetcsv($handle, 1000, ';'); // Ignora cabeçalho
            while (($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
                // Formato esperado: UF;DESTINACAO;MES;ANO;VALOR
                if (count($data) < 5) continue;
                
                $uf = trim($data[0]);
                $destinacao = trim($data[1]);
                $mes = intval($data[2]);
                $ano = intval($data[3]);
                $valor = str_replace(['.', ' '], '', trim($data[4]));
                $valor = str_replace(',', '.', $valor);
                $valor = floatval($valor);

                if ($uf && $destinacao && $mes && $ano) {
                     $stmt = $pdo->prepare(
                        "INSERT INTO vau (uf, destinacao, mes, ano, valor) 
                         VALUES (?, ?, ?, ?, ?) 
                         ON DUPLICATE KEY UPDATE valor = VALUES(valor)"
                    );
                    if ($stmt->execute([$uf, $destinacao, $mes, $ano, $valor])) {
                        $importados++;
                    }
                }
            }
            fclose($handle);
        }
        $message = "$importados registros importados do CSV!";
        $messageType = 'success';
    } else {
        $message = 'Falha no upload do CSV.';
        $messageType = 'danger';
    }
}

// Editar registro
$registro_editar = null;
if (isset($_GET['editar'])) {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("SELECT * FROM vau WHERE id = ?");
    $stmt->execute([$_GET['editar']]);
    $registro_editar = $stmt->fetch();
}

// Filtros e listagem
$where = [];
$params = [];

if (!empty($_GET['filtro_ano'])) {
    $where[] = 'ano = ?';
    $params[] = intval($_GET['filtro_ano']);
}
if (!empty($_GET['filtro_mes'])) {
    $where[] = 'mes = ?';
    $params[] = intval($_GET['filtro_mes']);
}
if (!empty($_GET['filtro_uf'])) {
    $where[] = 'uf = ?';
    $params[] = $_GET['filtro_uf'];
}
if (!empty($_GET['filtro_destinacao'])) {
    $where[] = 'destinacao LIKE ?';
    $params[] = '%' . $_GET['filtro_destinacao'] . '%';
}

$sql = "SELECT * FROM vau";
if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY uf, destinacao, ano DESC, mes DESC";

$pdo = Database::getConnection();
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vau_list = $stmt->fetchAll();

// Estatísticas
$stats = [
    'total_registros' => count($vau_list),
    'ufs_cobertas' => count(array_unique(array_column($vau_list, 'uf'))),
    'destinacoes_unicas' => count(array_unique(array_column($vau_list, 'destinacao'))),
    'valor_medio' => $vau_list ? array_sum(array_column($vau_list, 'valor')) / count($vau_list) : 0
];

// Dados para gráficos
$dadosPorUF = [];
$dadosPorDestinacao = [];
foreach ($vau_list as $vau) {
    $dadosPorUF[$vau['uf']] = ($dadosPorUF[$vau['uf']] ?? 0) + 1;
    $dadosPorDestinacao[$vau['destinacao']] = ($dadosPorDestinacao[$vau['destinacao']] ?? 0) + 1;
}
arsort($dadosPorUF);
arsort($dadosPorDestinacao);
$dadosPorUF = array_slice($dadosPorUF, 0, 10, true);
$dadosPorDestinacao = array_slice($dadosPorDestinacao, 0, 8, true);

$ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

// Carregar configurações
$config = LocalConfig::getAll();

// Cache-busting para logo e favicon
$baseUrl = LocalConfig::getBaseUrl();
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
    <title>Administração - Tabela VAU</title>
    <?php if (!empty($faviconFile)): ?>
    <link rel="icon" type="image/png" href="<?php echo LocalConfig::buildAssetUrl($faviconFile); ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <?php echo LocalConfig::getCustomCSS(); ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <i class="fas fa-table me-2"></i>Gestão VAU
                    </div>
                </div>
                <div class="list-group list-group-flush">
                    <a href="javascript:void(0);" onclick="window.history.back();" class="list-group-item list-group-item-action bg-dark text-white">
                        <i class="fas fa-arrow-left me-2"></i><span class="sidebar-text">Voltar</span>
                    </a>
                    <a href="dashboard.php" class="list-group-item list-group-item-action bg-dark text-white">
                        <i class="fas fa-tachometer-alt me-2"></i><span class="sidebar-text">Dashboard</span>
                    </a>
                    <a href="admin_vau.php" class="list-group-item list-group-item-action bg-dark text-white active">
                        <i class="fas fa-table me-2"></i><span class="sidebar-text">Tabela VAU</span>
                    </a>

                    <a href="#importacao" class="list-group-item list-group-item-action bg-dark text-white" onclick="scrollToSection('importacao')">
                        <i class="fas fa-upload me-2"></i><span class="sidebar-text">Importação</span>
                    </a>
                    <a href="#gestao" class="list-group-item list-group-item-action bg-dark text-white" onclick="scrollToSection('gestao')">
                        <i class="fas fa-edit me-2"></i><span class="sidebar-text">Gestão Manual</span>
                    </a>
                    <a href="#listagem" class="list-group-item list-group-item-action bg-dark text-white" onclick="scrollToSection('listagem')">
                        <i class="fas fa-list me-2"></i><span class="sidebar-text">Listagem</span>
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
                        <i class="fas fa-table me-2"></i>Gestão da Tabela VAU
                    </h1>
                    <div>
                        <button class="btn btn-outline-success" onclick="exportarVAU()">
                            <i class="fas fa-download me-2"></i>Exportar CSV
                        </button>
                    </div>
                </div>

                <!-- Mensagens -->
                <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Cards de Estatísticas -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total de Registros</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['total_registros'] ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-database fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">UFs Cobertas</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['ufs_cobertas'] ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-map fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Tipos de Destinação</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['destinacoes_unicas'] ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-building fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Valor Médio</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">R$ <?= number_format($stats['valor_medio'], 2, ',', '.') ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calculator fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficos -->
                <div class="row mb-4">
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-primary text-white py-3">
                                <h6 class="m-0 font-weight-bold text-white">Top 10 UFs por Registros</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="chartUFs" style="height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header bg-primary text-white py-3">
                                <h6 class="m-0 font-weight-bold text-white">Distribuição por Destinação</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="chartDestinacoes" style="height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Importação -->
                <div class="card shadow mb-4" id="importacao">
                    <div class="card-header bg-primary text-white py-3">
                        <h6 class="m-0 font-weight-bold text-white">Importação de Dados</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-6">
                                <h6>Importação Inteligente (Colar Tabela)</h6>
                                <p class="text-muted small">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Acesse a <a href="https://www.tabelavau.seroassessoria.com.br/tabela_vau.php" target="_blank">Tabela VAU online</a>, copie os dados e cole no campo abaixo.
                                </p>
                                <form method="post">
                                    <div class="mb-3">
                                        <label class="form-label">Cole aqui os dados copiados da tabela VAU:</label>
                                        <textarea name="dados_colados" rows="8" class="form-control" placeholder="Cole aqui a tabela completa incluindo cabeçalhos e período..."></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-magic me-2"></i>Importar Tabela Colada
                                    </button>
                                </form>
                                <div class="form-text mt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    O sistema identifica automaticamente o período, tipos de obra e valores.
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <h6>Upload de Arquivo CSV</h6>
                                <form method="post" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <label class="form-label">Arquivo CSV:</label>
                                        <input type="file" name="csv" accept=".csv" class="form-control" required>
                                    </div>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-upload me-2"></i>Importar CSV
                                    </button>
                                </form>
                                <div class="form-text mt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Formato: UF;DESTINACAO;MES;ANO;VALOR
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gestão Manual -->
                <div class="card shadow mb-4" id="gestao">
                    <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-white">
                            <?= $registro_editar ? 'Editar VAU' : 'Adicionar VAU' ?>
                        </h6>
                        <?php if ($registro_editar): ?>
                            <a href="admin_vau.php" class="btn btn-sm btn-secondary">
                                <i class="fas fa-times me-1"></i>Cancelar Edição
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="acao" value="<?= $registro_editar ? 'editar' : 'adicionar' ?>">
                            <?php if ($registro_editar): ?>
                                <input type="hidden" name="id" value="<?= $registro_editar['id'] ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-2">
                                    <label class="form-label">UF</label>
                                    <select class="form-select" name="uf" required>
                                        <option value="">Selecione</option>
                                        <?php foreach ($ufs as $uf): ?>
                                            <option value="<?= $uf ?>" <?= ($registro_editar && $registro_editar['uf'] == $uf) ? 'selected' : '' ?>>
                                                <?= $uf ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">Destinação</label>
                                    <select class="form-select" name="destinacao" required>
                                        <option value="">Selecione a destinação</option>
                                        <?php foreach ($destinacoes as $destinacao): ?>
                                            <option value="<?= $destinacao ?>" <?= ($registro_editar && $registro_editar['destinacao'] == $destinacao) ? 'selected' : '' ?>>
                                                <?= $destinacao ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-2">
                                    <label class="form-label">Mês</label>
                                    <select class="form-select" name="mes" required>
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                            <option value="<?= $m ?>" <?= ($registro_editar && $registro_editar['mes'] == $m) ? 'selected' : '' ?>>
                                                <?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-2">
                                    <label class="form-label">Ano</label>
                                    <input type="number" class="form-control" name="ano" min="2000" max="2100" required 
                                           value="<?= $registro_editar['ano'] ?? date('Y') ?>">
                                </div>
                                
                                <div class="col-md-2">
                                    <label class="form-label">Valor</label>
                                    <input type="number" class="form-control" name="valor" step="0.01" min="0" required 
                                           value="<?= $registro_editar['valor'] ?? '' ?>"
                                           placeholder="0,00">
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>
                                    <?= $registro_editar ? 'Atualizar' : 'Adicionar' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Filtros e Listagem -->
                <div class="card shadow mb-4" id="listagem">
                    <div class="card-header bg-primary text-white py-3">
                        <h6 class="m-0 font-weight-bold text-white">VAUs Cadastrados</h6>
                    </div>
                    <div class="card-body">
                        <!-- Filtros -->
                        <form method="get" class="row g-2 mb-4">
                            <div class="col-md-2">
                                <label class="form-label">UF:</label>
                                <select class="form-select" name="filtro_uf">
                                    <option value="">Todas</option>
                                    <?php foreach ($ufs as $uf): ?>
                                        <option value="<?= $uf ?>" <?= (isset($_GET['filtro_uf']) && $_GET['filtro_uf'] == $uf) ? 'selected' : '' ?>>
                                            <?= $uf ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">Destinação:</label>
                                <select class="form-select" name="filtro_destinacao">
                                    <option value="">Todas</option>
                                    <?php foreach ($destinacoes as $destinacao): ?>
                                        <option value="<?= $destinacao ?>" <?= (isset($_GET['filtro_destinacao']) && $_GET['filtro_destinacao'] == $destinacao) ? 'selected' : '' ?>>
                                            <?= $destinacao ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">Ano:</label>
                                <input type="number" class="form-control" name="filtro_ano" min="2000" max="2100" 
                                       value="<?= htmlspecialchars($_GET['filtro_ano'] ?? '') ?>">
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">Mês:</label>
                                <select class="form-select" name="filtro_mes">
                                    <option value="">Todos</option>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?= $m ?>" <?= (isset($_GET['filtro_mes']) && $_GET['filtro_mes'] == $m) ? 'selected' : '' ?>>
                                            <?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search me-1"></i>Filtrar
                                </button>
                                <a href="admin_vau.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i>Limpar
                                </a>
                            </div>
                        </form>

                        <!-- Tabela -->
                        <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-dark sticky-top">
                                    <tr>
                                        <th>UF</th>
                                        <th>Destinação</th>
                                        <th>Mês</th>
                                        <th>Ano</th>
                                        <th>Valor</th>
                                        <th class="text-center">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($vau_list)): ?>
                                        <?php foreach ($vau_list as $v): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($v['uf']) ?></strong></td>
                                                <td><?= htmlspecialchars($v['destinacao']) ?></td>
                                                <td><?= str_pad($v['mes'], 2, '0', STR_PAD_LEFT) ?></td>
                                                <td><?= $v['ano'] ?></td>
                                                <td>R$ <?= number_format($v['valor'], 2, ',', '.') ?></td>
                                                <td class="text-center">
                                                    <div class="btn-group" role="group">
                                                        <a href="?editar=<?= $v['id'] ?>#gestao" class="btn btn-outline-primary btn-sm" title="Editar">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="?excluir=<?= $v['id'] ?>" class="btn btn-outline-danger btn-sm" 
                                                           title="Excluir" onclick="return confirm('Tem certeza que deseja excluir este VAU?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <div class="text-muted">
                                                    <i class="fas fa-inbox fa-3x mb-3"></i>
                                                    <p class="mt-2">Nenhum VAU encontrado com os filtros aplicados.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (!empty($vau_list)): ?>
                            <div class="mt-3 text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Mostrando <?= count($vau_list) ?> registro(s)
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        <?php include 'footer_simple.php'; ?>
        </div>
        <!-- /#page-content-wrapper -->
    </div>
    <!-- /#wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Dados para gráficos
        var dadosPorUF = <?php echo json_encode($dadosPorUF); ?>;
        var dadosPorDestinacao = <?php echo json_encode($dadosPorDestinacao); ?>;

        // Gráfico de UFs
        var ctx1 = document.getElementById('chartUFs').getContext('2d');
        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: Object.keys(dadosPorUF),
                datasets: [{
                    label: 'Registros',
                    data: Object.values(dadosPorUF),
                    backgroundColor: 'rgba(54, 162, 235, 0.8)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Gráfico de Destinações
        var ctx2 = document.getElementById('chartDestinacoes').getContext('2d');
        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: Object.keys(dadosPorDestinacao),
                datasets: [{
                    data: Object.values(dadosPorDestinacao),
                    backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796', '#5a5c69'],
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });

        function exportarVAU() {
            // Criar CSV dos dados atuais
            var csv = 'UF;Destinação;Mês;Ano;Valor\n';
            <?php foreach ($vau_list as $v): ?>
            csv += '<?= $v['uf'] ?>;<?= addslashes($v['destinacao']) ?>;<?= $v['mes'] ?>;<?= $v['ano'] ?>;<?= $v['valor'] ?>\n';
            <?php endforeach; ?>
            
            var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement('a');
            var url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'vau_export_' + new Date().toISOString().slice(0,10) + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function scrollToSection(id) {
            document.getElementById(id).scrollIntoView({ behavior: 'smooth' });
        }
    </script>
    
</body>
</html>


<?php
session_start();
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Models/User.php';
require_once __DIR__ . '/../src/Utils/Database.php';
require_once __DIR__ . '/../src/Utils/LocalConfig.php';

// Redireciona se não estiver logado
if (!Auth::check()) {
    header('Location: login.php');
    exit;
}

$user = Auth::user();
$msg = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = Database::getConnection();
    $updated_fields = [];
    
    // Atualizar nome
    if (isset($_POST['nome']) && $_POST['nome'] !== $user['nome']) {
        User::update($user['id'], ['nome' => $_POST['nome']]);
        $updated_fields[] = 'Nome';
        $user['nome'] = $_POST['nome'];
        $_SESSION['user']['nome'] = $_POST['nome'];
    }
    
    // Atualizar telefone
    if (isset($_POST['telefone']) && $_POST['telefone'] !== ($user['telefone'] ?? '')) {
        // Validação básica do telefone e sanitização
        $telefone = $_POST['telefone'];
        $telefone_numeros = preg_replace('/\D/', '', $telefone);
        
        if (!empty($telefone) && (strlen($telefone_numeros) < 10 || strlen($telefone_numeros) > 11)) {
            $msg = 'Telefone deve ter 10 ou 11 dígitos (com DDD)!';
            $success = false;
        } else {
            // Salvar apenas números para manter consistência
            User::update($user['id'], ['telefone' => $telefone_numeros]);
            $updated_fields[] = 'Telefone';
            $user['telefone'] = $telefone_numeros;
            $_SESSION['user']['telefone'] = $telefone_numeros;
        }
    }
    
    // Definir mensagem de sucesso se houver campos atualizados e não houver erro
    if (!empty($updated_fields) && !isset($msg)) {
        $msg = implode(' e ', $updated_fields) . ' atualizado' . (count($updated_fields) > 1 ? 's' : '') . ' com sucesso!';
        $success = true;
    }
    // Atualizar senha
    if (!empty($_POST['senha_atual']) && !empty($_POST['nova_senha']) && !empty($_POST['confirmar_senha'])) {
        if ($_POST['nova_senha'] !== $_POST['confirmar_senha']) {
            $msg = 'A nova senha e a confirmação não coincidem!';
        } else {
            // Verifica senha atual
            if (password_verify($_POST['senha_atual'], $user['senha_hash'])) {
                User::update($user['id'], ['senha' => $_POST['nova_senha']]);
                $msg = 'Senha alterada com sucesso!';
                $success = true;
            } else {
                $msg = 'Senha atual incorreta!';
            }
        }
    }
}

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
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - Simulação INSS</title>
    <?php if (!empty($faviconFile)): ?>
    <link rel="icon" type="image/png" href="<?php echo LocalConfig::buildAssetUrl($faviconFile); ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <?php echo LocalConfig::getCustomCSS(); ?>
</head>
<body>
    <div id="wrapper">
        <!-- Sidebar -->
        <div id="sidebar-wrapper">
            <div class="sidebar-content-wrapper">
                <div class="sidebar-heading">
                    <?php if (!empty($logoFile)): ?>
                        <img class="sidebar-logo" src="<?php echo LocalConfig::buildAssetUrl($logoFile); ?>" alt="Logo" style="max-width:180px;max-height:80px;object-fit:contain;background:#fff;padding:8px 12px;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.1);">
                    <?php else: ?>
                        <span class="fw-bold"><?php echo htmlspecialchars($config['system']['name'] ?? 'Calculadora INSS Obra Legal ®'); ?></span>
                    <?php endif; ?>
                </div>
                <div class="list-group list-group-flush">
                    <a href="dashboard.php" class="list-group-item list-group-item-action bg-dark text-white">
                        <i class="fas fa-tachometer-alt me-2"></i><span class="sidebar-text">Dashboard</span>
                    </a>
                    <a href="nova_simulacao.php" class="list-group-item list-group-item-action bg-dark text-white">
                        <i class="fas fa-calculator me-2"></i><span class="sidebar-text">Nova Simulação</span>
                    </a>
                    <a href="minhas_simulacoes.php" class="list-group-item list-group-item-action bg-dark text-white">
                        <i class="fas fa-list me-2"></i><span class="sidebar-text">Minhas Simulações</span>
                    </a>
                    <a href="perfil.php" class="list-group-item list-group-item-action bg-dark text-white active">
                        <i class="fas fa-user me-2"></i><span class="sidebar-text">Meu Perfil</span>
                    </a>
                    <?php if (Auth::isAdmin()): ?>
                    <div class="dropdown-divider bg-secondary"></div>
                    <a href="admin.php" class="list-group-item list-group-item-action bg-dark text-white">
                        <i class="fas fa-user-shield me-2"></i><span class="sidebar-text">Área Admin</span>
                    </a>
                    <?php endif; ?>
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
                                <i class="fas fa-user me-2"></i><?= htmlspecialchars($user['nome']) ?>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <a class="dropdown-item" href="dashboard.php">Dashboard</a>
                                <a class="dropdown-item" href="minhas_simulacoes.php">Minhas Simulações</a>
                                <a class="dropdown-item" href="<?= Auth::isAdmin() ? 'admin.php' : 'perfil.php' ?>">Perfil</a>
                                <?php if (Auth::isAdmin()): ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="admin.php">Área Admin</a>
                                <?php endif; ?>
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
                        <i class="fas fa-user me-2"></i>
                        Meu Perfil
                    </h1>
                    <div>
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-home me-2"></i>Dashboard
                        </a>
                    </div>
                </div>

                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="card shadow">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-user-edit me-2"></i>
                                    Informações do Perfil
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if ($msg): ?>
                                    <div class="alert alert-<?= $success ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                                        <i class="fas fa-<?= $success ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                                        <?= htmlspecialchars($msg) ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <form method="post">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label for="nome" class="form-label fw-bold">
                                                    <i class="fas fa-user me-1"></i>Nome Completo
                                                </label>
                                                <input type="text" id="nome" name="nome" class="form-control" 
                                                       value="<?= htmlspecialchars($user['nome']) ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="email" class="form-label fw-bold">
                                                    <i class="fas fa-envelope me-1"></i>E-mail
                                                </label>
                                                <input type="email" id="email" class="form-control" 
                                                       value="<?= htmlspecialchars($user['email']) ?>" disabled>
                                                <small class="text-muted">O e-mail não pode ser alterado</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="telefone" class="form-label fw-bold">
                                                    <i class="fab fa-whatsapp me-1"></i>Telefone (WhatsApp)
                                                </label>
                                                <input type="tel" id="telefone" name="telefone" class="form-control" 
                                                       value="<?= htmlspecialchars($user['telefone'] ?? '') ?>" 
                                                       placeholder="(11) 99999-9999">
                                                <small class="text-muted">Digite seu telefone com WhatsApp para contato</small>
                                            </div>
                                        </div>
                                    </div>

                                    <hr class="my-4">

                                    <div class="row">
                                        <div class="col-12">
                                            <h6 class="mb-3 text-primary">
                                                <i class="fas fa-lock me-2"></i>
                                                Alterar Senha
                                            </h6>
                                            <small class="text-muted mb-3 d-block">
                                                Deixe os campos de senha em branco se não quiser alterar a senha
                                            </small>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="senha_atual" class="form-label fw-bold">
                                                    <i class="fas fa-key me-1"></i>Senha Atual
                                                </label>
                                                <input type="password" id="senha_atual" name="senha_atual" 
                                                       class="form-control" placeholder="Digite sua senha atual">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="nova_senha" class="form-label fw-bold">
                                                    <i class="fas fa-lock me-1"></i>Nova Senha
                                                </label>
                                                <input type="password" id="nova_senha" name="nova_senha" 
                                                       class="form-control" placeholder="Digite a nova senha">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="confirmar_senha" class="form-label fw-bold">
                                                    <i class="fas fa-check-circle me-1"></i>Confirmar Nova Senha
                                                </label>
                                                <input type="password" id="confirmar_senha" name="confirmar_senha" 
                                                       class="form-control" placeholder="Confirme a nova senha">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-end mt-4">
                                        <button type="button" class="btn btn-secondary" onclick="window.history.back();"><i class="fas fa-times me-1"></i>Cancelar</button>
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-save me-1"></i>Salvar Alterações
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Zona de Perigo -->
                <div class="row justify-content-center mt-4">
                    <div class="col-lg-8">
                        <div class="card shadow border-danger">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Zona de Perigo
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="card-text">
                                    A exclusão da sua conta é uma ação irreversível. Todos os seus dados, incluindo simulações e planejamentos tributários, serão permanentemente removidos.
                                </p>
                                <p class="text-muted small mb-3">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Você tem direito à exclusão dos seus dados conforme a <a href="direitos-lgpd.php" target="_blank">LGPD</a>.
                                </p>
                                <form action="excluir_cadastro.php" method="post" onsubmit="return confirm('Tem certeza que deseja excluir sua conta? Esta ação não pode ser desfeita.');">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-trash-alt me-2"></i>
                                        Excluir Minha Conta
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Fim da Zona de Perigo -->

            </div>
            <?php include 'footer_simple.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Função para formatar telefone
        function formatarTelefone(numero) {
            if (!numero) return '';
            let value = numero.replace(/\D/g, '');
            
            if (value.length <= 11) {
                if (value.length <= 2) {
                    value = value.replace(/(\d{0,2})/, '($1');
                } else if (value.length <= 7) {
                    value = value.replace(/(\d{2})(\d{0,5})/, '($1) $2');
                } else {
                    value = value.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
                }
            }
            return value;
        }
        
        // Formatar telefone na inicialização da página
        document.addEventListener('DOMContentLoaded', function() {
            const telefoneField = document.getElementById('telefone');
            if (telefoneField && telefoneField.value) {
                telefoneField.value = formatarTelefone(telefoneField.value);
            }
        });
        
        // Máscara para telefone
        document.getElementById('telefone').addEventListener('input', function(e) {
            e.target.value = formatarTelefone(e.target.value);
        });
        
        // Validação adicional no submit
        document.querySelector('form').addEventListener('submit', function(e) {
            const telefone = document.getElementById('telefone').value.replace(/\D/g, '');
            
            if (telefone.length > 0 && (telefone.length < 10 || telefone.length > 11)) {
                e.preventDefault();
                alert('Por favor, digite um telefone válido com DDD ou deixe o campo vazio.');
                return false;
            }
        });
        
        // Toggle do sidebar (se existir)
        const sidebarToggle = document.getElementById('sidebar-toggle-btn');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                const wrapper = document.getElementById('wrapper');
                wrapper.classList.toggle('toggled');
                
                const icon = this.querySelector('i');
                if (wrapper.classList.contains('toggled')) {
                    icon.classList.remove('fa-angle-double-left');
                    icon.classList.add('fa-angle-double-right');
                } else {
                    icon.classList.remove('fa-angle-double-right');
                    icon.classList.add('fa-angle-double-left');
                }
            });
        }
    </script>
    
</body>
</html> 
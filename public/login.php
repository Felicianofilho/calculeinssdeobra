<?php
// Incluir configurações de segurança
require_once __DIR__ . '/../config/security.php';

session_start();
require_once __DIR__ . '/../src/Controllers/AuthController.php';
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Utils/LocalConfig.php';
require_once __DIR__ . '/../src/Utils/PerformanceControl.php';
require_once __DIR__ . '/../src/Utils/UserActivity.php';

// Se já estiver logado, redireciona para o dashboard
if (Auth::check()) {
    header("Location: dashboard.php");
    exit;
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Login não deve ter delay (já está na lista de operações sem delay)
    
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $result = AuthController::login($email, $senha);
    if ($result === true) {
        // Atualizar atividade do usuário após login
        UserActivity::updateActivity();
        header("Location: dashboard.php");
        exit;
    } else {
        $msg = $result;
    }
}

$config = LocalConfig::getAll();

// Cache-busting para logo e favicon
$baseUrl = LocalConfig::getBaseUrl();
$faviconFile = $config['theme']['branding']['favicon_file'] ?? '';
$logoFile = $config['theme']['branding']['logo_file'] ?? '';

// Verificar se os arquivos existem e são acessíveis
$logoFilePath = !empty($logoFile) ? __DIR__ . '/' . $logoFile : '';
$logoVersion = '';
if ($logoFilePath && file_exists($logoFilePath) && is_readable($logoFilePath)) {
    $logoVersion = '?v=' . filemtime($logoFilePath);
}

$faviconFilePath = !empty($faviconFile) ? __DIR__ . '/' . $faviconFile : '';
$faviconVersion = '';
if ($faviconFilePath && file_exists($faviconFilePath) && is_readable($faviconFilePath)) {
    $faviconVersion = '?v=' . filemtime($faviconFilePath);
}

// Usar buildAssetUrl para assets
$logoUrl = LocalConfig::buildAssetUrl($logoFile);
$faviconUrl = LocalConfig::buildAssetUrl($faviconFile);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Login - Calculadora INSS Obra Legal ®</title>
    <?php if (!empty($faviconFile)): ?>
    <link rel="icon" type="image/png" href="<?php echo $faviconUrl; ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .login-container {
            max-width: 450px;
            margin: 0 auto;
        }
    </style>
<?php echo LocalConfig::getCustomCSS(); ?>
</head>
<body class="login-bg">
    <div class="login-container">
        <div class="text-center my-4">
            <?php if (!empty($logoFile)): ?>
                <img src="<?php echo $logoUrl; ?>" alt="Logo" style="max-width:180px;max-height:80px;object-fit:contain;background:#fff;padding:8px 12px;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.1);">
            <?php else: ?>
                <span class="fw-bold fs-4"><?php echo htmlspecialchars($config['system']['name'] ?? 'Calculadora INSS Obra Legal ®'); ?></span>
            <?php endif; ?>
        </div>
        <div class="card shadow">
            <div class="card-body p-5">
                <h2 class="text-center mb-4">Login</h2>
        <?php if ($msg): ?>
                    <div class="alert alert-danger"><?php echo $msg; ?></div>
        <?php endif; ?>
        <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                    <div class="mb-3">
                        <label class="form-label">E-mail</label>
                        <input type="email" name="email" class="form-control" required autocomplete="username">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Senha</label>
                        <input type="password" name="senha" class="form-control" required autocomplete="current-password">
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Entrar</button>
                    </div>
        </form>
                <div class="text-center mt-3">
                    <a href="register.php" class="text-decoration-none">Criar conta</a>
                    <br>
                    <a href="reset_senha.php" class="text-decoration-none">Esqueci minha senha?</a>
                </div>
            </div>
        </div>
        <footer class="text-center mt-4 text-muted">&copy; <?php echo date('Y'); ?> Calculadora INSS Obra Legal ®</footer>
    </div>

    <?php include 'includes/cookie_banner.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
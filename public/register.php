<?php
// Caminho: /public/register.php

session_start();
require_once __DIR__ . '/../src/Controllers/AuthController.php';
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Utils/LocalConfig.php';

// Se já estiver logado, redireciona
if (Auth::check()) {
    header("Location: dashboard.php");
    exit;
}

// Carregar configurações e definir base URL
$config = LocalConfig::getAll();
$baseUrl = LocalConfig::getBaseUrl();

// Cache-busting para logo e favicon
$baseUrl = LocalConfig::getBaseUrl();
$faviconFile = $config['theme']['branding']['favicon_file'] ?? '';
$logoFile = $config['theme']['branding']['logo_file'] ?? '';

// Usar buildAssetUrl para assets
$logoUrl = LocalConfig::buildAssetUrl($logoFile);
$faviconUrl = LocalConfig::buildAssetUrl($faviconFile);

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $email = $_POST['email'] ?? '';
    $telefone = $_POST['telefone'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $aceite_termos = isset($_POST['aceite_termos']) && $_POST['aceite_termos'] === 'on';
    
    // Validar aceite de termos
    if (!$aceite_termos) {
        $msg = 'Você deve aceitar os Termos de Uso e Política de Privacidade para se cadastrar.';
    } else {
        $result = AuthController::register($nome, $email, $telefone, $senha, $aceite_termos);
        if ($result === true) {
            // Não redireciona automaticamente, mostra mensagem de confirmação
            $msg = 'Cadastro realizado! Verifique seu e-mail para confirmar o cadastro.';
        } else {
            // $result contém a mensagem de erro
            $msg = $result;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Cadastro - Calculadora INSS Obra Legal ®</title>
    <?php if (!empty($faviconFile)): ?>
    <link rel="icon" type="image/png" href="<?php echo $faviconUrl; ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .register-container {
            max-width: 500px;
            margin: 0 auto;
        }
    </style>
<?php echo LocalConfig::getCustomCSS(); ?>
</head>
<body class="login-bg">
    <div class="register-container">
        <div class="text-center my-4">
            <?php if (!empty($logoFile)): ?>
                <img src="<?php echo $logoUrl; ?>" alt="Logo" style="max-width:180px;max-height:80px;object-fit:contain;background:#fff;padding:8px 12px;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.1);">
            <?php else: ?>
                <span class="fw-bold fs-4"><?php echo htmlspecialchars($config['system']['name'] ?? 'Calculadora INSS Obra Legal ®'); ?></span>
            <?php endif; ?>
        </div>
        <div class="card shadow">
            <div class="card-body p-5">
                <h2 class="text-center mb-4">Criar Conta</h2>
                <?php if ($msg) : ?>
                    <div class="alert alert-<?php echo (strpos($msg, 'Cadastro realizado') !== false) ? 'success' : 'danger'; ?>">
                        <?php echo $msg; ?>
                        <?php if (strpos($msg, 'E-mail já cadastrado') !== false) : ?>
                            <br><a href="reset_senha.php" class="alert-link">Esqueci minha senha?</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <form method="post">
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome</label>
                        <input type="text" id="nome" name="nome" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="telefone" class="form-label">Telefone (WhatsApp)</label>
                        <input type="tel" id="telefone" name="telefone" class="form-control" placeholder="(11) 99999-9999" required>
                        <div class="form-text">Digite seu telefone com WhatsApp para contato.</div>
                    </div>
                    <div class="mb-3">
                        <label for="senha" class="form-label">Senha</label>
                        <input type="password" id="senha" name="senha" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="aceite_termos" name="aceite_termos" required>
                            <label class="form-check-label" for="aceite_termos">
                                Li e concordo com os <a href="termos.php" target="_blank">Termos de Uso</a> e <a href="privacidade.php" target="_blank">Política de Privacidade</a>
                            </label>
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Cadastrar</button>
                    </div>
                </form>
                <div class="text-center mt-3">
                    <a href="login.php" class="text-decoration-none">Já tenho conta</a>
                </div>
            </div>
        </div>
        <footer class="text-center mt-4 text-muted">&copy; <?php echo date('Y'); ?> Calcule INSS de Obra </footer>
    </div>

    <?php include 'includes/cookie_banner.php'; ?>
    <script>
        // Máscara para telefone
        document.getElementById('telefone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.length <= 11) {
                if (value.length <= 2) {
                    value = value.replace(/(\d{0,2})/, '($1');
                } else if (value.length <= 7) {
                    value = value.replace(/(\d{2})(\d{0,5})/, '($1) $2');
                } else {
                    value = value.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
                }
            }
            
            e.target.value = value;
        });
        
        // Validação adicional no submit
        document.querySelector('form').addEventListener('submit', function(e) {
            const telefone = document.getElementById('telefone').value.replace(/\D/g, '');
            const aceiteTermos = document.getElementById('aceite_termos').checked;
            
            if (telefone.length < 10 || telefone.length > 11) {
                e.preventDefault();
                alert('Por favor, digite um telefone válido com DDD.');
                return false;
            }
            
            if (!aceiteTermos) {
                e.preventDefault();
                alert('Você deve aceitar os Termos de Uso e Política de Privacidade para se cadastrar.');
                return false;
            }
        });
    </script>
</body>
</html>
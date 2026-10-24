<?php
require_once __DIR__ . '/../src/Models/User.php';
require_once __DIR__ . '/../src/Utils/Database.php';

$msg = '';
$token = $_GET['token'] ?? '';
$valid = false;

if ($token) {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_token_expira > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if ($user) {
        $valid = true;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $novaSenha = $_POST['senha'] ?? '';
            if (strlen($novaSenha) < 6) {
                $msg = 'A senha deve ter pelo menos 6 caracteres.';
            } else {
                $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
                $update = $pdo->prepare("UPDATE users SET senha_hash = ?, reset_token = NULL, reset_token_expira = NULL, ativo = 1 WHERE id = ?");
                $update->execute([$senhaHash, $user['id']]);
                $msg = 'Senha redefinida com sucesso! <a href="login.php">Ir para login</a>';
                $valid = false;
            }
        }
    } else {
        $msg = 'Token inválido ou expirado.';
    }
} else {
    $msg = 'Token não informado.';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Nova Senha</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="card shadow mx-auto" style="max-width: 400px;">
            <div class="card-body text-center">
                <h2 class="mb-3">Definir Nova Senha</h2>
                <?php if ($msg): ?><div class="alert alert-info"><?php echo $msg; ?></div><?php endif; ?>
                <?php if ($valid): ?>
                <form method="post">
                    <div class="mb-3 text-start">
                        <label class="form-label">Nova senha</label>
                        <input type="password" name="senha" class="form-control" required minlength="6">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Redefinir senha</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html> 
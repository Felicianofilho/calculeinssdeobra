<?php
require_once __DIR__ . '/../src/Models/User.php';
require_once __DIR__ . '/../src/Utils/EmailHelper.php';

$msg = '';
$aviso = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $user = User::findByEmail($email);
    if ($user) {
        $token = bin2hex(random_bytes(32));
        // Salvar token no banco (campo novo: reset_token, reset_token_expira)
        $pdo = Database::getConnection();
        $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expira = ? WHERE id = ?");
        $stmt->execute([$token, $expira, $user['id']]);
        // Enviar e-mail com link de reset
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        $resetLink = $baseUrl . $basePath . '/nova_senha.php?token=' . $token;
        $subject = 'Redefinição de senha - Calculadora INSS Obra Legal';
        $body = '<p>Olá, ' . htmlspecialchars($user['nome']) . '!</p>' .
                '<p>Para redefinir sua senha, clique no link abaixo:</p>' .
                '<p><a href="' . $resetLink . '">' . $resetLink . '</a></p>' .
                '<p>Se não foi você que solicitou, ignore este e-mail.</p>';
        EmailHelper::send($email, $user['nome'], $subject, $body);
        $msg = 'Se o e-mail estiver cadastrado, você receberá um link para redefinir sua senha.';
    } else {
        $msg = 'Se o e-mail estiver cadastrado, você receberá um link para redefinir sua senha.';
    }
    $aviso = true;
} else {
    $aviso = false;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Redefinir Senha</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="card shadow mx-auto" style="max-width: 400px;">
            <div class="card-body text-center">
                <h2 class="mb-3">Redefinir Senha</h2>
                <?php if ($msg): ?><div class="alert alert-info"><?php echo $msg; ?></div><?php endif; ?>
                <?php if (!empty($aviso)): ?>
                <div class="alert alert-success">Se o e-mail informado estiver cadastrado, um link de redefinição de senha foi enviado. Verifique sua caixa de entrada e o spam.</div>
                <?php endif; ?>
                <form method="post">
                    <div class="mb-3 text-start">
                        <label class="form-label">E-mail cadastrado</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Enviar link de redefinição</button>
                </form>
                <a href="login.php" class="d-block mt-3">Voltar ao login</a>
            </div>
        </div>
    </div>
</body>
</html> 
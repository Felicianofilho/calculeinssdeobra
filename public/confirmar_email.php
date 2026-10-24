<?php
require_once __DIR__ . '/../src/Models/User.php';
require_once __DIR__ . '/../src/Utils/Database.php';

$msg = '';
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email_confirm_token = ? AND email_confirmed = 0");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if ($user) {
        $update = $pdo->prepare("UPDATE users SET ativo = 1, email_confirmed = 1, email_confirm_token = NULL WHERE id = ?");
        $update->execute([$user['id']]);
        $msg = 'E-mail confirmado com sucesso! Agora você pode fazer login.';
    } else {
        $msg = 'Token inválido, expirado ou e-mail já confirmado.';
    }
} else {
    $msg = 'Token de confirmação não informado.';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Confirmação de E-mail</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="card shadow mx-auto" style="max-width: 400px;">
            <div class="card-body text-center">
                <h2 class="mb-3">Confirmação de E-mail</h2>
                <p><?php echo $msg; ?></p>
                <a href="login.php" class="btn btn-primary mt-3">Ir para Login</a>
            </div>
        </div>
    </div>
</body>
</html> 
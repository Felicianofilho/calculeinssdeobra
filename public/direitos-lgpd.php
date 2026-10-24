<?php
$empresa = [
  'email' => 'hevconweb@gmail.com'
];

session_start();
require_once __DIR__ . '/../src/Utils/LocalConfig.php';
require_once __DIR__ . '/../src/Utils/Auth.php';
$config = LocalConfig::getAll();
$baseUrl = LocalConfig::getBaseUrl();
$faviconFile = $config['theme']['branding']['favicon_file'] ?? '';
$faviconUrl = LocalConfig::buildAssetUrl($faviconFile);

// Verificar se usuário está logado para mostrar link de exclusão direta
$userLoggedIn = Auth::check();
$user = $userLoggedIn ? Auth::user() : null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Direitos do Titular - LGPD</title>
  <?php if (!empty($faviconFile)): ?>
  <link rel="icon" type="image/png" href="<?php echo $faviconUrl; ?>">
  <?php endif; ?>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
  <link href="assets/style.css" rel="stylesheet">
  <?php echo LocalConfig::getCustomCSS(); ?>
</head>
<body>

<?php include 'header.php'; ?>

<div class="container my-5">
  <div class="card shadow-lg border-0">
    <div class="card-body p-4">
      <h2>Direitos do Titular de Dados</h2>

      <p>Você pode solicitar:</p>
      <ul>
        <li>Acesso aos seus dados</li>
        <li>Correção de dados</li>
        <li>Exclusão da sua conta e dos seus dados</li>
        <li>Informações sobre uso dos dados</li>
      </ul>

      <h5>Como solicitar</h5>
      <p>
        Envie um e-mail para <strong><?= $empresa['email'] ?></strong> informando:
      </p>
      <ul>
        <li>Seu e-mail cadastrado no sistema</li>
        <li>O tipo de solicitação</li>
      </ul>

      <?php if ($userLoggedIn): ?>
      <div class="alert alert-info mt-4">
        <h6><i class="fas fa-user-check me-2"></i>Você está logado</h6>
        <p class="mb-2">Como você está autenticado, você pode:</p>
        <ul class="mb-2">
          <li>Visualizar seus dados em <a href="perfil.php" class="alert-link">Meu Perfil</a></li>
          <li>Excluir sua conta diretamente através do seu perfil</li>
        </ul>
        <p class="mb-0">
          <a href="perfil.php" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-user me-1"></i>Acessar Meu Perfil
          </a>
        </p>
      </div>
      <?php else: ?>
      <p class="text-muted mt-4">
        Por segurança, podemos solicitar confirmação de identidade.
      </p>
      <?php endif; ?>

      <h5 class="mt-4">Links relacionados</h5>
      <ul>
        <li><a href="privacidade.php">Política de Privacidade</a></li>
        <li><a href="termos.php">Termos de Uso</a></li>
        <li><a href="cookies.php">Política de Cookies</a></li>
      </ul>
    </div>
  </div>
</div>

<?php include 'footer_simple.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

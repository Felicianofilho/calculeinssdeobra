<?php
$dataAtualizacao = '26/01/2026';

session_start();
require_once __DIR__ . '/../src/Utils/LocalConfig.php';
$config = LocalConfig::getAll();
$baseUrl = LocalConfig::getBaseUrl();
$faviconFile = $config['theme']['branding']['favicon_file'] ?? '';
$faviconUrl = LocalConfig::buildAssetUrl($faviconFile);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Política de Cookies - Calculadora INSS Obra Legal</title>
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
      <h2 class="mb-1">Política de Cookies</h2>
      <p class="text-muted">Última atualização: <?= $dataAtualizacao ?></p>

      <h5>1. O que são cookies</h5>
      <p>Cookies são pequenos arquivos usados para permitir o funcionamento correto do site.</p>

      <h5>2. Que cookies usamos</h5>
      <ul>
        <li><strong>Essenciais:</strong> para login, sessão e segurança</li>
        <li><strong>Estatística:</strong> somente se futuramente forem ativados</li>
      </ul>

      <h5>3. Como gerenciar</h5>
      <p>
        Você pode apagar ou bloquear cookies nas configurações do seu navegador.
        O bloqueio de cookies essenciais pode impedir o funcionamento do sistema.
      </p>

      <h5>4. Privacidade</h5>
      <p>
        Veja também a <a href="privacidade.php">Política de Privacidade</a>.
      </p>
    </div>
  </div>
</div>

<?php include 'footer_simple.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

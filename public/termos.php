<?php
$empresa = [
  'razao' => 'HEVCON ASSESSORIA CONTABIL LTDA',
  'email' => 'hevconweb@gmail.com'
];
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
  <title>Termos de Uso - Calculadora INSS Obra Legal</title>
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
      <h2 class="mb-1">Termos de Uso</h2>
      <p class="text-muted">Última atualização: <?= $dataAtualizacao ?></p>

      <h5>1. Aceite</h5>
      <p>Ao usar o sistema, você concorda com estes Termos.</p>

      <h5>2. O serviço</h5>
      <p>
        A Calculadora INSS Obra Legal é uma ferramenta de apoio e simulação.
        Não substitui a orientação de um contador ou profissional habilitado.
      </p>

      <h5>3. Conta e acesso</h5>
      <ul>
        <li>Você é responsável por sua senha e uso da sua conta</li>
        <li>Deve fornecer dados verdadeiros e atualizados</li>
      </ul>

      <h5>4. Uso indevido</h5>
      <ul>
        <li>É proibido tentar invadir, fraudar ou explorar falhas do sistema</li>
        <li>É proibido usar o sistema para fins ilegais</li>
      </ul>

      <h5>5. Responsabilidade</h5>
      <p>
        As decisões tomadas com base nas simulações são de responsabilidade do usuário.
      </p>

      <h5>6. Encerramento</h5>
      <p>
        Podemos suspender ou encerrar contas em caso de uso indevido.
      </p>

      <h5>7. Privacidade</h5>
      <p>
        O uso de dados segue a <a href="privacidade.php">Política de Privacidade</a>.
      </p>

      <h5>8. Contato</h5>
      <p>
        Contato: <strong><?= $empresa['email'] ?></strong>
      </p>
    </div>
  </div>
</div>

<?php include 'footer_simple.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

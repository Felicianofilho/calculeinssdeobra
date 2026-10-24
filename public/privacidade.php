<?php
$empresa = [
  'razao' => 'HEVCON ASSESSORIA CONTABIL LTDA',
  'fantasia' => 'Hevcon WEB',
  'cnpj' => '14.053.190/0001-10',
  'site' => 'https://www.hevcon.com.br/',
  'email' => 'hevconweb@gmail.com',
  'telefone' => '(85) 3494-0140',
  'endereco' => 'Rua Abelardo Marinho, 151, Sala 201 a 204, Vila União, Fortaleza - CE'
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
  <title>Política de Privacidade - Calculadora INSS Obra Legal</title>
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
      <h2 class="mb-1">Política de Privacidade</h2>
      <p class="text-muted">Última atualização: <?= $dataAtualizacao ?></p>

      <h5>1. Quem somos</h5>
      <p>
        O controlador dos dados é <strong><?= $empresa['razao'] ?></strong>,
        CNPJ <?= $empresa['cnpj'] ?>, com sede em <?= $empresa['endereco'] ?>.
        Contato LGPD: <strong><?= $empresa['email'] ?></strong>
      </p>

      <h5>2. Quais dados coletamos</h5>
      <ul>
        <li>Nome, e-mail e telefone</li>
        <li>Senha (armazenada de forma criptografada)</li>
        <li>Dados de simulações e planejamentos cadastrados pelo usuário</li>
        <li>Dados técnicos essenciais para funcionamento e segurança</li>
      </ul>

      <h5>3. Para que usamos os dados</h5>
      <ul>
        <li>Criar e gerenciar sua conta</li>
        <li>Salvar histórico e planejamentos</li>
        <li>Prestar suporte e atendimento</li>
        <li>Garantir segurança do sistema</li>
        <li>Cumprir obrigações legais</li>
      </ul>

      <h5>4. Compartilhamento</h5>
      <p>
        Os dados podem ser compartilhados apenas com serviços de hospedagem e infraestrutura
        ou por obrigação legal. Não comercializamos dados pessoais.
      </p>

      <h5>5. Armazenamento e segurança</h5>
      <p>
        Os dados são armazenados em ambiente seguro e mantidos enquanto a conta estiver ativa
        ou pelo tempo exigido por lei.
      </p>

      <h5>6. Direitos do titular</h5>
      <p>
        Você pode solicitar acesso, correção ou exclusão dos seus dados pelo e-mail:
        <strong><?= $empresa['email'] ?></strong> ou através da página <a href="direitos-lgpd.php">Direitos do Titular</a>.
      </p>

      <h5>7. Cookies</h5>
      <p>
        Utilizamos cookies essenciais para funcionamento do sistema. Veja a
        <a href="cookies.php">Política de Cookies</a>.
      </p>

      <h5>8. Contato</h5>
      <p>
        Dúvidas sobre privacidade: <strong><?= $empresa['email'] ?></strong>
      </p>

    </div>
  </div>
</div>

<?php include 'footer_simple.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

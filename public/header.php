<?php
// Header simples para páginas legais (LGPD)
if (!isset($config)) {
    require_once __DIR__ . '/../src/Utils/LocalConfig.php';
    $config = LocalConfig::getAll();
}
$baseUrl = LocalConfig::getBaseUrl();
$logoFile = $config['theme']['branding']['logo_file'] ?? '';
$logoUrl = LocalConfig::buildAssetUrl($logoFile);
?>
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <?php if (!empty($logoFile)): ?>
                <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Logo" style="max-width:180px;max-height:80px;object-fit:contain;background:#fff;padding:8px 12px;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.1);">
            <?php else: ?>
                <i class="fas fa-building me-2"></i>
                <?php echo htmlspecialchars($config['system']['name'] ?? 'Calculadora INSS Obra Legal ®'); ?>
            <?php endif; ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">
                    <i class="fas fa-home me-1"></i>Início
                </a>
                <a class="nav-link" href="login.php">
                    <i class="fas fa-sign-in-alt me-1"></i>Entrar
                </a>
                <a class="nav-link" href="register.php">
                    <i class="fas fa-user-plus me-1"></i>Cadastrar
                </a>
            </div>
        </div>
    </div>
</nav>

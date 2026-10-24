<?php
session_start();
require_once __DIR__ . '/../update_user_activity.php';
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Utils/LocalConfig.php';

// Verificar se pode acessar
if (!Auth::canAccessAdminPanel()) {
    header('Location: login.php');
    exit;
}

$user = Auth::user();
$config = LocalConfig::getAll();
$isSuperAdmin = Auth::isSuperAdmin();

// Cache-busting para logo e favicon
$baseUrl = LocalConfig::getBaseUrl();
$logoFile = $config['theme']['branding']['logo_file'] ?? '';
$logoFilePath = !empty($logoFile) ? __DIR__ . '/' . $logoFile : '';
$logoVersion = ($logoFilePath && file_exists($logoFilePath) ? filemtime($logoFilePath) : time());

$faviconFile = $config['theme']['branding']['favicon_file'] ?? '';
$faviconFilePath = !empty($faviconFile) ? __DIR__ . '/' . $faviconFile : '';
$faviconVersion = ($faviconFilePath && file_exists($faviconFilePath) ? filemtime($faviconFilePath) : time());
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - Sistema INSS</title>
    <?php if (!empty($faviconFile)): ?>
    <link rel="icon" type="image/png" href="<?php echo LocalConfig::buildAssetUrl($faviconFile); ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <?php echo LocalConfig::getCustomCSS(); ?>
</head>
<?php
$sidebarPosition = $config['theme']['layout']['sidebar_position'] ?? 'left';
$wrapperClass = ($sidebarPosition === 'right') ? 'd-flex flex-row-reverse' : 'd-flex';
?>
<?php
$sidebarPosition = $config['theme']['layout']['sidebar_position'] ?? 'left';
$wrapperClass = ($sidebarPosition === 'right') ? 'd-flex flex-row-reverse' : 'd-flex';
?>
<body class="d-flex">
    <div id="wrapper">
        <!-- Sidebar -->
        <div class="bg-dark" id="sidebar-wrapper">
            <div class="sidebar-content-wrapper">
                <div class="sidebar-heading text-center py-4" style="background: none;">
                    <?php if (!empty($logoFile)): ?>
                        <img class="sidebar-logo" src="<?php echo LocalConfig::buildAssetUrl($logoFile); ?>" alt="Logo" style="max-width:180px;max-height:80px;object-fit:contain;background:#fff;padding:8px 12px;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.1);">
                    <?php else: ?>
                        <span class="text-white fw-bold fs-5"><?php echo htmlspecialchars($config['system']['name'] ?? 'Calculadora INSS Obra Legal ®'); ?></span>
                    <?php endif; ?>
                </div>
                <div class="list-group list-group-flush">
                    <a href="javascript:void(0);" onclick="window.history.back();" class="list-group-item list-group-item-action bg-dark text-white">
                        <i class="fas fa-arrow-left fa-fw me-2"></i><span class="sidebar-text">Voltar</span>
                    </a>
                    <a href="dashboard.php" class="list-group-item list-group-item-action bg-dark text-white">
                        <i class="fas fa-tachometer-alt fa-fw me-2"></i><span class="sidebar-text">Dashboard</span>
                    </a>
                    <a href="admin_vau.php" class="list-group-item list-group-item-action bg-dark text-white">
                        <i class="fas fa-table fa-fw me-2"></i><span class="sidebar-text">Tabela VAU</span>
                    </a>
                    <a href="admin_selic.php" class="list-group-item list-group-item-action bg-dark text-white">
                        <i class="fas fa-percentage fa-fw me-2"></i><span class="sidebar-text">Tabela SELIC</span>
                    </a>
                    <a href="admin_theme.php" class="list-group-item list-group-item-action bg-dark text-white">
                        <i class="fas fa-palette fa-fw me-2"></i><span class="sidebar-text">Temas</span>
                    </a>
                    <a href="admin_settings.php" class="list-group-item list-group-item-action bg-dark text-white active">
                        <i class="fas fa-cog fa-fw me-2"></i><span class="sidebar-text">Configurações</span>
                    </a>
                </div>
            </div>
            <div class="sidebar-toggler-wrapper">
                <button class="sidebar-toggler" id="sidebar-toggle-btn">
                    <i class="fas fa-angle-double-left"></i>
                </button>
            </div>
        </div>
        <!-- Main content -->
        <div id="page-content-wrapper" class="flex-grow-1">
            <div class="container-fluid p-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-cog me-2"></i>Configurações do Sistema
                </h1>
                <div class="d-flex gap-2">
                    <a href="admin.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Voltar
                    </a>
                    <button class="btn btn-primary" onclick="salvarTodasConfiguracoes()">
                        <i class="fas fa-save me-2"></i>Salvar Todas
                    </button>
                </div>
            </div>

            <!-- Link para Configurações Temáticas -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-info">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-palette me-3 fs-4"></i>
                            <div>
                                <h6 class="mb-1">Configurações de Tema e Visual</h6>
                                <p class="mb-0">Para personalizar cores, fontes, logo e outros elementos visuais, acesse a tela de configurações temáticas.</p>
                                <a href="admin_theme.php" class="btn btn-primary btn-sm mt-2">
                                    <i class="fas fa-cog me-1"></i>Configurar Tema
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Configurações por Categoria -->
            <div class="row">
                <!-- Configurações da Empresa -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white py-3">
                            <h6 class="m-0 font-weight-bold"><i class="fas fa-building me-2"></i>Informações da Empresa</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="companyName" class="form-label">Nome da Empresa</label>
                                <input type="text" class="form-control" id="companyName" 
                                       value="<?php echo htmlspecialchars($config['company']['name'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="website" class="form-label">Website</label>
                                <input type="url" class="form-control" id="website" 
                                       value="<?php echo htmlspecialchars($config['company']['website'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Configurações de Contato -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white py-3">
                            <h6 class="m-0 font-weight-bold"><i class="fas fa-address-book me-2"></i>Informações de Contato</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="contactEmail" class="form-label">E-mail de Contato</label>
                                <input type="email" class="form-control" id="contactEmail" 
                                       value="<?php echo htmlspecialchars($config['company']['contact_email'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="contactPhone" class="form-label">Telefone</label>
                                <input type="text" class="form-control" id="contactPhone" 
                                       value="<?php echo htmlspecialchars($config['company']['contact_phone'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="contactAddress" class="form-label">Endereço</label>
                                <textarea class="form-control" id="contactAddress" rows="3"><?php echo htmlspecialchars($config['company']['address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Configurações de Redes Sociais -->
            <div class="row">
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white py-3">
                            <h6 class="m-0 font-weight-bold"><i class="fas fa-share-alt me-2"></i>Redes Sociais</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="facebookUrl" class="form-label">
                                            <i class="fab fa-facebook-f me-2"></i>Facebook
                                        </label>
                                        <input type="url" class="form-control" id="facebookUrl" 
                                               placeholder="https://facebook.com/suaempresa"
                                               value="<?php echo htmlspecialchars($config['company']['social_media']['facebook'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="instagramUrl" class="form-label">
                                            <i class="fab fa-instagram me-2"></i>Instagram
                                        </label>
                                        <input type="url" class="form-control" id="instagramUrl" 
                                               placeholder="https://instagram.com/suaempresa"
                                               value="<?php echo htmlspecialchars($config['company']['social_media']['instagram'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="linkedinUrl" class="form-label">
                                            <i class="fab fa-linkedin-in me-2"></i>LinkedIn
                                        </label>
                                        <input type="url" class="form-control" id="linkedinUrl" 
                                               placeholder="https://linkedin.com/company/suaempresa"
                                               value="<?php echo htmlspecialchars($config['company']['social_media']['linkedin'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="twitterUrl" class="form-label">
                                            <i class="fab fa-twitter me-2"></i>Twitter/X
                                        </label>
                                        <input type="url" class="form-control" id="twitterUrl" 
                                               placeholder="https://twitter.com/suaempresa"
                                               value="<?php echo htmlspecialchars($config['company']['social_media']['twitter'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="youtubeUrl" class="form-label">
                                            <i class="fab fa-youtube me-2"></i>YouTube
                                        </label>
                                        <input type="url" class="form-control" id="youtubeUrl" 
                                               placeholder="https://youtube.com/@suaempresa"
                                               value="<?php echo htmlspecialchars($config['company']['social_media']['youtube'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="whatsappUrl" class="form-label">
                                            <i class="fab fa-whatsapp me-2"></i>WhatsApp
                                        </label>
                                        <input type="text" class="form-control" id="whatsappUrl" 
                                               placeholder="(85) 99999-9999 ou 5585999999999"
                                               value="<?php 
                                               $whatsapp = $config['company']['social_media']['whatsapp'] ?? '';
                                               if ($whatsapp && strpos($whatsapp, 'wa.me/') !== false) {
                                                   // Extrair número do link wa.me
                                                   $numero = str_replace('https://wa.me/', '', $whatsapp);
                                                   // Formatar para exibição
                                                   if (strlen($numero) >= 12) { // Com código do país
                                                       $ddd = substr($numero, 2, 2);
                                                       $parte1 = substr($numero, 4, 5);
                                                       $parte2 = substr($numero, 9, 4);
                                                       echo "($ddd) $parte1-$parte2";
                                                   } else {
                                                       echo $numero;
                                                   }
                                               } else {
                                                   echo htmlspecialchars($whatsapp);
                                               }
                                               ?>">
                                        <div class="form-text">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Digite o número do telefone. O sistema converterá automaticamente para o link do WhatsApp.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Dica:</strong> Apenas as redes sociais com URL configurada aparecerão no rodapé do site.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Configurações do Sistema (apenas SuperAdmin) -->
            <?php if ($isSuperAdmin): ?>
            <div class="row">
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white py-3">
                            <h6 class="m-0 font-weight-bold"><i class="fas fa-shield-alt me-2"></i>Configurações Globais do Sistema</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="systemName" class="form-label">Nome do Sistema</label>
                                        <input type="text" class="form-control" id="systemName" 
                                               value="<?php echo htmlspecialchars($config['system']['name'] ?? 'Calculadora INSS Obra Legal ®'); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="copyrightYear" class="form-label">Ano do Copyright</label>
                                        <input type="number" class="form-control" id="copyrightYear" 
                                               value="<?php echo htmlspecialchars($config['system']['copyright_year'] ?? date('Y')); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="supportEmail" class="form-label">E-mail de Suporte</label>
                                        <input type="email" class="form-control" id="supportEmail" 
                                               value="<?php echo htmlspecialchars($config['system']['support_email'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="maxAdmins" class="form-label">Limite de Admins</label>
                                        <input type="number" class="form-control" id="maxAdmins" 
                                               value="<?php echo htmlspecialchars($config['features']['max_admins'] ?? '1'); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="maxClients" class="form-label">Limite de Clientes</label>
                                        <input type="number" class="form-control" id="maxClients" 
                                               value="<?php echo htmlspecialchars($config['features']['max_clients'] ?? '2'); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Controle de Performance (apenas SuperAdmin) -->
            <div class="row">
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-header bg-warning text-dark py-3">
                            <h6 class="m-0 font-weight-bold"><i class="fas fa-tachometer-alt me-2"></i>Controle de Performance do Sistema</h6>
                        </div>
                        <div class="card-body">
                            <!-- Status do Sistema -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <h6><i class="fas fa-info-circle me-2"></i>Status Atual do Sistema</h6>
                                        <div id="systemStatus">
                                            <div class="d-flex justify-content-between">
                                                <span>Carregando status...</span>
                                                <button class="btn btn-sm btn-outline-primary" onclick="atualizarStatus()">
                                                    <i class="fas fa-sync-alt"></i> Atualizar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Configurações de Performance -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="clientTrigger" class="form-label">Clientes Gatilho</label>
                                        <input type="number" class="form-control" id="clientTrigger" min="1" max="999"
                                               value="98" placeholder="Número de clientes ativos que ativa o gatilho">
                                        <small class="form-text text-muted">Quando o número de clientes ativos atingir este valor, o sistema aplicará delays</small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="degradationFactor" class="form-label">Fator de Degradação</label>
                                        <input type="number" class="form-control" id="degradationFactor" step="0.01" min="1.0" max="3.0"
                                               value="1.50" placeholder="Fator exponencial para cálculo do delay">
                                        <small class="form-text text-muted">Quanto maior, mais rápido o sistema fica lento (1.5 = 50% mais lento por usuário online)</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="baseDelayMs" class="form-label">Delay Base (ms)</label>
                                        <input type="number" class="form-control" id="baseDelayMs" min="50" max="2000"
                                               value="200" placeholder="Delay base em milissegundos">
                                        <small class="form-text text-muted">Delay inicial aplicado quando o gatilho é ativado</small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="maxDelayMs" class="form-label">Delay Máximo (ms)</label>
                                        <input type="number" class="form-control" id="maxDelayMs" min="1000" max="10000"
                                               value="5000" placeholder="Delay máximo em milissegundos">
                                        <small class="form-text text-muted">Delay máximo que pode ser aplicado (evita travamentos)</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-12">
                                    <div class="alert alert-warning">
                                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Aviso Importante</h6>
                                        <p class="mb-0">Este sistema controla a performance baseada no número de usuários online. 
                                        Quando o gatilho é atingido, o sistema aplica delays exponenciais para manter a estabilidade.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-12 text-end">
                                    <button type="button" class="btn btn-warning" onclick="salvarConfiguracoesPerformance()">
                                        <i class="fas fa-save me-2"></i>Salvar Configurações de Performance
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            </div>
            <?php include 'footer_simple.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function formatarWhatsApp(numero) {
            // Remove todos os caracteres não numéricos
            let numeroLimpo = numero.replace(/\D/g, '');
            
            // Se o número não tem código do país, adiciona 55 (Brasil)
            if (numeroLimpo.length === 11 && numeroLimpo.startsWith('0')) {
                numeroLimpo = '55' + numeroLimpo.substring(1);
            } else if (numeroLimpo.length === 10) {
                numeroLimpo = '55' + numeroLimpo;
            } else if (numeroLimpo.length === 11 && !numeroLimpo.startsWith('55')) {
                numeroLimpo = '55' + numeroLimpo;
            }
            
            // Retorna o link do WhatsApp
            return numeroLimpo ? `https://wa.me/${numeroLimpo}` : '';
        }

        function salvarTodasConfiguracoes() {
            // Processar número do WhatsApp
            const whatsappInput = document.getElementById('whatsappUrl').value;
            const whatsappLink = formatarWhatsApp(whatsappInput);
            
            const configs = {
                company: {
                    name: document.getElementById('companyName').value,
                    website: document.getElementById('website').value,
                    contact_email: document.getElementById('contactEmail').value,
                    contact_phone: document.getElementById('contactPhone').value,
                    address: document.getElementById('contactAddress').value,
                    social_media: {
                        facebook: document.getElementById('facebookUrl').value,
                        instagram: document.getElementById('instagramUrl').value,
                        linkedin: document.getElementById('linkedinUrl').value,
                        twitter: document.getElementById('twitterUrl').value,
                        youtube: document.getElementById('youtubeUrl').value,
                        whatsapp: whatsappLink
                    }
                }
            };

            <?php if ($isSuperAdmin): ?>
            // Configurações do sistema (apenas SuperAdmin)
            configs.system = {
                name: document.getElementById('systemName').value,
                copyright_year: parseInt(document.getElementById('copyrightYear').value),
                support_email: document.getElementById('supportEmail').value
            };
            configs.features = {
                max_admins: parseInt(document.getElementById('maxAdmins').value),
                max_clients: parseInt(document.getElementById('maxClients').value)
            };
            <?php endif; ?>

            const requestData = {
                action: 'salvar_configuracoes_locais',
                configs: configs
            };

            fetch('admin_settings_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify(requestData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Configurações salvas com sucesso! Recarregue a página para ver as mudanças.');
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao salvar configurações');
            });
        }

        // Adicionar listener para formatar WhatsApp em tempo real
        document.addEventListener('DOMContentLoaded', function() {
            const whatsappInput = document.getElementById('whatsappUrl');
            
            // Função para formatar número de telefone brasileiro
            function formatarTelefone(numero) {
                // Remove tudo que não é número
                let numeroLimpo = numero.replace(/\D/g, '');
                
                // Aplica máscara de telefone brasileiro
                if (numeroLimpo.length <= 2) {
                    return numeroLimpo;
                } else if (numeroLimpo.length <= 6) {
                    return `(${numeroLimpo.slice(0, 2)}) ${numeroLimpo.slice(2)}`;
                } else if (numeroLimpo.length <= 10) {
                    return `(${numeroLimpo.slice(0, 2)}) ${numeroLimpo.slice(2, 6)}-${numeroLimpo.slice(6)}`;
                } else {
                    return `(${numeroLimpo.slice(0, 2)}) ${numeroLimpo.slice(2, 7)}-${numeroLimpo.slice(7, 11)}`;
                }
            }
            
            // Listener para formatar enquanto digita
            whatsappInput.addEventListener('input', function(e) {
                const valor = e.target.value;
                
                // Se parece ser um número de telefone (não um link), formata
                if (valor && !valor.includes('http') && !valor.includes('wa.me')) {
                    const numeroFormatado = formatarTelefone(valor);
                    if (numeroFormatado !== valor) {
                        e.target.value = numeroFormatado;
                    }
                }
            });
            
            // Mostrar preview do link quando o campo perder o foco
            whatsappInput.addEventListener('blur', function(e) {
                const valor = e.target.value;
                if (valor && !valor.includes('http')) {
                    const linkWhatsApp = formatarWhatsApp(valor);
                    if (linkWhatsApp) {
                        // Criar tooltip ou mostrar preview
                        const preview = document.createElement('div');
                        preview.className = 'alert alert-info mt-2';
                        preview.innerHTML = `
                            <i class="fas fa-link me-2"></i>
                            <strong>Link gerado:</strong> ${linkWhatsApp}
                        `;
                        
                        // Remover preview anterior se existir
                        const previewAnterior = whatsappInput.parentNode.querySelector('.alert');
                        if (previewAnterior) {
                            previewAnterior.remove();
                        }
                        
                        // Adicionar novo preview
                        whatsappInput.parentNode.appendChild(preview);
                        
                        // Remover preview após 3 segundos
                        setTimeout(() => {
                            if (preview.parentNode) {
                                preview.remove();
                            }
                        }, 3000);
                    }
                }
            });
        });

        // Funções para Controle de Performance
        function atualizarStatus() {
            console.log('🔄 Atualizando status...');
            fetch('api_performance_status.php?t=' + Date.now(), {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-cache'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const statusDiv = document.getElementById('systemStatus');
                    const systemStatus = data.stats.system_status === 'degraded' ? 'DEGRADADO' : 'NORMAL';
                    const statusClass = data.stats.system_status === 'degraded' ? 'text-warning' : 'text-success';
                    
                    statusDiv.innerHTML = `
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Usuários Online:</h6>
                                <ul class="list-unstyled mb-0">
                                    <li>Total: <strong>${data.stats.counts.total_online}</strong></li>
                                    <li>Leads: <strong>${data.stats.counts.leads_online}</strong></li>
                                    <li>Clientes: <strong>${data.stats.counts.clientes_online}</strong></li>
                                    <li>Admins: <strong>${data.stats.counts.admins_online}</strong></li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Status do Sistema:</h6>
                                <ul class="list-unstyled mb-0">
                                    <li>Clientes Ativos: <strong>${data.stats.counts.total_clientes_ativos}</strong></li>
                                    <li>Gatilho: <strong>${data.stats.config.client_trigger}</strong></li>
                                    <li>Status: <strong class="${statusClass}">${systemStatus}</strong></li>
                                    <li>Delay Atual: <strong>${data.stats.current_delay}ms</strong></li>
                                </ul>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end mt-2">
                            <button class="btn btn-sm btn-outline-primary" onclick="atualizarStatus()">
                                <i class="fas fa-sync-alt"></i> Atualizar
                            </button>
                        </div>
                    `;
                } else {
                    document.getElementById('systemStatus').innerHTML = 
                        '<div class="text-danger">Erro ao carregar status do sistema</div>';
                }
            })
            .catch(error => {
                console.error('❌ Erro na requisição atualizarStatus:', error);
                document.getElementById('systemStatus').innerHTML = 
                    '<div class="text-danger">Erro ao carregar status do sistema: ' + error.message + '</div>';
            });
        }

        function salvarConfiguracoesPerformance() {
            const config = {
                client_trigger: parseInt(document.getElementById('clientTrigger').value),
                degradation_factor: parseFloat(document.getElementById('degradationFactor').value),
                base_delay_ms: parseInt(document.getElementById('baseDelayMs').value),
                max_delay_ms: parseInt(document.getElementById('maxDelayMs').value),
                stealth_mode: true
            };

            console.log('Enviando configurações:', config);

            fetch('api_performance_config.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(config)
            })
            .then(response => response.json())
            .then(data => {
                console.log('Resposta da API:', data);
                if (data.success) {
                    alert('Configurações salvas:\n' +
                          'Gatilho: ' + config.client_trigger + '\n' +
                          'Fator: ' + config.degradation_factor + '\n' +
                          'Base Delay: ' + config.base_delay_ms + 'ms\n' +
                          'Max Delay: ' + config.max_delay_ms + 'ms');
                    atualizarStatus();
                    // Recarregar configurações
                    setTimeout(() => location.reload(), 1000);
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao salvar configurações de performance');
            });
        }

        // Carregar configurações de performance ao carregar a página
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($isSuperAdmin): ?>
            // Carregar configurações de performance
            fetch('api_performance_status.php', {
                method: 'GET',
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('clientTrigger').value = data.stats.config.client_trigger;
                    document.getElementById('degradationFactor').value = data.stats.config.degradation_factor;
                    document.getElementById('baseDelayMs').value = data.stats.config.base_delay_ms;
                    document.getElementById('maxDelayMs').value = data.stats.config.max_delay_ms;
                    
                    // Atualizar status
                    atualizarStatus();
                }
            })
            .catch(error => {
                console.error('Erro ao carregar configurações de performance:', error);
            });
            <?php endif; ?>
        });
    </script>
</body>
</html> 
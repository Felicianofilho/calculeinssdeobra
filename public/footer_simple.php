<!-- Rodapé Simples -->
<?php
// Carregar configurações se não estiverem disponíveis
if (!isset($config)) {
    require_once __DIR__ . '/../src/Utils/LocalConfig.php';
    $config = LocalConfig::getAll();
}
$baseUrl = LocalConfig::getBaseUrl();
?>

<footer class="footer-simple">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-4">
                <p class="footer-text">
                    <?php echo htmlspecialchars($config['system']['name'] ?? 'Calculadora INSS Obra Legal ®'); ?>
                </p>
            </div>
            <div class="col-md-4 text-center">
                <p class="footer-text">
                    &copy; <?php echo htmlspecialchars($config['system']['copyright_year'] ?? date('Y')); ?> <?php echo htmlspecialchars($config['company']['name'] ?? 'Calculadora INSS Obra Legal ®'); ?>. Todos os direitos reservados.
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <p class="footer-host">
                    Desenvolvido e Hospedado por 
                    <a href="https://fortaleza.hostnet.com.br" target="_blank" class="host-link">
                        <strong>Hostnet Fortaleza</strong>
                    </a>
                </p>
            </div>
        </div>
        <!-- Links Legais LGPD -->
        <div class="row mt-3 pt-3" style="border-top: 1px solid rgba(255,255,255,0.1);">
            <div class="col-12 text-center">
                <p class="footer-text mb-2" style="font-size: 0.9rem;">
                    <a href="privacidade.php" class="text-decoration-none me-3" style="color: inherit; opacity: 0.8;">Política de Privacidade</a>
                    <span class="mx-2" style="opacity: 0.5;">|</span>
                    <a href="termos.php" class="text-decoration-none me-3" style="color: inherit; opacity: 0.8;">Termos de Uso</a>
                    <span class="mx-2" style="opacity: 0.5;">|</span>
                    <a href="cookies.php" class="text-decoration-none me-3" style="color: inherit; opacity: 0.8;">Política de Cookies</a>
                    <span class="mx-2" style="opacity: 0.5;">|</span>
                    <a href="direitos-lgpd.php" class="text-decoration-none" style="color: inherit; opacity: 0.8;">Direitos do Titular (LGPD)</a>
                </p>
            </div>
        </div>
    </div>
</footer>

<!-- Botão Flutuante WhatsApp -->
<?php
$whatsappLink = $config['company']['social_media']['whatsapp'] ?? '';

if (!empty($whatsappLink)):
    // Mensagem pré-definida genérica para qualquer página do sistema
    $mensagem = urlencode("Olá! Preciso de informações sobre INSS da minha obra...");
    $whatsappUrl = $whatsappLink . "?text=" . $mensagem;
?>
<div id="whatsapp-float" class="whatsapp-float">
    <a href="<?= htmlspecialchars($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer" 
       title="Precisa de ajuda? Fale conosco no WhatsApp">
        <i class="fab fa-whatsapp"></i>
        <span class="tooltip-text">Precisa de ajuda?</span>
    </a>
</div>

<style>
    /* Botão Flutuante WhatsApp */
    .whatsapp-float {
        position: fixed;
        bottom: 25px;
        right: 25px;
        z-index: 1000;
        animation: whatsapp-pulse 2s infinite;
    }
    
    .whatsapp-float a {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 60px;
        height: 60px;
        background: #25D366;
        border-radius: 50%;
        color: white;
        text-decoration: none;
        font-size: 28px;
        box-shadow: 0 4px 20px rgba(37, 211, 102, 0.4);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .whatsapp-float a:hover {
        background: #128C7E;
        transform: scale(1.1);
        box-shadow: 0 6px 25px rgba(37, 211, 102, 0.6);
        color: white;
    }
    
    .whatsapp-float a::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
        transition: left 0.5s ease;
    }
    
    .whatsapp-float a:hover::before {
        left: 100%;
    }
    
    .tooltip-text {
        position: absolute;
        right: 70px;
        top: 50%;
        transform: translateY(-50%);
        background: #333;
        color: white;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 14px;
        white-space: nowrap;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease;
        font-family: inherit;
    }
    
    .tooltip-text::after {
        content: '';
        position: absolute;
        left: 100%;
        top: 50%;
        transform: translateY(-50%);
        border: 6px solid transparent;
        border-left-color: #333;
    }
    
    .whatsapp-float a:hover .tooltip-text {
        opacity: 1;
    }
    
    @keyframes whatsapp-pulse {
        0% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.05);
        }
        100% {
            transform: scale(1);
        }
    }
    
    /* Responsividade */
    @media (max-width: 768px) {
        .whatsapp-float {
            bottom: 20px;
            right: 20px;
        }
        
        .whatsapp-float a {
            width: 55px;
            height: 55px;
            font-size: 24px;
        }
        
        .tooltip-text {
            display: none; /* Ocultar tooltip no mobile */
        }
    }
    
    @media (max-width: 480px) {
        .whatsapp-float {
            bottom: 15px;
            right: 15px;
        }
        
        .whatsapp-float a {
            width: 50px;
            height: 50px;
            font-size: 22px;
        }
    }
    
    /* Evitar conflito com sidebar e outros elementos */
    @media (min-width: 769px) {
        .whatsapp-float {
            right: 30px;
        }
    }
    
    /* Suavizar entrada do botão */
    .whatsapp-float {
        animation: whatsapp-slide-in 0.5s ease-out, whatsapp-pulse 2s infinite 0.5s;
    }
    
    @keyframes whatsapp-slide-in {
        from {
            opacity: 0;
            transform: translateY(100px) scale(0.3);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
</style>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Aplica a posição da sidebar (esquerda/direita)
    const sidebarPosition = '<?php echo $config["theme"]["layout"]["sidebar_position"] ?? "left"; ?>';
    const wrapper = document.getElementById('wrapper');
    if (wrapper && sidebarPosition === 'right') {
        wrapper.classList.add('sidebar-right');
    }

    // Lógica do novo toggler da sidebar
    const toggleButton = document.getElementById('sidebar-toggle-btn');
    const sidebarState = localStorage.getItem('sidebarState');

    // Aplica o estado salvo ao carregar a página
    if (wrapper && sidebarState === 'collapsed') {
        wrapper.classList.add('sidebar-collapsed');
    }

    // Adiciona o evento de clique ao botão
    if (toggleButton && wrapper) {
        toggleButton.addEventListener('click', function() {
            wrapper.classList.toggle('sidebar-collapsed');
            // Salva o novo estado no localStorage
            if (wrapper.classList.contains('sidebar-collapsed')) {
                localStorage.setItem('sidebarState', 'collapsed');
            } else {
                localStorage.setItem('sidebarState', 'expanded');
            }
        });
    }
});
</script>


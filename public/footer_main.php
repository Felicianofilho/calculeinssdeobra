<!-- Rodapé Principal -->
<footer class="footer-main">
    <div class="container">
        <div class="row">
            <!-- Coluna 1: Sobre o Sistema -->
            <div class="col-lg-4 col-md-6 mb-4">
                <?php
                // Carregar configurações usando o helper
                require_once __DIR__ . '/../src/Utils/FooterHelper.php';
                $footerData = FooterHelper::loadConfig();
                ?>
                
                <!-- Logo da Empresa ou Nome da Empresa -->
                <?php if (!empty($footerData['branding']['logo_file'])): ?>
                    <div class="text-center mb-3">
                        <?php echo FooterHelper::renderLogo($footerData, 'large'); ?>
                    </div>
                <?php endif; ?>
                <h5 class="footer-title">
                    <i class="fas fa-building me-2"></i>
                    <?php echo htmlspecialchars($footerData['system']['name'] ?? 'Calculadora INSS Obra Legal ®'); ?>
                </h5>
                
                <p class="footer-text">
                    Solução completa para simulação de cálculo do INSS em obras civis, 
                    desenvolvida com base na Instrução Normativa RFB nº 2.021/2021.
                </p>
                <div class="footer-social">
                    <?php echo FooterHelper::renderSocialMedia($footerData); ?>
                </div>
            </div>
            
            <!-- Coluna 2: Links Úteis -->
            <div class="col-lg-2 col-md-6 mb-4">
                <h5 class="footer-title">Links Úteis</h5>
                <ul class="footer-links">
                    <li><a href="index.php">Início</a></li>
                    <li><a href="login.php">Entrar</a></li>
                    <li><a href="register.php">Cadastrar</a></li>
                    <li><a href="#sobre">Sobre</a></li>
                </ul>
            </div>
            
            <!-- Coluna 3: Funcionalidades -->
            <div class="col-lg-3 col-md-6 mb-4">
                <h5 class="footer-title">Funcionalidades</h5>
                <ul class="footer-links">
                    <li><a href="#" onclick="showFeatureMessage('Cálculos INSS')" title="Clique para saber mais">Cálculos INSS</a></li>
                    <li><a href="#" onclick="showFeatureMessage('Relatórios PDF')" title="Clique para saber mais">Relatórios PDF</a></li>
                    <li><a href="#" onclick="showFeatureMessage('Histórico')" title="Clique para saber mais">Histórico</a></li>
                    <li><a href="#" onclick="showFeatureMessage('Simulações')" title="Clique para saber mais">Simulações</a></li>
                </ul>
            </div>
            
            <!-- Coluna 4: Contato -->
            <div class="col-lg-3 col-md-6 mb-4">
                <h5 class="footer-title">Contato</h5>
                <div class="footer-contact">
                    <?php echo FooterHelper::renderContactInfo($footerData); ?>
                </div>
            </div>
        </div>
        
        <hr class="footer-divider">
        
        <!-- Links Legais LGPD -->
        <div class="row mb-3">
            <div class="col-12 text-center">
                <p class="footer-text mb-0" style="font-size: 0.9rem;">
                    <a href="privacidade.php" class="text-white text-decoration-none me-3">Política de Privacidade</a>
                    <span class="mx-2" style="opacity: 0.6;">|</span>
                    <a href="termos.php" class="text-white text-decoration-none me-3">Termos de Uso</a>
                    <span class="mx-2" style="opacity: 0.6;">|</span>
                    <a href="cookies.php" class="text-white text-decoration-none me-3">Política de Cookies</a>
                    <span class="mx-2" style="opacity: 0.6;">|</span>
                    <a href="direitos-lgpd.php" class="text-white text-decoration-none">Direitos do Titular (LGPD)</a>
                </p>
            </div>
        </div>

        <!-- Rodapé Inferior -->
        <div class="row align-items-center">
            <div class="col-md-4 d-flex align-items-center gap-2">
                <span class="footer-text mb-0"><?php echo htmlspecialchars($footerData['system']['name'] ?? 'Calculadora INSS Obra Legal ®'); ?></span>
            </div>
            <div class="col-md-4 text-center">
                <p class="footer-copyright mb-0">
                    &copy; <?php echo htmlspecialchars($footerData['system']['copyright_year'] ?? date('Y')); ?> <?php echo htmlspecialchars($footerData['company']['name'] ?? 'Calculadora INSS Obra Legal ®'); ?>. Todos os direitos reservados.
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <p class="footer-host mb-0">
                    Desenvolvido e Hospedado por 
                    <a href="https://fortaleza.hostnet.com.br" target="_blank" class="host-link">
                        <strong>Hostnet Fortaleza</strong>
                    </a>
                </p>
            </div>
        </div>
    </div>
</footer>

<script>
function showFeatureMessage(featureName) {
    // Criar modal ou toast com a mensagem
    const message = `Para acessar ${featureName}, crie sua conta gratuita e tenha acesso imediato ao sistema!`;
    
    // Usar Bootstrap toast se disponível, senão alert
    if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
        // Criar toast dinamicamente
        const toastHtml = `
            <div class="toast align-items-center text-white bg-primary border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-info-circle me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        
        // Adicionar toast ao body
        const toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        toastContainer.innerHTML = toastHtml;
        document.body.appendChild(toastContainer);
        
        // Mostrar toast
        const toastElement = toastContainer.querySelector('.toast');
        const toast = new bootstrap.Toast(toastElement);
        toast.show();
        
        // Remover toast após ser fechado
        toastElement.addEventListener('hidden.bs.toast', function() {
            document.body.removeChild(toastContainer);
        });
    } else {
        // Fallback para alert simples
        alert(message);
    }
}
</script>

<style>
.footer-main {
    background: var(--primary-color) !important;
    color: #ffffff;
    padding: 4rem 0 2rem;
    margin-top: 4rem;
}

.footer-title {
    color: #ffffff;
    font-weight: 700;
    font-size: 1.3rem;
    margin-bottom: 1.5rem;
    position: relative;
}

.footer-title::after {
    content: '';
    position: absolute;
    bottom: -8px;
    left: 0;
    width: 50px;
    height: 3px;
    background: linear-gradient(90deg, #ffffff, rgba(255, 255, 255, 0.3));
    border-radius: 2px;
}

.footer-text {
    color: rgba(255, 255, 255, 0.9);
    line-height: 1.6;
    margin-bottom: 1.5rem;
}

.footer-social {
    display: flex;
    gap: 1rem;
}

.social-link {
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    text-decoration: none;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.social-link:hover {
    background: rgba(255, 255, 255, 0.2);
    color: #ffffff;
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(255, 255, 255, 0.2);
}

.footer-links {
    list-style: none;
    padding: 0;
    margin: 0;
}

.footer-links li {
    margin-bottom: 0.8rem;
}

.footer-links a {
    color: rgba(255, 255, 255, 0.9);
    text-decoration: none;
    transition: all 0.3s ease;
    position: relative;
}

.footer-links a:hover {
    color: #ffffff;
    padding-left: 10px;
}

.footer-links a::before {
    content: '';
    position: absolute;
    left: -15px;
    top: 50%;
    transform: translateY(-50%);
    width: 0;
    height: 2px;
    background: #ffffff;
    transition: width 0.3s ease;
}

.footer-links a:hover::before {
    width: 8px;
}

.footer-contact {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.contact-item {
    display: flex;
    align-items: center;
    color: rgba(255, 255, 255, 0.9);
    transition: all 0.3s ease;
}

.contact-item:hover {
    color: #ffffff;
    transform: translateX(5px);
}

.contact-item i {
    width: 20px;
    flex-shrink: 0;
}

.footer-divider {
    border-color: rgba(255, 255, 255, 0.2);
    margin: 2rem 0 1.5rem;
}

.footer-copyright {
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
    font-size: 0.9rem;
}

.footer-host {
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
    font-size: 0.9rem;
}

.host-link {
    color: #ffffff;
    text-decoration: none;
    transition: all 0.3s ease;
    position: relative;
}

.host-link:hover {
    color: #ffffff;
    text-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
}

.host-link::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 0;
    height: 2px;
    background: #ffffff;
    transition: width 0.3s ease;
}

.host-link:hover::after {
    width: 100%;
}

@media (max-width: 768px) {
    .footer-main {
        padding: 3rem 0 2rem;
        text-align: center;
    }
    
    .footer-social {
        justify-content: center;
    }
    
    .footer-contact {
        align-items: center;
    }
    
    .contact-item {
        justify-content: center;
    }
    
    .col-md-6.text-md-end {
        text-align: center !important;
        margin-top: 1rem;
    }
}
</style>


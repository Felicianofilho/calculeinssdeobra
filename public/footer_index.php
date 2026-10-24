<!-- Footer Informativo para Index -->
<footer class="bg-dark text-white py-4 mt-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 col-md-6 mb-3">
                <?php
                require_once __DIR__ . '/../src/Utils/LocalConfig.php';
                $config = LocalConfig::getAll();
                $baseUrl = LocalConfig::getBaseUrl();

                // Obter caminho da logo com múltiplas verificações
                $logoFile = $config['theme']['branding']['logo_file'] ?? '';
                $logoUrl = '';
                $logoExists = false;
                
                if (!empty($logoFile)) {
                    // Tentar diferentes métodos de geração de URL
                    $logoUrl = LocalConfig::buildAssetUrl($logoFile);
                    
                    // Lista de caminhos possíveis para verificar
                    $possiblePaths = [
                        __DIR__ . '/' . $logoFile,  // Relativo ao footer
                        $_SERVER['DOCUMENT_ROOT'] . '/public/' . $logoFile,  // Absoluto raiz
                    ];
                    
                    // Se tivermos um baseUrl, adicionar mais uma opção
                    if ($baseUrl !== '') {
                        $possiblePaths[] = $_SERVER['DOCUMENT_ROOT'] . $baseUrl . '/public/' . $logoFile;
                    }
                    
                    $logoExists = false;
                    $workingPath = '';
                    
                    // Testar cada caminho até encontrar um que funcione
                    foreach ($possiblePaths as $testPath) {
                        if (file_exists($testPath) && is_readable($testPath)) {
                            $logoExists = true;
                            $workingPath = $testPath;
                            break;
                        }
                    }
                    
                    // Se encontrou um caminho válido, gerar URL correspondente
                    if ($logoExists) {
                        // Adicionar cache-busting baseado na modificação do arquivo
                        $logoTimestamp = filemtime($workingPath);
                        $logoUrl .= '?v=' . $logoTimestamp;
                    }
                    
                    // Debug em desenvolvimento (apenas localhost)
                    if ($_SERVER['HTTP_HOST'] === 'localhost') {
                        if (!$logoExists) {
                            error_log("FOOTER DEBUG - Logo não encontrada em nenhum caminho testado");
                            foreach ($possiblePaths as $i => $path) {
                                error_log("FOOTER DEBUG - Caminho " . ($i+1) . ": " . $path . " - " . (file_exists($path) ? 'existe' : 'não existe'));
                            }
                        } else {
                            error_log("FOOTER DEBUG - Logo encontrada em: " . $workingPath);
                            error_log("FOOTER DEBUG - URL gerada: " . $logoUrl);
                        }
                    }
                }
                ?>
                
                <!-- Logo da Empresa ou Nome da Empresa -->
                <?php if (!empty($logoFile) && $logoExists): ?>
                    <div class="text-center mb-3">
                        <img src="<?php echo htmlspecialchars($logoUrl); ?>" 
                             alt="<?php echo htmlspecialchars($config['company']['name'] ?? 'Logo'); ?>" 
                             style="max-width:180px;max-height:70px;object-fit:contain;background:#fff;padding:8px;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.1);"
                             onerror="this.style.display='none';">
                    </div>
                    <h5 class="fw-bold mb-3">
                        <i class="fas fa-building me-2"></i>
                        Calculadora INSS Obra Legal ®
                    </h5>
                <?php else: ?>
                    <h5 class="fw-bold mb-3">
                        <i class="fas fa-building me-2"></i>
                        <?php echo htmlspecialchars($config['company']['name'] ?? 'Calculadora INSS Obra Legal ®'); ?>
                    </h5>
                <?php endif; ?>
                <p class="mb-2">
                    Solução profissional para simulação de cálculo do INSS em obras civis 
                    por aferição indireta, conforme legislação vigente.
                </p>
                <p class="mb-0">
                    <small><strong>Base Legal:</strong> Instrução Normativa RFB nº 2.021/2021</small>
                </p>
            </div>
            
            <div class="col-lg-2 col-md-6 mb-3">
                <h6 class="fw-bold mb-3">Navegação</h6>
                <ul class="list-unstyled">
                    <li><a href="login.php" class="text-light text-decoration-none"><i class="fas fa-sign-in-alt me-1"></i> Entrar</a></li>
                    <li><a href="register.php" class="text-light text-decoration-none"><i class="fas fa-user-plus me-1"></i> Cadastrar</a></li>
                    <li><a href="#recursos" class="text-light text-decoration-none"><i class="fas fa-list me-1"></i> Recursos</a></li>
                    <li><a href="#beneficios" class="text-light text-decoration-none"><i class="fas fa-check-circle me-1"></i> Benefícios</a></li>
                </ul>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <h6 class="fw-bold mb-3">Funcionalidades</h6>
                <ul class="list-unstyled small">
                    <li><i class="fas fa-calculator me-2 text-primary"></i><a href="#" onclick="showFeatureMessage('Cálculos por aferição indireta')" class="text-light text-decoration-none" title="Clique para saber mais">Cálculos por aferição indireta</a></li>
                    <li><i class="fas fa-file-pdf me-2 text-primary"></i><a href="#" onclick="showFeatureMessage('Relatórios profissionais em PDF')" class="text-light text-decoration-none" title="Clique para saber mais">Relatórios profissionais em PDF</a></li>
                    <li><i class="fas fa-shield-alt me-2 text-primary"></i><a href="#" onclick="showFeatureMessage('Segurança e proteção de dados')" class="text-light text-decoration-none" title="Clique para saber mais">Segurança e proteção de dados</a></li>
                    <li><i class="fas fa-mobile-alt me-2 text-primary"></i><a href="#" onclick="showFeatureMessage('Interface responsiva')" class="text-light text-decoration-none" title="Clique para saber mais">Interface responsiva</a></li>
                </ul>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <h6 class="fw-bold mb-3">Informações Técnicas</h6>
                <div class="small">
                    <p class="mb-2">
                        <strong>Legislação:</strong><br>
                        IN RFB nº 2.021/2021<br>
                        Lei nº 8.212/1991
                    </p>
                    <p class="mb-2">
                        <strong>Aplicação:</strong><br>
                        Obras de construção civil<br>
                        Serviços de construção
                    </p>
                    <p class="mb-0">
                        <strong>Metodologia:</strong><br>
                        Cálculo por aferição indireta
                    </p>
                </div>
            </div>
        </div>
        
        <hr class="my-4">
        
        <div class="row align-items-center">
            <div class="col-md-6">
                <p class="mb-0">
                    © <?php echo date('Y'); ?> Calculadora INSS Obra Legal ®. Todos os direitos reservados.
                </p>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="mb-0">
                    Desenvolvido e Hospedado por 
                    <a href="https://fortaleza.hostnet.com.br" target="_blank" class="text-primary text-decoration-none fw-bold">
                        Hostnet Fortaleza
                    </a>
                </p>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-12 text-center">
                <small class="text-muted">
                    Este sistema é uma ferramenta de simulação. Recomenda-se sempre a verificação com profissionais especializados.
                </small>
            </div>
        </div>
    </div>
</footer>

<style>
/* Estilos específicos do footer index */
footer a:hover {
    color: #4a7bc8 !important;
    transition: color 0.3s ease;
}

footer ul li {
    margin-bottom: 0.5rem;
}

footer .text-primary {
    color: #4a7bc8 !important;
}

/* Responsividade */
@media (max-width: 768px) {
    footer .col-md-6:first-child {
        text-align: center;
        margin-bottom: 1rem;
    }
    
    footer .text-md-end {
        text-align: center !important;
    }
}
</style>

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


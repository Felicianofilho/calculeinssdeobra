<?php
// Incluir configurações de segurança
require_once __DIR__ . '/../config/security.php';

session_start();
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Utils/LocalConfig.php';
require_once __DIR__ . '/../src/Utils/ErrorHandler.php';

// Inicializar manipulador de erros
ErrorHandler::initialize();

$config = LocalConfig::getAll();
if (Auth::check()) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Início - <?php echo htmlspecialchars($config['system']['name'] ?? 'Calculadora INSS Obra Legal ®'); ?></title>
    <link rel="icon" type="image/png" href="<?php echo LocalConfig::buildAssetUrl('uploads/favicons/favicon.png'); ?>">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
    
    <style>
        /* Melhorias específicas para a página index */
        .hero-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e3f2fd 50%, #f8f9fa 100%);
            position: relative;
            overflow: hidden;
            padding: 4rem 0;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 100%;
            height: 200%;
            background: linear-gradient(45deg, rgba(44, 90, 160, 0.05) 0%, transparent 50%);
            border-radius: 50%;
            z-index: 1;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #2c5aa0, #1e3d72);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }
        
        .hero-subtitle {
            font-size: 1.4rem;
            color: #6c757d;
            font-weight: 400;
            margin-bottom: 2.5rem;
            line-height: 1.6;
        }
        
        .hero-buttons {
            gap: 1rem;
            margin-bottom: 3rem;
        }
        
        .btn-hero-primary {
            background: linear-gradient(135deg, #2c5aa0 0%, #1e3d72 100%);
            border: none;
            color: white;
            padding: 1.2rem 2.5rem;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(44, 90, 160, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-hero-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(44, 90, 160, 0.4);
            color: white;
        }
        
        .btn-hero-secondary {
            background: transparent;
            border: 2px solid #2c5aa0;
            color: #2c5aa0;
            padding: 1.2rem 2.5rem;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .btn-hero-secondary:hover {
            background: #2c5aa0;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(44, 90, 160, 0.3);
        }
        
        .stats-section {
            background: linear-gradient(135deg, #2c5aa0 0%, #1e3d72 100%);
            color: white;
            padding: 4rem 0;
            margin: 4rem 0;
        }
        
        .stat-item {
            text-align: center;
            padding: 2rem 1rem;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .stat-label {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 500;
        }
        
        .features-section {
            padding: 4rem 0;
        }
        
        .feature-card {
            background: white;
            border-radius: 20px;
            padding: 2.5rem 2rem;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            transition: all 0.4s ease;
            border: none;
            height: 100%;
            position: relative;
            overflow: hidden;
        }
        
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #2c5aa0, #1e3d72);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .feature-card:hover::before {
            transform: scaleX(1);
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #2c5aa0, #1e3d72);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
            transition: all 0.3s ease;
        }
        
        .feature-card:hover .feature-icon {
            transform: rotate(5deg) scale(1.1);
        }
        
        .feature-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #2c5aa0;
            margin-bottom: 1rem;
        }
        
        .feature-description {
            color: #6c757d;
            line-height: 1.6;
            font-size: 1rem;
        }
        
        .cta-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 25px;
            padding: 4rem 2rem;
            text-align: center;
            margin: 4rem 0;
            position: relative;
            overflow: hidden;
        }
        
        .cta-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(44, 90, 160, 0.05) 0%, transparent 70%);
            z-index: 1;
        }
        
        .cta-content {
            position: relative;
            z-index: 2;
        }
        
        .cta-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c5aa0;
            margin-bottom: 1.5rem;
        }
        
        .cta-subtitle {
            font-size: 1.2rem;
            color: #6c757d;
            margin-bottom: 2.5rem;
            line-height: 1.6;
        }
        
        .benefits-section {
            padding: 4rem 0;
            background: white;
        }
        
        .benefit-item {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 15px;
            transition: all 0.3s ease;
        }
        
        .benefit-item:hover {
            background: #e3f2fd;
            transform: translateX(10px);
        }
        
        .benefit-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #28a745, #20c997);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-right: 1.5rem;
            flex-shrink: 0;
        }
        
        .benefit-content h5 {
            color: #2c5aa0;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .benefit-content p {
            color: #6c757d;
            margin: 0;
            line-height: 1.5;
        }
        
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-subtitle {
                font-size: 1.2rem;
            }
            
            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn-hero-primary,
            .btn-hero-secondary {
                width: 100%;
                max-width: 300px;
            }
            
            .stat-number {
                font-size: 2.5rem;
            }
            
            .cta-title {
                font-size: 2rem;
            }

            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .benefit-item {
                flex-direction: column;
                text-align: center;
            }
            
            .benefit-icon {
                margin-right: 0;
                margin-bottom: 1rem;
            }
        }
        
        /* Animações */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-on-scroll {
            animation: fadeInUp 0.8s ease-out;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-building me-2"></i>
                <?php echo htmlspecialchars($config['system']['name'] ?? 'Calculadora INSS Obra Legal ®'); ?>
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="login.php">
                    <i class="fas fa-sign-in-alt me-1"></i>
                    Entrar
                </a>
                <a class="nav-link" href="register.php">
                    <i class="fas fa-user-plus me-1"></i>
                    Cadastrar
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="hero-content text-center">
                        <h1 class="hero-title">
                            <i class="fas fa-building me-3"></i>
                            <?php echo htmlspecialchars($config['system']['name'] ?? 'Calculadora INSS Obra Legal ®'); ?>
                        </h1>
                        <p class="hero-subtitle">
                            Simulação profissional de cálculo do INSS para obras civis segundo a Instrução Normativa RFB nº 2.021/2021. 
                            Precisão, agilidade e confiabilidade em cada cálculo.
                        </p>
                        <div class="d-flex justify-content-center hero-buttons">
                            <a href="login.php" class="btn btn-hero-primary">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                Acessar Sistema
                            </a>
                            <a href="#sobre" class="btn btn-hero-secondary">
                                <i class="fas fa-info-circle me-2"></i>
                                Saiba Mais
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <div class="stat-item">
                        <span class="stat-number">100%</span>
                        <div class="stat-label">Conforme Legislação</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-item">
                        <span class="stat-number">24/7</span>
                        <div class="stat-label">Disponibilidade</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-item">
                        <span class="stat-number">Seguro</span>
                        <div class="stat-label">Dados Protegidos</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section" id="sobre">
        <div class="container">
            <div class="row justify-content-center mb-5">
                <div class="col-lg-8 text-center">
                    <h2 class="display-5 fw-bold text-primary mb-4">Principais Funcionalidades</h2>
                    <p class="lead text-muted">
                        Desenvolvido especificamente para atender às necessidades de cálculo do INSS em obras civis
                    </p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-calculator"></i>
                        </div>
                        <h5 class="feature-title">Cálculos Precisos</h5>
                        <p class="feature-description">
                            Algoritmos avançados que garantem precisão total nos cálculos do INSS, 
                            seguindo rigorosamente a Instrução Normativa RFB nº 2.021/2021.
                        </p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <h5 class="feature-title">Relatórios Profissionais</h5>
                        <p class="feature-description">
                            Geração automática de relatórios detalhados em PDF com layout profissional, 
                            prontos para apresentação e arquivo.
                        </p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h5 class="feature-title">Segurança Total</h5>
                        <p class="feature-description">
                            Sistema seguro com criptografia avançada, controle de acesso rigoroso 
                            e proteção completa dos seus dados.
                        </p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h5 class="feature-title">Economia de Tempo</h5>
                        <p class="feature-description">
                            Automatize cálculos complexos que levariam horas para serem feitos manualmente. 
                            Resultados instantâneos e confiáveis.
                        </p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h5 class="feature-title">Acesso Móvel</h5>
                        <p class="feature-description">
                            Interface responsiva que funciona perfeitamente em computadores, 
                            tablets e smartphones. Trabalhe de qualquer lugar.
                        </p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        <h5 class="feature-title">Histórico Completo</h5>
                        <p class="feature-description">
                            Mantenha um registro completo de todas as simulações realizadas 
                            com possibilidade de consulta e reimpressão a qualquer momento.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Benefits Section -->
    <section class="benefits-section">
        <div class="container">
            <div class="row justify-content-center mb-5">
                <div class="col-lg-8 text-center">
                    <h2 class="display-5 fw-bold text-primary mb-4">Por que escolher nosso sistema?</h2>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-6">
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="benefit-content">
                            <h5>Conformidade Legal</h5>
                            <p>Totalmente alinhado com a legislação vigente e atualizações normativas</p>
                        </div>
                    </div>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="benefit-content">
                            <h5>Suporte Especializado</h5>
                            <p>Equipe técnica qualificada para esclarecer dúvidas e oferecer suporte</p>
                        </div>
                    </div>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-sync"></i>
                        </div>
                        <div class="benefit-content">
                            <h5>Atualizações Automáticas</h5>
                            <p>Sistema sempre atualizado com as últimas mudanças na legislação</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="benefit-content">
                            <h5>Análises Detalhadas</h5>
                            <p>Relatórios com análises aprofundadas e detalhamento completo dos cálculos</p>
                        </div>
                    </div>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-cloud"></i>
                        </div>
                        <div class="benefit-content">
                            <h5>Acesso na Nuvem</h5>
                            <p>Seus dados seguros na nuvem com backup automático e alta disponibilidade</p>
                        </div>
                    </div>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="benefit-content">
                            <h5>Custo-Benefício</h5>
                            <p>Solução econômica que elimina a necessidade de cálculos manuais demorados</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2 class="cta-title">Comece a usar agora mesmo!</h2>
                <p class="cta-subtitle">
                    Cadastre-se gratuitamente e tenha acesso imediato a todas as funcionalidades do sistema. 
                    Simplifique seus cálculos de INSS e ganhe mais tempo para focar no que realmente importa.
                </p>
                <div class="d-flex justify-content-center gap-3 cta-buttons">
                    <a href="register.php" class="btn btn-hero-primary">
                        <i class="fas fa-user-plus me-2"></i>
                        Criar Conta Gratuita
                    </a>
                    <a href="login.php" class="btn btn-hero-secondary">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        Já tenho conta
                    </a>
                </div>
            </div>
        </div>
    </section>

    <?php include 'footer_main.php'; ?>

    <!-- Banner de Cookies (LGPD) -->
    <?php include 'includes/cookie_banner.php'; ?>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Animações suaves ao fazer scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-on-scroll');
                }
            });
        }, observerOptions);

        // Observar elementos para animação
        document.querySelectorAll('.feature-card, .benefit-item').forEach(el => {
            observer.observe(el);
        });

        // Smooth scroll para links âncora
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>


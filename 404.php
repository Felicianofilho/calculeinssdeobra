<?php
// Incluir utilitário de caminhos
require_once __DIR__ . '/src/Utils/PathHelper.php';

// Definir cabeçalho 404
if (!headers_sent()) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página Não Encontrada - Calculadora INSS Obra Legal ®</title>
    <link rel="icon" type="image/png" href="<?= PathHelper::buildUrl('public/uploads/favicons/favicon.png') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c5aa0;
            --secondary-color: #f8f9fa;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --dark-color: #343a40;
            --light-color: #ffffff;
            --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary-color) 0%, #1e3d72 100%);
            font-family: var(--font-family);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        
        /* Elementos de fundo decorativos */
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 100%;
            height: 200%;
            background: linear-gradient(45deg, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            border-radius: 50%;
            z-index: 1;
        }
        
        body::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -30%;
            width: 80%;
            height: 80%;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 1;
        }
        
        .error-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            border-radius: 20px;
            padding: 3rem;
            text-align: center;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            max-width: 900px;
            width: 100%;
            position: relative;
            z-index: 2;
            animation: slideUp 0.8s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .error-icon {
            font-size: 4rem;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            animation: bounce 2s ease-in-out infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }
        
        .error-number {
            font-size: 7rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--primary-color), #1e3d72);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            margin-bottom: 1rem;
            text-shadow: 0 4px 8px rgba(44, 90, 160, 0.3);
        }
        
        .error-title {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 1rem;
        }
        
        .error-subtitle {
            font-size: 1.1rem;
            color: #6c757d;
            margin-bottom: 2.5rem;
            line-height: 1.6;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .error-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }
        
        .btn-action {
            padding: 14px 28px;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            font-size: 0.95rem;
            min-width: 140px;
            position: relative;
            overflow: hidden;
        }
        
        .btn-action::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .btn-action:hover::before {
            left: 100%;
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary-color) 0%, #1e3d72 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(44, 90, 160, 0.3);
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(44, 90, 160, 0.4);
            color: white;
        }
        
        .btn-secondary-custom {
            background: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }
        
        .btn-secondary-custom:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(44, 90, 160, 0.3);
        }
        
        .btn-whatsapp {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3);
        }
        
        .btn-whatsapp:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37, 211, 102, 0.4);
            color: white;
        }
        
        .error-suggestions {
            background: linear-gradient(135deg, rgba(44, 90, 160, 0.1) 0%, rgba(44, 90, 160, 0.05) 100%);
            border: 1px solid rgba(44, 90, 160, 0.1);
            border-radius: 15px;
            padding: 2rem;
            margin-top: 2rem;
        }
        
        .error-suggestions h5 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
        }
        
        .error-suggestions ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .error-suggestions li {
            padding: 0.8rem 0;
            color: #5a6c7d;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            border-bottom: 1px solid rgba(44, 90, 160, 0.1);
            transition: all 0.3s ease;
        }
        
        .error-suggestions li:last-child {
            border-bottom: none;
        }
        
        .error-suggestions li:hover {
            color: var(--primary-color);
            transform: translateX(5px);
        }
        
        .error-suggestions i {
            color: var(--success-color);
            width: 18px;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .error-container {
                padding: 2rem 1.5rem;
                margin: 1rem;
                max-width: 90%;
            }
            
            .error-number {
                font-size: 5rem;
            }
            
            .error-title {
                font-size: 1.8rem;
            }
            
            .error-subtitle {
                font-size: 1rem;
            }
            
            .error-actions {
                flex-direction: column;
                align-items: center;
            }
            
            .btn-action {
                width: 100%;
                max-width: 280px;
            }
            
            .error-suggestions {
                padding: 1.5rem;
            }
        }
        
        /* Animação de entrada escalonada */
        .fade-in {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.6s ease-out forwards;
        }
        
        .fade-in:nth-child(1) { animation-delay: 0.1s; }
        .fade-in:nth-child(2) { animation-delay: 0.2s; }
        .fade-in:nth-child(3) { animation-delay: 0.3s; }
        .fade-in:nth-child(4) { animation-delay: 0.4s; }
        .fade-in:nth-child(5) { animation-delay: 0.5s; }
        
        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="fade-in">
            <i class="fas fa-exclamation-triangle error-icon"></i>
        </div>
        
        <div class="fade-in">
            <div class="error-number">404</div>
        </div>
        
        <div class="fade-in">
            <h1 class="error-title">Página Não Encontrada</h1>
        </div>
        
        <div class="fade-in">
            <p class="error-subtitle">
                Ops! A página que você está procurando não existe ou foi movida para outro local.
                Mas não se preocupe, vamos te ajudar a encontrar o que você precisa.
            </p>
        </div>
        
        <div class="fade-in">
            <div class="error-actions">
                <a href="<?= PathHelper::buildUrl() ?>" class="btn btn-action btn-primary-custom">
                    <i class="fas fa-home me-2"></i>Página Inicial
                </a>
                <a href="javascript:history.back()" class="btn btn-action btn-secondary-custom">
                    <i class="fas fa-arrow-left me-2"></i>Voltar
                </a>
                <a href="https://wa.me/558534940140?text=Olá!%20Estou%20com%20problemas%20para%20acessar%20uma%20página%20no%20sistema%20INSS" 
                   target="_blank" class="btn btn-action btn-whatsapp">
                    <i class="fab fa-whatsapp me-2"></i>Preciso de Ajuda
                </a>
            </div>
        </div>
        
        <div class="fade-in">
            <div class="error-suggestions">
                <h5><i class="fas fa-lightbulb me-2"></i>O que você pode fazer:</h5>
                <ul>
                    <li><i class="fas fa-check"></i>Verificar se a URL está digitada corretamente</li>
                    <li><i class="fas fa-check"></i>Usar o menu de navegação principal</li>
                    <li><i class="fas fa-check"></i>Limpar o cache e cookies do navegador</li>
                    <li><i class="fas fa-check"></i>Entrar em contato conosco se o problema persistir</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Aplicar classe fade-in para animação escalonada
            const elements = document.querySelectorAll('.error-container > div');
            elements.forEach(el => {
                if (!el.classList.contains('fade-in')) {
                    el.classList.add('fade-in');
                }
            });
        });
    </script>
</body>
</html>

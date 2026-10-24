<?php
class LocalConfig
{
    private static $config = null;
    
    /**
     * Carrega as configurações locais
     */
    private static function loadConfig()
    {
        if (self::$config === null) {
            $configFile = __DIR__ . '/../../config/local_settings.php';
            if (file_exists($configFile)) {
                self::$config = include $configFile;
            } else {
                self::$config = [];
            }
        }
        return self::$config;
    }
    
    /**
     * Obtém uma configuração específica
     */
    public static function get($key, $default = null)
    {
        $config = self::loadConfig();
        $keys = explode('.', $key);
        $value = $config;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
    
    /**
     * Obtém configuração da empresa
     */
    public static function getCompany($key, $default = null)
    {
        return self::get("company.{$key}", $default);
    }
    
    /**
     * Obtém configuração do tema
     */
    public static function getTheme($key, $default = null)
    {
        return self::get("theme.{$key}", $default);
    }
    
    /**
     * Obtém configuração do sistema
     */
    public static function getSystem($key, $default = null)
    {
        return self::get("system.{$key}", $default);
    }
    
    /**
     * Obtém configuração de funcionalidades
     */
    public static function getFeature($key, $default = null)
    {
        return self::get("features.{$key}", $default);
    }
    
    /**
     * Obtém configuração de relatórios
     */
    public static function getReport($key, $default = null)
    {
        return self::get("reports.{$key}", $default);
    }
    
    /**
     * Verifica se uma funcionalidade está habilitada
     */
    public static function isFeatureEnabled($key)
    {
        return self::getFeature($key, false) === true;
    }
    
    /**
     * Obtém todas as configurações
     */
    public static function getAll()
    {
        return self::loadConfig();
    }
    
    /**
     * Gera CSS customizado baseado nas configurações do tema
     */
    public static function getCustomCSS()
    {
        $config = self::getAll();
        $theme = $config['theme'] ?? [];
        $cardHeaderText = $theme['colors']['card_header_text'] ?? '#fff';
        
        // Cores principais
        $primaryColor = $theme['colors']['primary'] ?? '#2c5aa0';
        $secondaryColor = $theme['colors']['secondary'] ?? '#6c757d';
        $successColor = $theme['colors']['success'] ?? '#28a745';
        $warningColor = $theme['colors']['warning'] ?? '#ffc107';
        $dangerColor = $theme['colors']['danger'] ?? '#dc3545';
        $infoColor = $theme['colors']['info'] ?? '#17a2b8';
        $lightColor = $theme['colors']['light'] ?? '#f8f9fa';
        $darkColor = $theme['colors']['dark'] ?? '#343a40';
        
        // Cores de fundo
        $bodyBg = $theme['colors']['body_bg'] ?? '#ffffff';
        $navbarBg = $theme['colors']['navbar_bg'] ?? '#2c5aa0';
        $sidebarBg = $theme['colors']['sidebar_bg'] ?? '#343a40';
        $cardBg = $theme['colors']['card_bg'] ?? '#ffffff';
        
        // Cores de texto
        $textPrimary = $theme['colors']['text_primary'] ?? '#212529';
        $textSecondary = $theme['colors']['text_secondary'] ?? '#6c757d';
        $textMuted = $theme['colors']['text_muted'] ?? '#6c757d';
        
        // Cores de borda e links
        $borderColor = $theme['colors']['border_color'] ?? '#dee2e6';
        $linkColor = $theme['colors']['link_color'] ?? '#2c5aa0';
        $linkHoverColor = $theme['colors']['link_hover_color'] ?? '#1e3a5f';
        
        // Configurações de fonte
        $fontFamily = $theme['fonts']['family'] ?? "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
        $fontSizeBase = $theme['fonts']['size_base'] ?? '16px';
        $fontSizeSmall = $theme['fonts']['font_size_small'] ?? '14px';
        $fontSizeLarge = $theme['fonts']['font_size_large'] ?? '18px';
        $fontWeightNormal = $theme['fonts']['font_weight_normal'] ?? '400';
        $fontWeightBold = $theme['fonts']['font_weight_bold'] ?? '700';
        $fontWeightLight = $theme['fonts']['font_weight_light'] ?? '300';
        
        // Configurações de layout
        $borderRadius = $theme['layout']['border_radius'] ?? '0.375rem';
        $boxShadow = $theme['layout']['box_shadow'] ?? '0 0.125rem 0.25rem rgba(0, 0, 0, 0.075)';
        $paddingSmall = $theme['layout']['padding_small'] ?? '0.5rem';
        $paddingMedium = $theme['layout']['padding_medium'] ?? '1rem';
        $paddingLarge = $theme['layout']['padding_large'] ?? '1.5rem';
        
        // Variáveis CSS
        $cssVars = "
            --primary-color: {$primaryColor};
            --secondary-color: {$secondaryColor};
            --success-color: {$successColor};
            --warning-color: {$warningColor};
            --danger-color: {$dangerColor};
            --info-color: {$infoColor};
            --light-color: {$lightColor};
            --dark-color: {$darkColor};
            --body-bg: {$bodyBg};
            --navbar-bg: {$navbarBg};
            --sidebar-bg: {$sidebarBg};
            --card-bg: {$cardBg};
            --text-primary: {$textPrimary};
            --text-secondary: {$textSecondary};
            --text-muted: {$textMuted};
            --border-color: {$borderColor};
            --link-color: {$linkColor};
            --link-hover-color: {$linkHoverColor};
            --font-family: {$fontFamily};
            --font-size-base: {$fontSizeBase};
            --font-size-small: {$fontSizeSmall};
            --font-size-large: {$fontSizeLarge};
            --font-weight-normal: {$fontWeightNormal};
            --font-weight-bold: {$fontWeightBold};
            --font-weight-light: {$fontWeightLight};
            --border-radius: {$borderRadius};
            --box-shadow: {$boxShadow};
            --padding-small: {$paddingSmall};
            --padding-medium: {$paddingMedium};
            --padding-large: {$paddingLarge};
            
            /* Variáveis derivadas */
            --primary-color-light: " . self::adjustBrightness($primaryColor, 20) . ";
            --primary-color-dark: " . self::adjustBrightness($primaryColor, -20) . ";
            --sidebar-text: #ffffff;
            --sidebar-hover: " . self::adjustBrightness($sidebarBg, 10) . ";
            --card-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --card-shadow-hover: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --card-header-text: {$cardHeaderText};
        ";
        
        return "
        <style>
        :root {
            {$cssVars}
        }
        
        /* Reset e base */
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: var(--font-family);
            font-size: var(--font-size-base);
            font-weight: var(--font-weight-normal);
            background-color: var(--body-bg);
            color: var(--text-primary);
            line-height: 1.6;
            display: flex; /* Habilita flexbox no body */
            min-height: 100vh; /* Garante que o body ocupe a altura total da viewport */
        }
        
        /* Tipografia */
        h1 { font-size: 28px; font-weight: var(--font-weight-bold); color: var(--text-primary); margin-bottom: var(--padding-medium); }
        h2 { font-size: 24px; font-weight: var(--font-weight-bold); color: var(--text-primary); margin-bottom: var(--padding-medium); }
        h3 { font-size: 20px; font-weight: var(--font-weight-bold); color: var(--text-primary); margin-bottom: var(--padding-medium); }
        h4 { font-size: 16px; font-weight: var(--font-weight-bold); color: var(--text-primary); margin-bottom: var(--padding-medium); }
        h5 { font-size: 14px; font-weight: var(--font-weight-bold); color: var(--text-primary); margin-bottom: var(--padding-medium); }
        h6 { font-size: 12px; font-weight: var(--font-weight-bold); color: var(--text-primary); margin-bottom: var(--padding-medium); }

        .text-small { font-size: var(--font-size-small); }
        .text-large { font-size: var(--font-size-large); }
        .text-light { font-weight: var(--font-weight-light); }
        .text-bold { font-weight: var(--font-weight-bold); }
        
        /* Navbar */
        .navbar {
            background: linear-gradient(135deg, var(--navbar-bg) 0%, var(--primary-color) 100%);
            box-shadow: var(--card-shadow);
        }
        
        .navbar-brand {
            font-weight: var(--font-weight-bold);
            color: white !important;
        }

        /* --- Novo Sistema de Sidebar Moderno --- */
        #wrapper {
            display: flex;
            width: 100%; /* Ocupa toda a largura disponível */
            transition: all 0.3s ease;
        }

        #sidebar-wrapper {
            width: 240px;
            min-width: 240px;
            background-color: var(--sidebar-bg) !important;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.3s ease;
            position: fixed; /* Fixa a sidebar na tela */
            height: 100vh; /* Ocupa a altura total da viewport */
            z-index: 1030; /* Garante que fique acima do conteúdo */
            top: 0;
            left: 0; /* Padrão: esquerda */
            border-right: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-content-wrapper {
            flex-grow: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .sidebar-toggler-wrapper {
            padding: 0.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-toggler {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            width: 100%;
            padding: 0.5rem;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .sidebar-toggler:hover {
            background: var(--primary-color);
        }

        #page-content-wrapper {
            flex-grow: 1;
            width: 100%; /* Começa com 100% e ajusta com margem */
            transition: all 0.3s ease;
            margin-left: 240px; /* Espaço para a sidebar esquerda */
        }

        /* Estado Recolhido */
        #wrapper.sidebar-collapsed #sidebar-wrapper {
            width: 80px;
            min-width: 80px;
        }

        #wrapper.sidebar-collapsed #page-content-wrapper {
            margin-left: 80px; /* Ajusta a margem para a sidebar recolhida */
        }

        #wrapper.sidebar-collapsed .sidebar-logo {
            display: none; /* Opcional: esconder a logo quando recolhido */
        }

        .sidebar-logo {
            background-color: white;
            padding: 5px;
            border-radius: 5px;
        }

        #wrapper.sidebar-collapsed .sidebar-text {
            display: none;
        }

        #wrapper.sidebar-collapsed .list-group-item {
            justify-content: center;
            padding-left: 1rem !important; /* Resetar padding */
        }

        #wrapper.sidebar-collapsed .sidebar-toggler i {
            transform: rotate(180deg);
        }

        /* Layout da Sidebar à Direita */
        #wrapper.sidebar-right #sidebar-wrapper {
            left: auto; /* Remove o left */
            right: 0; /* Posiciona à direita */
            border-left: 1px solid rgba(255, 255, 255, 0.1); /* Borda à esquerda */
            border-right: none;
        }

        #wrapper.sidebar-right #page-content-wrapper {
            margin-left: 0; /* Remove a margem esquerda */
            margin-right: 240px; /* Adiciona margem direita */
        }

        #wrapper.sidebar-right.sidebar-collapsed #page-content-wrapper {
            margin-right: 80px; /* Ajusta margem direita para recolhido */
        }

        #wrapper.sidebar-right.sidebar-collapsed .sidebar-toggler i {
            transform: rotate(0deg); /* Ícone aponta para a esquerda para expandir */
        }

        #wrapper.sidebar-right .sidebar-toggler i {
            transform: rotate(180deg); /* Ícone aponta para a direita para recolher */
        }

        /* --- Fim do Novo Sistema de Sidebar --- */

        /* Forçar cores da sidebar para evitar sobreposições */
        .sidebar {
            background-color: var(--sidebar-bg) !important;
        }
        
        .sidebar-heading {
            background-color: var(--sidebar-bg) !important;
            color: var(--sidebar-text) !important;
            padding: var(--padding-medium);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .list-group-item {
            background-color: var(--sidebar-bg) !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
            color: var(--sidebar-text) !important;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            display: flex; /* Para alinhar ícone e texto */
            align-items: center;
        }
        
        .list-group-item:hover {
            background-color: var(--sidebar-hover) !important;
            padding-left: 1.5rem;
            border-left: 3px solid var(--primary-color);
        }
        
        .list-group-item.active {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
        }
        
        /* Navegação da sidebar */
        .nav-link {
            color: var(--sidebar-text) !important;
            transition: color 0.3s ease;
        }
        
        .nav-link:hover,
        .nav-link.active {
            color: var(--primary-color) !important;
            background-color: var(--sidebar-hover) !important;
        }
        
        /* Cards */
        .card {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        .card-header:not(.no-theme-header) {
            color: var(--card-header-text, #fff) !important;
        }
        
        /* Botões */
        .btn {
            border-radius: var(--border-radius);
            font-weight: var(--font-weight-normal);
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-color-dark) 100%);
            border-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-color-dark) 0%, var(--primary-color) 100%);
            border-color: var(--primary-color-dark);
            transform: translateY(-2px);
            box-shadow: var(--card-shadow-hover);
        }
        
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .btn-success {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }
        
        .btn-warning {
            background-color: var(--warning-color);
            border-color: var(--warning-color);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
        }
        
        .btn-info {
            background-color: var(--info-color);
            border-color: var(--info-color);
        }
        
        /* Textos */
        .text-primary { color: var(--primary-color) !important; }
        .text-secondary { color: var(--text-secondary) !important; }
        .text-success { color: var(--success-color) !important; }
        .text-warning { color: var(--warning-color) !important; }
        .text-danger { color: var(--danger-color) !important; }
        .text-info { color: var(--info-color) !important; }
        .text-muted { color: var(--text-muted) !important; }
        
        /* Backgrounds */
        .bg-primary { background-color: var(--primary-color) !important; }
        .bg-secondary { background-color: var(--secondary-color) !important; }
        .bg-success { background-color: var(--success-color) !important; }
        .bg-warning { background-color: var(--warning-color) !important; }
        .bg-danger { background-color: var(--danger-color) !important; }
        .bg-info { background-color: var(--info-color) !important; }
        .bg-light { background-color: var(--light-color) !important; }
        .bg-dark { background-color: var(--dark-color) !important; }
        
        /* Links */
        a {
            color: var(--link-color);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        a:hover {
            color: var(--link-hover-color);
            text-decoration: none;
        }
        
        /* Formulários */
        .form-control, .form-select {
            border-color: var(--border-color);
            border-radius: var(--border-radius);
            font-family: var(--font-family);
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(44, 90, 160, 0.15);
        }
        
        .form-label {
            font-weight: var(--font-weight-bold);
            color: var(--text-primary);
        }
        
        /* Tabelas */
        .table {
            color: var(--text-primary);
            border-color: var(--border-color);
        }
        
        .table thead th {
            background-color: var(--light-color);
            border-color: var(--border-color);
            color: var(--text-primary);
            font-weight: var(--font-weight-bold);
        }
        
        .table tbody tr {
            border-color: var(--border-color);
            transition: background-color 0.3s ease;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(44, 90, 160, 0.05);
        }
        
        /* Alertas */
        .alert {
            border-radius: var(--border-radius);
            border: none;
            font-weight: var(--font-weight-normal);
        }
        
        .alert-primary {
            background-color: rgba(44, 90, 160, 0.1);
            border-left: 4px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            border-left: 4px solid var(--success-color);
            color: var(--success-color);
        }
        
        .alert-warning {
            background-color: rgba(255, 193, 7, 0.1);
            border-left: 4px solid var(--warning-color);
            color: var(--warning-color);
        }
        
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            border-left: 4px solid var(--danger-color);
            color: var(--danger-color);
        }
        
        /* Dropdown */
        .dropdown-menu {
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow-hover);
            border: 1px solid var(--border-color);
        }
        
        .dropdown-item {
            color: var(--text-primary);
            transition: background-color 0.3s ease;
        }
        
        .dropdown-item:hover {
            background-color: var(--light-color);
            color: var(--primary-color);
        }
        
        /* Animações */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }

        /* Responsividade */
        @media (max-width: 768px) {
            :root {
                --font-size-base: 14px;
                --padding-medium: 0.75rem;
                --padding-large: 1rem;
            }
            
            .card {
                margin-bottom: var(--padding-medium);
            }
            
            .btn {
                font-size: var(--font-size-small);
                padding: 0.5rem 1rem;
            }
        }
        
        @media (max-width: 576px) {
            :root {
                --font-size-base: 13px;
                --padding-medium: 0.5rem;
            }
            
            .container-fluid {
                padding: var(--padding-small);
            }
        }
        </style>
        ";
    }
    
    /**
     * Ajusta o brilho de uma cor
     */
    private static function adjustBrightness($hex, $percent) {
        $hex = str_replace('#', '', $hex);
        
        if (strlen($hex) == 3) {
            $hex = str_repeat(substr($hex, 0, 1), 2) . str_repeat(substr($hex, 1, 1), 2) . str_repeat(substr($hex, 2, 1), 2);
        }
        
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        $r = max(0, min(255, $r + ($r * $percent / 100)));
        $g = max(0, min(255, $g + ($g * $percent / 100)));
        $b = max(0, min(255, $b + ($b * $percent / 100)));
        
        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }
    
    /**
     * Retorna as configurações padrão do sistema (refúgio seguro)
     */
    public static function getDefaultConfig()
    {
        return [
            'theme' => [
                'colors' => [
                    'primary' => '#2c5aa0',
                    'secondary' => '#f8f9fa',
                    'success' => '#28a745',
                    'warning' => '#ffc107',
                    'danger' => '#dc3545',
                    'info' => '#17a2b8',
                    'sidebar_bg' => '#343a40',
                    'card_header_text' => '#ffffff', // cor padrão do texto das barras de título
                ],
                'fonts' => [
                    'family' => 'Inter, -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif',
                    'size_base' => '16px',
                ],
                'layout' => [
                    'sidebar_position' => 'left',
                ],
                'branding' => [
                    'logo_file' => '',
                    'logo_file_report' => '',
                    'favicon_file' => '',
                ],
            ],
            'system' => [
                'name' => 'Calculadora INSS Obra Legal ®',
                'copyright_year' => date('Y'),
                'support_email' => 'suporte@sistema-obralegal.com.br'
            ],
            'company' => [
                'name' => 'Calculadora INSS Obra Legal ®',
                'website' => 'https://sistema-obralegal.com.br',
                'contact_email' => 'contato@sistema-obralegal.com.br',
                'contact_phone' => '(85) 3494-0140',
                'address' => 'Fortaleza, CE'
            ],
            'features' => [
                'max_users' => '50'
            ]
        ];
    }
    
    /**
     * Restaura as configurações padrão (refúgio seguro)
     */
    public static function restoreDefaults()
    {
        $defaultConfig = self::getDefaultConfig();
        return self::save($defaultConfig);
    }
    
    /**
     * Salva configurações no arquivo
     */
    public static function save($config)
    {
        try {
            $configFile = __DIR__ . '/../../config/local_settings.php';
            $configDir = dirname($configFile);
            
            // Criar diretório se não existir
            if (!is_dir($configDir)) {
                mkdir($configDir, 0777, true);
            }
            
            // Carregar configurações existentes
            $existingConfig = [];
            if (file_exists($configFile)) {
                $existingConfig = include $configFile;
            }
            
            // Mesclar configurações (preservar configurações existentes não temáticas)
            $mergedConfig = array_merge($existingConfig, $config);
            
            // Gerar conteúdo do arquivo
            $content = "<?php\n";
            $content .= "// Configurações locais do sistema\n";
            $content .= "// Este arquivo pode ser editado para personalizar a instância local\n\n";
            $content .= "return " . var_export($mergedConfig, true) . ";\n";
            $content .= "?>";
            
            // Salvar arquivo
            $result = file_put_contents($configFile, $content);
            
            if ($result === false) {
                error_log("Erro ao salvar configurações em: " . $configFile);
                return false;
            }
            
            // Limpar cache de configurações
            self::$config = null;
            
            return true;
            
        } catch (Exception $e) {
            error_log("Exceção ao salvar configurações: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Retorna a base da URL do sistema, ex: /, /meusistema, etc. Nunca termina com barra.
     */
    public static function getBaseUrl() {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseDir = dirname($scriptName);

        // Remove /public do final, se existir
        if (substr($baseDir, -7) === '/public') {
            $baseDir = substr($baseDir, 0, -7);
            if ($baseDir === '') $baseDir = '/';
        }

        // Garante que sempre começa com /
        if ($baseDir === '.' || $baseDir === '') {
            $baseDir = '/';
        }

        // Remove barra final, exceto se for apenas /
        if ($baseDir !== '/' && substr($baseDir, -1) === '/') {
            $baseDir = rtrim($baseDir, '/');
        }

        // Para domínios raiz, garantir que não haja barras duplas
        if ($baseDir === '/' && isset($_SERVER['HTTP_HOST'])) {
            // Se estamos no domínio raiz, retornar string vazia para evitar //public
            return '';
        }

        return $baseDir;
    }

    /**
     * Constrói uma URL de forma segura, evitando barras duplas
     */
    public static function buildUrl($path = '') {
        $baseUrl = self::getBaseUrl();
        
        // Se for um asset (uploads/ ou assets/), usar buildAssetUrl
        if (strpos($path, 'uploads/') === 0 || strpos($path, 'assets/') === 0) {
            return self::buildAssetUrl($path);
        }
        
        // Para outras páginas
        if ($baseUrl === '') {
            return '/' . ltrim($path, '/');
        }
        
        // Se baseUrl termina com / e path começa com /, remover uma das barras
        if (substr($baseUrl, -1) === '/' && substr($path, 0, 1) === '/') {
            return $baseUrl . substr($path, 1);
        }
        
        // Se baseUrl não termina com / e path não começa com /, adicionar barra
        if (substr($baseUrl, -1) !== '/' && substr($path, 0, 1) !== '/') {
            return $baseUrl . '/' . $path;
        }
        
        return $baseUrl . $path;
    }

    /**
     * Constrói uma URL para assets (uploads, CSS, JS, etc.) de forma consistente
     */
    public static function buildAssetUrl($assetPath) {
        $baseUrl = self::getBaseUrl();
        
        // Remover barra inicial se presente
        $assetPath = ltrim($assetPath, '/');
        
        // Para domínio raiz
        if ($baseUrl === '') {
            // Sempre usar /public/ em todos os ambientes
            return '/public/' . $assetPath;
        }
        
        // Para subdiretórios (desenvolvimento)
        return $baseUrl . '/public/' . $assetPath;
    }
} 
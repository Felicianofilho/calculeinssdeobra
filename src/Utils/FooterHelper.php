<?php
require_once __DIR__ . '/LocalConfig.php';

class FooterHelper
{
    /**
     * Carrega as configurações necessárias para os rodapés
     * @return array Configurações carregadas
     */
    public static function loadConfig()
    {
        $config = LocalConfig::getAll();
        $baseUrl = LocalConfig::getBaseUrl();
        
        return [
            'config' => $config,
            'baseUrl' => $baseUrl,
            'company' => $config['company'] ?? [],
            'branding' => $config['theme']['branding'] ?? [],
            'system' => $config['system'] ?? []
        ];
    }
    
    /**
     * Renderiza a logo da empresa com estilo padrão
     * @param array $footerData Dados do footer carregados
     * @param string $size Tamanho da logo (small, medium, large)
     * @return string HTML da logo ou string vazia se não existir
     */
    public static function renderLogo($footerData, $size = 'medium')
    {
        $logoFile = $footerData['branding']['logo_file'] ?? '';
        $companyName = $footerData['company']['name'] ?? 'Logo';
        
        if (empty($logoFile)) {
            return '';
        }
        
        $styles = [
            'small' => 'max-width:120px;max-height:50px;object-fit:contain;background:#fff;padding:5px;border-radius:4px;box-shadow:0 1px 4px rgba(0,0,0,0.1);',
            'medium' => 'max-width:180px;max-height:70px;object-fit:contain;background:#fff;padding:8px;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.1);',
            'large' => 'max-width:200px;max-height:80px;object-fit:contain;background:#fff;padding:10px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);'
        ];
        
        $style = $styles[$size] ?? $styles['medium'];
        
        return sprintf(
            '<img src="%s" alt="%s" style="%s">',
            LocalConfig::buildAssetUrl($logoFile),
            htmlspecialchars($companyName),
            $style
        );
    }
    
    /**
     * Renderiza informações de contato da empresa
     * @param array $footerData Dados do footer carregados
     * @return string HTML das informações de contato
     */
    public static function renderContactInfo($footerData)
    {
        $company = $footerData['company'];
        $html = '';
        
        // Email
        if (!empty($company['contact_email'])) {
            $html .= sprintf(
                '<div class="contact-item"><i class="fas fa-envelope me-2"></i><span>%s</span></div>',
                htmlspecialchars($company['contact_email'])
            );
        }
        
        // Telefone
        if (!empty($company['contact_phone'])) {
            $html .= sprintf(
                '<div class="contact-item"><i class="fas fa-phone me-2"></i><span>%s</span></div>',
                htmlspecialchars($company['contact_phone'])
            );
        }
        
        // Endereço
        if (!empty($company['address'])) {
            $html .= sprintf(
                '<div class="contact-item"><i class="fas fa-map-marker-alt me-2"></i><span>%s</span></div>',
                htmlspecialchars($company['address'])
            );
        }
        
        // Website
        if (!empty($company['website'])) {
            $html .= sprintf(
                '<div class="contact-item"><i class="fas fa-globe me-2"></i><a href="%s" target="_blank" style="color: rgba(255, 255, 255, 0.9); text-decoration: none;">%s</a></div>',
                htmlspecialchars($company['website']),
                htmlspecialchars($company['website'])
            );
        }
        
        return $html;
    }
    
    /**
     * Renderiza o copyright com nome do sistema
     * @param array $footerData Dados do footer carregados
     * @return string HTML do copyright
     */
    public static function renderCopyright($footerData)
    {
        $year = htmlspecialchars($footerData['system']['copyright_year'] ?? date('Y'));
        $name = htmlspecialchars($footerData['system']['name'] ?? $footerData['company']['name'] ?? 'Calculadora INSS Obra Legal ®');
        return sprintf(
            '&copy; %s %s. Todos os direitos reservados.',
            $year,
            $name
        );
    }
    
    /**
     * Renderiza os ícones das redes sociais configuradas
     * @param array $footerData Dados do footer carregados
     * @return string HTML dos ícones das redes sociais
     */
    public static function renderSocialMedia($footerData)
    {
        $socialMedia = $footerData['company']['social_media'] ?? [];
        $html = '';
        
        $socialNetworks = [
            'facebook' => [
                'icon' => 'fab fa-facebook-f',
                'title' => 'Facebook',
                'class' => 'social-link'
            ],
            'instagram' => [
                'icon' => 'fab fa-instagram',
                'title' => 'Instagram',
                'class' => 'social-link'
            ],
            'linkedin' => [
                'icon' => 'fab fa-linkedin-in',
                'title' => 'LinkedIn',
                'class' => 'social-link'
            ],
            'twitter' => [
                'icon' => 'fab fa-twitter',
                'title' => 'Twitter/X',
                'class' => 'social-link'
            ],
            'youtube' => [
                'icon' => 'fab fa-youtube',
                'title' => 'YouTube',
                'class' => 'social-link'
            ],
            'whatsapp' => [
                'icon' => 'fab fa-whatsapp',
                'title' => 'WhatsApp',
                'class' => 'social-link'
            ]
        ];
        
        foreach ($socialNetworks as $network => $config) {
            if (!empty($socialMedia[$network])) {
                $html .= sprintf(
                    '<a href="%s" class="%s" title="%s" target="_blank" rel="noopener noreferrer">
                        <i class="%s"></i>
                    </a>',
                    htmlspecialchars($socialMedia[$network]),
                    $config['class'],
                    $config['title'],
                    $config['icon']
                );
            }
        }
        
        return $html;
    }
}
?> 
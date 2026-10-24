<?php
// Configurações locais do sistema
// Este arquivo pode ser editado para personalizar a instância local

return array (
  'company' => 
  array (
    'name' => 'Hevcon WEB',
    'website' => 'https://www.hevcon.com.br/',
    'contact_email' => 'hevconweb@gmail.com',
    'contact_phone' => '(85) 3494-0140',
    'address' => 'Rua Abelardo Marinho, 151, Sala 201 a 204, Vila União | Fortaleza - CE',
    'social_media' => 
    array (
      'facebook' => '',
      'instagram' => 'https://www.instagram.com/hevconcontabilidade/',
      'linkedin' => '',
      'twitter' => '',
      'youtube' => 'https://www.youtube.com/@iranildo.contador',
      'whatsapp' => 'https://wa.me/558534940140',
    ),
  ),
  'theme' => 
  array (
    'colors' => 
    array (
      'primary' => '#2c5aa0',
      'secondary' => '#f8f9fa',
      'success' => '#28a745',
      'warning' => '#ffc107',
      'danger' => '#dc3545',
      'info' => '#17a2b8',
      'sidebar_bg' => '#343a40',
      'card_header_text' => '#ffffff',
    ),
    'fonts' => 
    array (
      'family' => 'Inter, -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif',
      'size_base' => '16px',
    ),
    'layout' => 
    array (
      'sidebar_position' => 'left',
    ),
    'branding' => 
    array (
      'logo_file' => 'uploads/logos/logo_1761478214.png',
      'logo_file_report' => 'uploads/logos/logo_report.png',
      'favicon_file' => 'uploads/favicons/favicon.png',
    ),
  ),
  'system' => 
  array (
    'name' => 'Calculadora INSS Obra Legal ®',
    'copyright_year' => 2025,
    'support_email' => 'feliciano.filho@hostnet.com.br',
  ),
  'features' => 
  array (
    'max_users' => 50,
    'max_admins' => 2,
    'max_clients' => 20,
    'multiplicador' => 1,
  ),
  'reports' => 
  array (
    'company_header' => true,
    'show_logo' => true,
    'footer_text' => 'Relatório gerado pela Calculadora INSS Obra Legal ®',
  ),
);
?>
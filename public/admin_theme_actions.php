<?php
// Endpoint para ações de configurações temáticas via AJAX
session_start();
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Utils/LocalConfig.php';

// Verificar se pode acessar configurações
if (!Auth::canAccessAdminPanel()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Obter action (pode vir via JSON ou POST para uploads)
$action = '';
if (isset($_POST['action'])) {
    $action = $_POST['action'];
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
}

try {
    switch ($action) {
        case 'salvar_configuracoes_tematicas':
            $configs = $input['configs'] ?? [];
            
            // Carregar configurações atuais
            $configFile = __DIR__ . '/../config/local_settings.php';
            $currentConfig = [];
            
            if (file_exists($configFile)) {
                $currentConfig = include $configFile;
            }
            
            // Mesclar configurações temáticas
            if (isset($configs['theme'])) {
                $currentConfig['theme'] = array_replace_recursive($currentConfig['theme'] ?? [], $configs['theme']);
            }
            
            // Gerar conteúdo do arquivo
            $content = "<?php\n";
            $content .= "// Configurações locais do sistema\n";
            $content .= "// Este arquivo pode ser editado para personalizar a instância local\n\n";
            $content .= "return " . var_export($currentConfig, true) . ";\n";
            $content .= "?>";
            
            // Salvar arquivo
            $result = file_put_contents($configFile, $content);
            
            if ($result !== false) {
                echo json_encode(['success' => true, 'message' => 'Configurações temáticas salvas com sucesso']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao salvar arquivo de configurações']);
            }
            break;

        case 'obter_configuracao_tema':
            $key = $input['key'] ?? '';
            
            if (empty($key)) {
                echo json_encode(['success' => false, 'message' => 'Chave da configuração não fornecida']);
                break;
            }
            
            $value = LocalConfig::getTheme($key);
            echo json_encode(['success' => true, 'value' => $value]);
            break;

        case 'listar_configuracoes_tema':
            $themeConfig = LocalConfig::getAll()['theme'] ?? [];
            echo json_encode(['success' => true, 'config' => $themeConfig]);
            break;

        case 'resetar_tema':
            // Resetar para configurações padrão centralizadas, preservando branding
            require_once __DIR__ . '/../src/Utils/LocalConfig.php';
            $defaultTheme = LocalConfig::getDefaultConfig()['theme'];
            $configFile = __DIR__ . '/../config/local_settings.php';
            $currentConfig = [];
            if (file_exists($configFile)) {
                $currentConfig = include $configFile;
            }
            // Preservar branding atual
            if (isset($currentConfig['theme']['branding'])) {
                $defaultTheme['branding'] = $currentConfig['theme']['branding'];
            }
            $currentConfig['theme'] = $defaultTheme;
            $content = "<?php\n";
            $content .= "// Configurações locais do sistema\n";
            $content .= "// Este arquivo pode ser editado para personalizar a instância local\n\n";
            $content .= "return " . var_export($currentConfig, true) . ";\n";
            $content .= "?>";
            $result = file_put_contents($configFile, $content);
            if ($result !== false) {
                echo json_encode(['success' => true, 'message' => 'Tema resetado para configurações padrão']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao resetar tema']);
            }
            break;

        case 'upload_logo_favicon':
            // Upload de logo, favicon ou logo do relatório
            $type = $_POST['type'] ?? '';
            if (!isset($_FILES['file']) || empty($type)) {
                echo json_encode(['success' => false, 'message' => 'Arquivo ou tipo não enviado']);
                exit;
            }
            $file = $_FILES['file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'Erro no upload do arquivo: ' . $file['error']]);
                exit;
            }
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'png') {
                echo json_encode(['success' => false, 'message' => 'Apenas arquivos PNG são permitidos']);
                exit;
            }
            
            $configFile = __DIR__ . '/../config/local_settings.php';
            $currentConfig = file_exists($configFile) ? include $configFile : [];
            if (!isset($currentConfig['theme']['branding'])) {
                $currentConfig['theme']['branding'] = [];
            }
            
            $destDir = '';
            $destFile = '';
            $configKey = '';
            $relativePath = '';

            // Definir destino
            if ($type === 'logo') {
                $destDir = __DIR__ . '/uploads/logos/';
                $timestamp = time();
                $destFile = 'logo_' . $timestamp . '.png';
                $configKey = 'logo_file';
                $relativePath = 'uploads/logos/' . $destFile;

                if (!is_dir($destDir)) {
                    mkdir($destDir, 0777, true);
                }
                $destPath = $destDir . $destFile;

                if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                    echo json_encode(['success' => false, 'message' => 'Erro ao salvar o novo arquivo de logo.']);
                    exit;
                }

                // Limpar logos antigas, mantendo as 5 mais recentes
                $logoFiles = glob($destDir . 'logo_*.png');
                if (count($logoFiles) > 5) {
                    usort($logoFiles, function($a, $b) {
                        return filemtime($a) - filemtime($b);
                    });
                    $filesToDelete = count($logoFiles) - 5;
                    for ($i = 0; $i < $filesToDelete; $i++) {
                        if (file_exists($logoFiles[$i])) {
                            unlink($logoFiles[$i]);
                        }
                    }
                }
                
                $currentConfig['theme']['branding'][$configKey] = $relativePath;

            } elseif ($type === 'favicon') {
                $destDir = __DIR__ . '/uploads/favicons/';
                $destFile = 'favicon.png';
                $configKey = 'favicon_file';
                $relativePath = 'uploads/favicons/' . $destFile;

                if (!is_dir($destDir)) mkdir($destDir, 0777, true);
                $destPath = $destDir . $destFile;
                if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                    echo json_encode(['success' => false, 'message' => 'Erro ao salvar o arquivo de favicon.']);
                    exit;
                }
                $currentConfig['theme']['branding'][$configKey] = $relativePath;
                copy($destPath, __DIR__ . '/favicon.png');

            } elseif ($type === 'logo_report') {
                $destDir = __DIR__ . '/uploads/logos/';
                $destFile = 'logo_report.png';
                $configKey = 'logo_file_report';
                $relativePath = 'uploads/logos/' . $destFile;

                if (!is_dir($destDir)) mkdir($destDir, 0777, true);
                $destPath = $destDir . $destFile;
                if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                    echo json_encode(['success' => false, 'message' => 'Erro ao salvar o arquivo de logo do relatório.']);
                    exit;
                }
                $currentConfig['theme']['branding'][$configKey] = $relativePath;

            } else {
                echo json_encode(['success' => false, 'message' => 'Tipo de upload inválido']);
                exit;
            }

            // Salvar config
            $content = "<?php\n";
            $content .= "// Configurações locais do sistema\n";
            $content .= "// Este arquivo pode ser editado para personalizar a instância local\n\n";
            $content .= "return " . var_export($currentConfig, true) . ";\n";
            $content .= "?>";
            file_put_contents($configFile, $content);

            // Resposta com URL correta
            $assetUrl = LocalConfig::buildAssetUrl($relativePath);
            echo json_encode(['success' => true, 'message' => 'Arquivo salvo com sucesso', 'path' => $assetUrl]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
            break;
    }

} catch (Exception $e) {
    error_log("Erro no admin_theme_actions.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?> 
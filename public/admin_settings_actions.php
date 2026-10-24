<?php
// Endpoint para ações de configurações via AJAX
session_start();
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Controllers/AdminController.php';

// Verificar se pode acessar configurações
error_log('[DEBUG_SESSION] user_id: ' . ($_SESSION['user_id'] ?? 'NULO') . ' | user_type: ' . ($_SESSION['user_type'] ?? 'NULO'));
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

// Obter dados JSON
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'salvar_configuracoes_locais':
            $configs = $input['configs'] ?? [];
            
            // LOG: JSON recebido
            error_log('[SALVAR_CONFIG] JSON recebido: ' . json_encode($input));
            
            // Carregar configurações atuais
            $configFile = __DIR__ . '/../config/local_settings.php';
            $currentConfig = [];
            
            if (file_exists($configFile)) {
                $currentConfig = include $configFile;
            }
            
            // LOG: Configs atuais
            error_log('[SALVAR_CONFIG] Config atual: ' . json_encode($currentConfig));
            // LOG: Configs recebidas
            error_log('[SALVAR_CONFIG] Config recebida: ' . json_encode($configs));
            
            // Mesclar configurações
            $newConfig = array_replace_recursive($currentConfig, $configs);
            // LOG: Config final (merge)
            error_log('[SALVAR_CONFIG] Config final (merge): ' . json_encode($newConfig));
            
            // Gerar conteúdo do arquivo
            $content = "<?php\n";
            $content .= "// Configurações locais do sistema\n";
            $content .= "// Este arquivo pode ser editado para personalizar a instância local\n\n";
            $content .= "return " . var_export($newConfig, true) . ";\n";
            $content .= "?>";
            // LOG: Conteúdo gerado
            error_log('[SALVAR_CONFIG] Conteúdo gerado: ' . substr($content, 0, 500));
            
            // Salvar arquivo
            $result = file_put_contents($configFile, $content);
            // LOG: Resultado do file_put_contents
            error_log('[SALVAR_CONFIG] file_put_contents retornou: ' . var_export($result, true));
            
            if ($result !== false) {
                echo json_encode(['success' => true, 'message' => 'Configurações salvas com sucesso']);
            } else {
                error_log('[SALVAR_CONFIG] ERRO ao salvar arquivo! Permissão: ' . (is_writable($configFile) ? 'gravável' : 'NÃO gravável'));
                echo json_encode(['success' => false, 'message' => 'Erro ao salvar arquivo de configurações']);
            }
            break;

        case 'obter_configuracao':
            $key = $input['key'] ?? '';
            $licenseId = $input['license_id'] ?? null;
            
            if (empty($key)) {
                echo json_encode(['success' => false, 'message' => 'Chave da configuração não fornecida']);
                break;
            }
            
            $value = AdminController::obterSetting($key, $licenseId);
            echo json_encode(['success' => true, 'value' => $value]);
            break;

        case 'listar_configuracoes':
            $licenseId = $input['license_id'] ?? null;
            $settings = AdminController::listarSettings($licenseId);
            echo json_encode(['success' => true, 'settings' => $settings]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
            break;
    }

} catch (Exception $e) {
    error_log("Erro no admin_settings_actions.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?> 
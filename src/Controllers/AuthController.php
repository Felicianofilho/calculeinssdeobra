<?php
require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Utils/Auth.php';
require_once __DIR__ . '/../Utils/EmailHelper.php';

class AuthController
{
    public static function register($nome, $email, $telefone, $senha, $aceite_termos = false)
    {
        // Validar aceite de termos (LGPD)
        if (!$aceite_termos) {
            return "Você deve aceitar os Termos de Uso e Política de Privacidade para se cadastrar.";
        }
        
        if (User::findByEmail($email)) {
            return "E-mail já cadastrado!";
        }
        
        // Validação básica do telefone
        if (empty($telefone)) {
            return "Telefone é obrigatório!";
        }
        
        // Remove caracteres não numéricos para validação e armazenamento
        $telefone_numeros = preg_replace('/\D/', '', $telefone);
        if (strlen($telefone_numeros) < 10 || strlen($telefone_numeros) > 11) {
            return "Telefone deve ter 10 ou 11 dígitos (com DDD)!";
        }
        
        $token = bin2hex(random_bytes(32));
        $data = [
            'nome' => $nome,
            'email' => $email,
            'telefone' => $telefone_numeros, // Salva apenas números
            'senha' => $senha,
            'tipo' => 'usuario',
            'ativo' => 0,
            'email_confirm_token' => $token,
            'email_confirmed' => 0
        ];
        User::create($data);

        // Enviar e-mail de confirmação
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
        $host = $_SERVER['HTTP_HOST'];
        $scriptName = $_SERVER['SCRIPT_NAME'];

        // Determinar base path da aplicação
        $scriptDir = dirname($scriptName);
        $basePath = '';

        if (strpos($scriptDir, '/public') !== false) {
            // Script está dentro de public, subir um nível
            $basePath = rtrim(substr($scriptDir, 0, strpos($scriptDir, '/public')), '/');
        } else {
            // Script está na raiz, usar seu diretório
            $basePath = rtrim($scriptDir, '/');
        }

        // Garantir que basePath não seja apenas uma barra
        if ($basePath === '/' || $basePath === '\\') {
            $basePath = '';
        }

        // Construir link de confirmação
        $confirmLink = $protocol . '://' . $host . $basePath . '/public/confirmar_email.php?token=' . $token;

        $subject = 'Confirme seu cadastro - Calcule INSS de Obra';
        $body = '<p>Olá, ' . htmlspecialchars($nome) . '!</p>' .
                '<p>Para ativar sua conta, clique no link abaixo:</p>' .
                '<p><a href="' . $confirmLink . '">' . $confirmLink . '</a></p>' .
                '<p>Se não foi você que solicitou o cadastro, ignore este e-mail.</p>';
        EmailHelper::send($email, $nome, $subject, $body);
        return true; // Retornar true para indicar sucesso
    }

    public static function login($email, $senha)
    {
        // Verificar rate limiting
        if (!Auth::checkLoginRateLimit($email)) {
            return "Muitas tentativas de login. Tente novamente em 5 minutos.";
        }
        
        if (Auth::login($email, $senha)) {
            // Reset rate limiting em caso de sucesso
            Auth::resetLoginRateLimit($email);
            return true;
        }
        return "E-mail ou senha inválidos!";
    }

    public static function logout()
    {
        Auth::logout();
    }
}
<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';

class EmailHelper
{
    private static function getMailer()
    {
        // Credenciais SMTP ficam em config/config.php (fora do controle de versão)
        $config = require __DIR__ . '/../../config/config.php';
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $config['smtp_host'] ?? '';
        $mail->SMTPAuth = true;
        $mail->Username = $config['smtp_user'] ?? '';
        $mail->Password = $config['smtp_pass'] ?? '';
        $mail->SMTPSecure = $config['smtp_secure'] ?? 'tls';
        $mail->Port = (int)($config['smtp_port'] ?? 587);
        $mail->CharSet = 'UTF-8';
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        $mail->setFrom('contato@calculeinssdeobra.com.br', 'Calcule INSS de Obra');
        return $mail;
    }

    /**
     * Envia um e-mail simples
     * @param string $toEmail
     * @param string $toName
     * @param string $subject
     * @param string $body (HTML)
     * @return bool|string true em caso de sucesso, mensagem de erro em caso de falha
     */
    public static function send($toEmail, $toName, $subject, $body)
    {
        try {
            $mail = self::getMailer();
            $mail->SMTPDebug = 2; // Ativa debug SMTP
            $mail->Debugoutput = function($str, $level) {
                error_log('PHPMailer: ' . $str);
            };
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Erro ao enviar e-mail: ' . $e->getMessage());
            return 'Erro ao enviar e-mail: ' . $e->getMessage();
        }
    }
} 
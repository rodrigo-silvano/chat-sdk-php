<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\Core\Database;
use App\Core\Crypto;

require_once __DIR__ . '/../libs/PHPMailer/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/SMTP.php';

class MailService
{
    public function sendMail(string $to, string $subject, string $body): void
    {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings");
            $stmt->execute();
            $rows = $stmt->fetchAll();
            
            $config = [];
            $encryptionKey = getenv('ENCRYPTION_KEY') ?: '';
            foreach ($rows as $row) {
                $config[$row['setting_key']] = Crypto::decrypt($row['setting_value'], $encryptionKey);
            }

            $host = $config['smtp_host'] ?? getenv('SMTP_HOST') ?? 'localhost';
            $port = (int)($config['smtp_port'] ?? getenv('SMTP_PORT') ?? 587);
            $user = $config['smtp_user'] ?? getenv('SMTP_USER') ?? '';
            $pass = $config['smtp_pass'] ?? getenv('SMTP_PASS') ?? '';
            $from = $config['smtp_from'] ?? getenv('SMTP_FROM') ?? 'noreply@chatsdk.com';

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = $port;
            
            if (!empty($user)) {
                $mail->SMTPAuth = true;
                $mail->Username = $user;
                $mail->Password = $pass;
                if ($port === 587) {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                } elseif ($port === 465) {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                }
            } else {
                $mail->SMTPAuth = false;
            }

            $mail->setFrom($from, 'ChatSDK');
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->CharSet = PHPMailer::CHARSET_UTF8;

            $mail->send();
        } catch (Exception $e) {
            throw new \Exception("Erro ao enviar email: " . $e->getMessage());
        }
    }
}

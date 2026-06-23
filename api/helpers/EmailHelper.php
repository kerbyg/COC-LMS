<?php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/email.php';
require_once __DIR__ . '/GoogleMailHelper.php';
require_once __DIR__ . '/GmailSmtpHelper.php';
require_once __DIR__ . '/SesSmtpHelper.php';

class EmailHelper {

    public static function appBaseUrl() {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . BASE_URL;
    }

    public static function logoUrl() {
        return self::appBaseUrl() . '/assets/images/phinma_logo2.png';
    }

    /**
     * Inline logo CID for emails (embedded attachment — works in Gmail).
     */
    public static function logoCidSrc() {
        return 'cid:phinma_logo';
    }

    public static function logoInlineAttachment() {
        $path = dirname(__DIR__, 2) . '/assets/images/phinma_logo2.png';
        if (!is_readable($path)) {
            return null;
        }
        return [
            'cid'  => 'phinma_logo',
            'path' => $path,
            'mime' => 'image/png',
            'name' => 'phinma_logo.png',
        ];
    }

    /** @deprecated Use logoCidSrc() for emails */
    public static function logoEmbedSrc() {
        return self::logoCidSrc();
    }

    public static function isGmailReady() {
        return GmailSmtpHelper::isConfigured();
    }

    public static function sendPasswordOtp($toEmail, $firstName, $otp) {
        $ttl = max(30, (int)(defined('PASSWORD_OTP_TTL') ? PASSWORD_OTP_TTL : 60));
        $mins = $ttl >= 60 ? '1 minute' : $ttl . ' seconds';
        $subject = 'Your COC-LMS password verification code';
        $html = self::passwordOtpTemplate($firstName, $otp, $mins);
        $text = "Hello {$firstName},\n\nYour COC-LMS password verification code is: {$otp}\n\nThis code expires in {$mins}.\n\nIf you did not request this, ignore this email.";

        return self::send($toEmail, $subject, $html, $text, ['priority' => true, 'require_delivery' => true]);
    }

    public static function notificationTemplate($firstName, $headline, $bodyHtml, $ctaUrl = null) {
        $name   = htmlspecialchars(trim($firstName) ?: 'Student', ENT_QUOTES, 'UTF-8');
        $title  = htmlspecialchars($headline, ENT_QUOTES, 'UTF-8');
        $logo   = self::logoCidSrc();
        $school = htmlspecialchars(SCHOOL_NAME, ENT_QUOTES, 'UTF-8');
        $year   = date('Y');
        $cta    = '';
        if ($ctaUrl) {
            $href = htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8');
            $cta  = '<div style="text-align:center;margin:28px 0 8px;">'
                  . '<a href="' . $href . '" style="display:inline-block;background:#00461B;color:#ffffff;text-decoration:none;'
                  . 'padding:13px 32px;border-radius:8px;font-weight:700;font-size:14px;letter-spacing:0.2px;">Open in COC-LMS</a>'
                  . '</div>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6;padding:40px 16px;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:480px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">

        <!-- Logo header -->
        <tr>
          <td style="padding:32px 40px 24px;text-align:center;border-bottom:1px solid #f0f0f0;">
            <img src="{$logo}" alt="PHINMA COC" width="48" height="48" style="display:inline-block;vertical-align:middle;border-radius:8px;margin-right:10px;">
            <span style="font-size:18px;font-weight:700;color:#111827;vertical-align:middle;">COC-LMS</span>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:32px 40px;">
            <h2 style="margin:0 0 8px;font-size:22px;font-weight:700;color:#111827;text-align:center;">{$title}</h2>
            <p style="margin:0 0 20px;font-size:14px;color:#6b7280;text-align:center;">Hello, <strong style="color:#374151;">{$name}</strong></p>
            <div style="font-size:14px;line-height:1.7;color:#4b5563;">{$bodyHtml}</div>
            {$cta}
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="padding:20px 40px 28px;border-top:1px solid #f0f0f0;text-align:center;">
            <p style="margin:0;font-size:12px;color:#9ca3af;line-height:1.6;">
              If you did not expect this notification, please disregard this email.<br>
              &copy; {$year} {$school}
            </p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }

    public static function passwordOtpTemplate($firstName, $otp, $validFor = '1 minute') {
        $name   = htmlspecialchars(trim($firstName) ?: 'User', ENT_QUOTES, 'UTF-8');
        $code   = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
        $valid  = htmlspecialchars($validFor, ENT_QUOTES, 'UTF-8');
        $logo   = self::logoCidSrc();
        $school = htmlspecialchars(SCHOOL_NAME, ENT_QUOTES, 'UTF-8');
        $year   = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Verify your COC-LMS sign-up</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6;padding:40px 16px;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:480px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">

        <!-- Logo header -->
        <tr>
          <td style="padding:32px 40px 24px;text-align:center;border-bottom:1px solid #f0f0f0;">
            <img src="{$logo}" alt="PHINMA COC" width="48" height="48" style="display:inline-block;vertical-align:middle;border-radius:8px;margin-right:10px;">
            <span style="font-size:18px;font-weight:700;color:#111827;vertical-align:middle;">COC-LMS</span>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:36px 40px 28px;text-align:center;">
            <h2 style="margin:0 0 12px;font-size:22px;font-weight:700;color:#111827;">Verify your password change</h2>
            <p style="margin:0 0 28px;font-size:14px;line-height:1.65;color:#6b7280;">
              We received a request to change the password for <strong style="color:#374151;">{$name}</strong>.
              Please enter the code below in the browser window where you started the request.
            </p>

            <!-- OTP Code Box -->
            <div style="background:#f3f4f6;border-radius:10px;padding:24px 40px;display:inline-block;margin:0 auto 28px;">
              <div style="font-size:38px;font-weight:800;letter-spacing:10px;color:#111827;font-family:Consolas,'Courier New',monospace;">{$code}</div>
            </div>

            <p style="margin:0;font-size:13px;color:#9ca3af;line-height:1.6;">
              This code will remain active for <strong style="color:#6b7280;">{$valid}</strong>.<br>
              If you did not attempt this, please disregard this email.
            </p>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="padding:20px 40px 28px;border-top:1px solid #f0f0f0;text-align:center;">
            <p style="margin:0;font-size:12px;color:#9ca3af;line-height:1.6;">
              &copy; {$year} {$school} &mdash; Learning Management System<br>
              This is an automated message. Please do not reply.
            </p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }

    public static function canSendMore() {
        $cap = (int)MAIL_DAILY_LIMIT;
        if ($cap <= 0) {
            return true;
        }
        return self::countSentToday() < $cap;
    }

    public static function countSentToday() {
        self::ensureSendLogTable();
        $row = db()->fetchOne(
            "SELECT COUNT(*) AS c FROM email_send_log WHERE sent_at >= CURDATE()"
        );
        return (int)($row['c'] ?? 0);
    }

    private static function ensureSendLogTable() {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            if (!function_exists('pdo')) {
                require_once __DIR__ . '/../../config/database.php';
            }
            pdo()->exec(
                "CREATE TABLE IF NOT EXISTS email_send_log (
                    log_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    to_email VARCHAR(190) NOT NULL,
                    subject VARCHAR(255) NOT NULL,
                    sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_sent_day (sent_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } catch (Throwable $e) {
            error_log('email_send_log table: ' . $e->getMessage());
        }
    }

    private static function recordSend($toEmail, $subject) {
        try {
            self::ensureSendLogTable();
            db()->execute(
                "INSERT INTO email_send_log (to_email, subject) VALUES (?, ?)",
                [$toEmail, $subject]
            );
        } catch (Throwable $e) {
            error_log('email_send_log insert: ' . $e->getMessage());
        }
    }

    public static function send($toEmail, $subject, $htmlBody, $textBody = '', array $options = []) {
        $toEmail = trim($toEmail);
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $priority = !empty($options['priority']);
        $requireDelivery = !empty($options['require_delivery']);
        $inlineImages = $options['inline_images'] ?? [];
        if ($inlineImages === [] && str_contains($htmlBody, 'cid:phinma_logo')) {
            $logoAtt = self::logoInlineAttachment();
            if ($logoAtt) {
                $inlineImages = [$logoAtt];
            }
        }
        if (!$priority && !self::canSendMore()) {
            error_log("Daily email cap reached ({$subject} -> {$toEmail})");
            return false;
        }

        $provider = strtolower(trim(MAIL_PROVIDER ?: 'auto'));
        $ok = false;

        if ($provider === 'log') {
            $ok = !$requireDelivery && MAIL_DEV_LOG && self::logDevEmail($toEmail, $subject, $htmlBody, $textBody);
        } elseif ($provider === 'brevo') {
            $ok = BREVO_API_KEY !== ''
                ? self::sendViaBrevo($toEmail, $subject, $htmlBody, $textBody)
                : (!$requireDelivery && MAIL_DEV_LOG && self::logDevEmail($toEmail, $subject, $htmlBody, $textBody));
        } elseif ($provider === 'google') {
            $ok = self::sendViaGoogle($toEmail, $subject, $htmlBody, $textBody, $inlineImages);
            if (!$ok && MAIL_DEV_LOG && !$requireDelivery) {
                $ok = self::logDevEmail($toEmail, $subject, $htmlBody, $textBody);
            }
        } elseif ($provider === 'ses') {
            $ok = self::sendViaSes($toEmail, $subject, $htmlBody, $textBody);
        } else {
            if (self::sendViaGoogle($toEmail, $subject, $htmlBody, $textBody, $inlineImages)) {
                $ok = true;
            } elseif (BREVO_API_KEY !== '' && self::sendViaBrevo($toEmail, $subject, $htmlBody, $textBody)) {
                $ok = true;
            } elseif (self::sendViaSes($toEmail, $subject, $htmlBody, $textBody)) {
                $ok = true;
            } elseif (MAIL_DEV_LOG && !$requireDelivery) {
                $ok = self::logDevEmail($toEmail, $subject, $htmlBody, $textBody);
            }
        }

        if (!$ok && !$requireDelivery && $provider !== 'log' && $provider !== 'brevo' && MAIL_DEV_LOG) {
            $ok = self::logDevEmail($toEmail, $subject, $htmlBody, $textBody);
        }

        if (!$ok) {
            error_log("Email not sent ({$subject} -> {$toEmail}) provider={$provider} gmail=" . (self::isGmailReady() ? 'yes' : 'no'));
        }

        if ($ok) {
            self::recordSend($toEmail, $subject);
        }

        return $ok;
    }

    private static function sendViaSes($toEmail, $subject, $htmlBody, $textBody) {
        return SesSmtpHelper::isConfigured()
            && SesSmtpHelper::send($toEmail, $subject, $htmlBody, $textBody);
    }

    private static function sendViaGoogle($toEmail, $subject, $htmlBody, $textBody, array $inlineImages = []) {
        if (GmailSmtpHelper::isConfigured() && GmailSmtpHelper::send($toEmail, $subject, $htmlBody, $textBody, $inlineImages)) {
            return true;
        }
        if (GoogleMailHelper::isConfigured() && GoogleMailHelper::send($toEmail, $subject, $htmlBody, $textBody)) {
            return true;
        }
        return false;
    }

    private static function sendViaBrevo($toEmail, $subject, $htmlBody, $textBody) {
        $payload = [
            'sender'      => ['name' => MAIL_FROM_NAME, 'email' => MAIL_FROM_EMAIL],
            'to'          => [['email' => $toEmail]],
            'subject'     => $subject,
            'htmlContent' => $htmlBody,
        ];
        if ($textBody !== '') {
            $payload['textContent'] = $textBody;
        }

        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'api-key: ' . BREVO_API_KEY,
                'content-type: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 20,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            error_log('Brevo curl error: ' . $err);
            return false;
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        }

        error_log('Brevo API error ' . $httpCode . ': ' . $response);
        return false;
    }

    private static function logDevEmail($toEmail, $subject, $htmlBody, $textBody) {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $line = str_repeat('=', 60) . "\n"
            . date('Y-m-d H:i:s') . " | {$subject}\n"
            . "To: {$toEmail}\n"
            . ($textBody !== '' ? $textBody . "\n" : strip_tags($htmlBody) . "\n");
        @file_put_contents($dir . '/mail.log', $line, FILE_APPEND | LOCK_EX);
        return true;
    }
}

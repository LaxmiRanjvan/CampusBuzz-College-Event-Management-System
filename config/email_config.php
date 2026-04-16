<?php
/**
 * Email Configuration for Campus Event Manager
 * Choose your preferred email method below
 */

// Load PHPMailer if using Composer
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ==========================================
// OPTION 1: PHP mail() - Default (works on most hosts)
// ==========================================
//define('EMAIL_METHOD', 'php_mail'); // Options: 'php_mail', 'smtp', 'sendgrid'

// ==========================================
// OPTION 2: SMTP Configuration (Gmail/Custom SMTP)
// ==========================================
// Uncomment and configure if using SMTP

define('EMAIL_METHOD', 'smtp');
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'laxmiranjvan01@gmail.com');
define('SMTP_PASSWORD', 'txsz izsj ignz hesj'); // Use App Password for Gmail
define('SMTP_ENCRYPTION', 'tls'); // 'tls' or 'ssl'

// Aliases for compatibility
define('SMTP_USER', SMTP_USERNAME);
define('SMTP_PASS', SMTP_PASSWORD);

// ==========================================
// OPTION 3: SendGrid API (Recommended for production)
// ==========================================
/*
define('EMAIL_METHOD', 'sendgrid');
define('SENDGRID_API_KEY', 'your-sendgrid-api-key');
*/

// ==========================================
// Default Email Settings
// ==========================================
define('FROM_EMAIL', 'laxmiranjvan01@gmail.com'); // Changed to match your Gmail
define('FROM_NAME', 'Campus Event Manager');
define('REPLY_TO_EMAIL', 'laxmiranjvan01@gmail.com'); // Changed to match your Gmail

// Aliases for compatibility
define('SMTP_FROM', FROM_EMAIL);
define('SMTP_FROM_NAME', FROM_NAME);

/**
 * Send Email Function
 * Universal email sender that works with all methods
 */
function sendEmail($to, $to_name, $subject, $html_body, $plain_body = '') {
    $method = EMAIL_METHOD;
    
    // Use HTML body as plain text if not provided
    if(empty($plain_body)) {
        $plain_body = strip_tags($html_body);
    }
    
    switch($method) {
        case 'php_mail':
            return sendEmailPHPMail($to, $to_name, $subject, $html_body);
            
        case 'smtp':
            return sendEmailSMTP($to, $to_name, $subject, $html_body, $plain_body);
            
        case 'sendgrid':
            return sendEmailSendGrid($to, $to_name, $subject, $html_body, $plain_body);
            
        default:
            return sendEmailPHPMail($to, $to_name, $subject, $html_body);
    }
}

/**
 * Method 1: PHP mail() function
 */
function sendEmailPHPMail($to, $to_name, $subject, $html_body) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
    $headers .= "From: " . FROM_NAME . " <" . FROM_EMAIL . ">" . "\r\n";
    $headers .= "Reply-To: " . REPLY_TO_EMAIL . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    return mail($to, $subject, $html_body, $headers);
}

/**
 * Method 2: SMTP using PHPMailer
 */
function sendEmailSMTP($to, $to_name, $subject, $html_body, $plain_body) {
    // Check if PHPMailer is available
    if(!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log("PHPMailer not found. Install it with: composer require phpmailer/phpmailer");
        return false; // Don't fallback, return false to show error
    }
    
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;
        
        // Disable SSL verification for localhost testing (remove in production)
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($to, $to_name);
        $mail->addReplyTo(REPLY_TO_EMAIL, FROM_NAME);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html_body;
        $mail->AltBody = $plain_body;
        
        // Enable verbose debug output for testing
        // $mail->SMTPDebug = 2; // Uncomment to see detailed debug info
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("SMTP Error: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Method 3: SendGrid API
 */
function sendEmailSendGrid($to, $to_name, $subject, $html_body, $plain_body) {
    $email_data = [
        'personalizations' => [[
            'to' => [['email' => $to, 'name' => $to_name]],
            'subject' => $subject
        ]],
        'from' => [
            'email' => FROM_EMAIL,
            'name' => FROM_NAME
        ],
        'reply_to' => [
            'email' => REPLY_TO_EMAIL,
            'name' => FROM_NAME
        ],
        'content' => [
            ['type' => 'text/plain', 'value' => $plain_body],
            ['type' => 'text/html', 'value' => $html_body]
        ]
    ];
    
    $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($email_data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . SENDGRID_API_KEY,
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($http_code >= 200 && $http_code < 300);
}

/**
 * Email Template Helper
 * Creates beautiful HTML email templates
 */
function getEmailTemplate($title, $content, $footer_text = '') {
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    
    if(empty($footer_text)) {
        $footer_text = "This is an automated message from Campus Event Manager.";
    }
    
    return "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f4f5f7; }
        .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 28px; font-weight: 700; }
        .header p { margin: 10px 0 0 0; opacity: 0.95; font-size: 15px; }
        .content { padding: 40px 30px; }
        .button { display: inline-block; background: #667eea; color: white !important; padding: 14px 32px; text-decoration: none; border-radius: 8px; font-weight: 600; margin: 20px 0; box-shadow: 0 4px 12px rgba(102,126,234,0.3); }
        .button:hover { background: #5568d3; }
        .info-box { background: #f7fafc; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #667eea; }
        .info-item { margin: 12px 0; }
        .info-label { color: #718096; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-value { font-size: 16px; font-weight: 600; color: #2d3748; margin-top: 4px; }
        .footer { background: #f7fafc; padding: 30px; text-align: center; color: #718096; font-size: 13px; border-top: 1px solid #e2e8f0; }
        .footer p { margin: 8px 0; }
        @media only screen and (max-width: 600px) {
            .container { margin: 10px; border-radius: 8px; }
            .header { padding: 30px 20px; }
            .content { padding: 30px 20px; }
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>🎓 Campus Event Manager</h1>
            <p>" . htmlspecialchars($title) . "</p>
        </div>
        <div class='content'>
            " . $content . "
        </div>
        <div class='footer'>
            <p><strong>" . htmlspecialchars($footer_text) . "</strong></p>
            <p style='color: #a0aec0; margin-top: 15px;'>Sent on " . date('F d, Y \a\t h:i A') . "</p>
            <p style='margin-top: 15px;'>
                <a href='" . $base_url . "' style='color: #667eea; text-decoration: none;'>Visit Campus Event Manager</a>
            </p>
        </div>
    </div>
</body>
</html>";
}

/**
 * Log email sending
 */
function logEmail($user_id, $recipient_email, $subject) {
    global $conn;
    
    if(!isset($conn) || !$conn) {
        return false;
    }
    
    $user_id = intval($user_id);
    $recipient_email = mysqli_real_escape_string($conn, $recipient_email);
    $subject = mysqli_real_escape_string($conn, $subject);
    
    $sql = "INSERT INTO email_logs (user_id, recipient_email, subject, sent_date) 
            VALUES ($user_id, '$recipient_email', '$subject', NOW())";
    
    return mysqli_query($conn, $sql);
}
?>
<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid Request Method'
    ]);
    exit;
}

// 1. Capture and Validate Inputs
$email       = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$message     = filter_input(INPUT_POST, 'message', FILTER_UNSAFE_RAW);
$msgType     = filter_input(INPUT_POST, 'message_type', FILTER_UNSAFE_RAW);
$companyName = filter_input(INPUT_POST, 'company_name', FILTER_UNSAFE_RAW);

if (!$email || !$message || !$msgType || !$companyName) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing or invalid required fields (email, message, message_type, company_name)'
    ]);
    exit;
}

// 2. Determine Theme Variables based on Message Type
$msgType = strtolower(trim($msgType));
switch ($msgType) {
    case 'otp':
        $themeColor = '#8505ff'; // Purple
        $titleText  = 'Verification Code';
        break;
    case 'alert':
        $themeColor = '#dc3545'; // Red
        $titleText  = 'Urgent Alert';
        break;
    case 'warning':
        $themeColor = '#ffc107'; // Yellow/Amber
        $titleText  = 'Warning Notification';
        break;
    case 'news':
    case 'update':
        $themeColor = '#0d6efd'; // Blue
        $titleText  = 'New Update';
        break;
    default:
        $themeColor = '#212529'; // Dark Grey (Fallback)
        $titleText  = 'Notification';
        break;
}

// Sanitize outputs for HTML rendering
$safeMessage     = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
$safeCompanyName = htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8');
$safeTitleText   = htmlspecialchars($titleText, ENT_QUOTES, 'UTF-8');

$mail = new PHPMailer(true);

try {
    // SMTP Configuration
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = getenv('SMTP_EMAIL');
    $mail->Password   = getenv('SMTP_PASSWORD');
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Sender & Recipient
    $mail->setFrom(getenv('SMTP_EMAIL'), $safeCompanyName);
    $mail->addAddress($email);

    // Email Content
    $mail->isHTML(true);
    $mail->Subject = "[{$safeCompanyName}] {$safeTitleText}";

    // Handle structural display for OTP vs regular messages
    $contentDisplay = ($msgType === 'otp') 
        ? '<div style="margin:30px 0;text-align:center;font-size:42px;font-weight:bold;letter-spacing:8px;color:'.$themeColor.';">'.$safeMessage.'</div>'
        : '<p style="font-size:16px;color:#333333;line-height:1.6;margin:20px 0;">'.$safeMessage.'</p>';

    $mail->Body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>'.$safeCompanyName.' | '.$safeTitleText.'</title>
    </head>
    <body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,sans-serif;">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td align="center" style="padding:30px 15px;">
                    <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,0.05);">
                        
                        <!-- Header Banner -->
                        <tr>
                            <td style="background:'.$themeColor.';color:#ffffff;padding:25px;text-align:center;">
                                <h1 style="margin:0;font-size:26px;font-weight:600;">'.$safeCompanyName.'</h1>
                            </td>
                        </tr>

                        <!-- Content Body -->
                        <tr>
                            <td style="padding:40px;background:#ffffff;">
                                <h2 style="margin-top:0;color:#222222;font-size:20px;">'.$safeTitleText.'</h2>
                                
                                '.$contentDisplay.'

                                <p style="font-size:14px;color:#777777;margin-top:30px;">
                                    If you have any questions, please contact support.
                                </p>
                                <p style="font-size:16px;color:#222222;margin-top:20px;margin-bottom:0;">
                                    <strong>The '.$safeCompanyName.' Team</strong>
                                </p>
                            </td>
                        </tr>

                        <!-- Footer -->
                        <tr>
                            <td style="background:#f8f8f8;padding:20px;text-align:center;font-size:12px;color:#777777;border-top:1px solid #eeeeee;">
                                &copy; '.date('Y').' '.$safeCompanyName.'. All rights reserved.
                                <br><br>
                                This is an automated notification. Please do not reply directly to this email.
                            </td>
                        </tr>

                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';

    // Plain text fallback
    $mail->AltBody = "[$safeCompanyName] $safeTitleText\n\n" . strip_tags($message) . "\n\nBest regards,\nThe $safeCompanyName Team";

    $mail->send();

    echo json_encode([
        'status' => 'success',
        'message' => 'Email notification sent successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Mailer Error: ' . $mail->ErrorInfo
    ]);
}
?>

<?php
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/phpmailer/phpmailer/src/Exception.php';
require __DIR__ . '/phpmailer/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Ungültige Anfrage.'
    ]);
    exit;
}

$ownerMail = "maike.kropff-personaltraining@outlook.de";

// Eingaben holen und bereinigen
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');

// Validierung
if (empty($name) || empty($email)) {
    echo json_encode([
        'success' => false,
        'message' => 'Bitte Name und E-Mail ausfüllen.'
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'Bitte eine gültige E-Mail-Adresse eingeben.'
    ]);
    exit;
}

// Sichere Ausgabe für HTML-Mail
$safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$safeMessage = nl2br(htmlspecialchars($message ?: 'Keine Nachricht angegeben', ENT_QUOTES, 'UTF-8'));

// Empfänger
$to = $ownerMail;

$mail = new PHPMailer(true);

try {
    // SMTP aktivieren
    $mail->isSMTP();
    $mail->Host       = 'smtp.strato.de';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'webmaster@bit-ka.de';
    $mail->Password   = 'Lefibu&9631';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Optional fürs Debuggen:
    // $mail->SMTPDebug = 2;

    // Absender & Empfänger
    $mail->setFrom('no-reply@bit-ka.de', 'Website Kontakt');
    $mail->addAddress($to);
    $mail->addReplyTo($email, $name);

    // Inhalt
    $mail->CharSet = 'UTF-8';
    $mail->Subject = 'Neue Anfrage über das Kontaktformular';
    $mail->isHTML(true);

    $mail->Body = '
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <title>Neue Anfrage</title>
    </head>
    <body style="margin:0; padding:0; background-color:#f4f4f7; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4f4f7; margin:0; padding:30px 15px;">
            <tr>
                <td align="center">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,0.08);">
                        
                        <tr>
                            <td style="background:linear-gradient(135deg, #111827, #374151); padding:32px 40px;">
                                <h1 style="margin:0; font-size:28px; line-height:1.3; color:#ffffff;">Neue Kontaktanfrage</h1>
                                <p style="margin:10px 0 0; font-size:15px; color:#d1d5db;">
                                    Es wurde eine neue Nachricht über das Kontaktformular gesendet.
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:32px 40px 20px;">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                    <tr>
                                        <td style="padding:0 0 18px;">
                                            <div style="font-size:12px; font-weight:bold; text-transform:uppercase; letter-spacing:0.08em; color:#6b7280; margin-bottom:6px;">Name</div>
                                            <div style="font-size:16px; color:#111827; background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:14px 16px;">
                                                ' . $safeName . '
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:0 0 18px;">
                                            <div style="font-size:12px; font-weight:bold; text-transform:uppercase; letter-spacing:0.08em; color:#6b7280; margin-bottom:6px;">E-Mail</div>
                                            <div style="font-size:16px; color:#111827; background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:14px 16px;">
                                                <a href="mailto:' . $safeEmail . '" style="color:#2563eb; text-decoration:none;">' . $safeEmail . '</a>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:0 0 8px;">
                                            <div style="font-size:12px; font-weight:bold; text-transform:uppercase; letter-spacing:0.08em; color:#6b7280; margin-bottom:6px;">Nachricht</div>
                                            <div style="font-size:16px; line-height:1.7; color:#111827; background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:16px;">
                                                ' . $safeMessage . '
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:0 40px 32px;">
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                    <tr>
                                        <td style="border-radius:10px; background-color:#111827;">
                                            <a href="mailto:' . rawurlencode($email) . '" style="display:inline-block; padding:14px 22px; font-size:15px; font-weight:bold; color:#ffffff; text-decoration:none;">
                                                Direkt antworten
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:20px 40px; background-color:#f9fafb; border-top:1px solid #e5e7eb;">
                                <p style="margin:0; font-size:13px; line-height:1.6; color:#6b7280;">
                                    Diese E-Mail wurde automatisch über das Kontaktformular deiner Website versendet.
                                </p>
                            </td>
                        </tr>

                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';

    $mail->AltBody =
        "Neue Anfrage über das Kontaktformular\n\n" .
        "Name: " . $name . "\n" .
        "E-Mail: " . $email . "\n" .
        "Nachricht:\n" . ($message ?: 'Keine Nachricht angegeben') . "\n";

    // Senden
    $mail->send();

    echo json_encode([
        'success' => true,
        'message' => 'Danke für deine Anfrage, ich melde mich zeitnah bei dir.'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Die E-Mail konnte nicht versendet werden.',
        'error' => $mail->ErrorInfo
    ]);
}
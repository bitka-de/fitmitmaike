<?php
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/phpmailer/phpmailer/src/Exception.php';
require __DIR__ . '/phpmailer/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Ungültige Anfrage.']);
    exit;
}

$ownerMail = 'maike.kropff-personaltraining@outlook.de';

$name  = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$kurs  = trim($_POST['kurs'] ?? '');
$fragen = trim($_POST['fragen'] ?? '');

if (empty($name) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Bitte Name und E-Mail ausfüllen.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Bitte eine gültige E-Mail-Adresse eingeben.']);
    exit;
}

$safeName  = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$safeKurs  = htmlspecialchars($kurs, ENT_QUOTES, 'UTF-8');
$safeFragen = htmlspecialchars($fragen, ENT_QUOTES, 'UTF-8');

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.strato.de';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'webmaster@bit-ka.de';
    $mail->Password   = 'Lefibu&9631';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('no-reply@bit-ka.de', 'Website Kursanmeldung');
    $mail->addAddress($ownerMail);
    $mail->addReplyTo($email, $name);

    $mail->CharSet = 'UTF-8';
    $mail->Subject = 'Neue Kursanmeldung: ' . $kurs;
    $mail->isHTML(true);

    $mail->Body = '
    <!DOCTYPE html>
    <html lang="de">
    <head><meta charset="UTF-8"><title>Neue Kursanmeldung</title></head>
    <body style="margin:0;padding:0;background-color:#f4f4f7;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4f4f7;padding:30px 15px;">
            <tr>
                <td align="center">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px;background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,0.08);">

                        <tr>
                            <td style="background:linear-gradient(135deg,#111827,#374151);padding:32px 40px;">
                                <h1 style="margin:0;font-size:26px;color:#ffffff;">Neue Kursanmeldung</h1>
                                <p style="margin:10px 0 0;font-size:15px;color:#d1d5db;">
                                    Eine Person hat sich für einen Kurs angemeldet.
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:32px 40px 20px;">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                    <tr>
                                        <td style="padding:0 0 18px;">
                                            <div style="font-size:12px;font-weight:bold;text-transform:uppercase;letter-spacing:0.08em;color:#6b7280;margin-bottom:6px;">Kurs</div>
                                            <div style="font-size:16px;color:#111827;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:14px 16px;">
                                                ' . $safeKurs . '
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:0 0 18px;">
                                            <div style="font-size:12px;font-weight:bold;text-transform:uppercase;letter-spacing:0.08em;color:#6b7280;margin-bottom:6px;">Name</div>
                                            <div style="font-size:16px;color:#111827;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:14px 16px;">
                                                ' . $safeName . '
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:0 0 8px;">
                                            <div style="font-size:12px;font-weight:bold;text-transform:uppercase;letter-spacing:0.08em;color:#6b7280;margin-bottom:6px;">E-Mail</div>
                                            <div style="font-size:16px;color:#111827;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:14px 16px;">
                                                <a href="mailto:' . $safeEmail . '" style="color:#2563eb;text-decoration:none;">' . $safeEmail . '</a>
                                            </div>
                                        </td>
                                    </tr>' . ($safeFragen !== '' ? '
                                    <tr>
                                        <td style="padding:0 0 8px;">
                                            <div style="font-size:12px;font-weight:bold;text-transform:uppercase;letter-spacing:0.08em;color:#6b7280;margin-bottom:6px;">Fragen</div>
                                            <div style="font-size:16px;color:#111827;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:14px 16px;white-space:pre-wrap;word-wrap:break-word;">
                                                ' . $safeFragen . '
                                            </div>
                                        </td>
                                    </tr>' : '')
                                </table>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:0 40px 32px;">
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                    <tr>
                                        <td style="border-radius:10px;background-color:#111827;">
                                            <a href="mailto:' . $safeEmail . '" style="display:inline-block;padding:14px 22px;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;">
                                                Direkt antworten
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:20px 40px;background-color:#f9fafb;border-top:1px solid #e5e7eb;">
                                <p style="margin:0;font-size:13px;line-height:1.6;color:#6b7280;">
                                    Diese E-Mail wurde automatisch über das Kursanmeldeformular deiner Website versendet.
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
        "Neue Kursanmeldung\n\n" .
        "Kurs: " . $kurs . "\n" .
        "Name: " . $name . "\n" .
        "E-Mail: " . $email . "\n" .
        ($fragen !== '' ? "Fragen: " . $fragen . "\n" : "");

    $mail->send();

    echo json_encode(['success' => true, 'message' => 'Danke für deine Anmeldung! Ich melde mich zeitnah bei dir.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Die E-Mail konnte nicht versendet werden.', 'error' => $mail->ErrorInfo]);
}

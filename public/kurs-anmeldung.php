<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/phpmailer/phpmailer/src/Exception.php';
require __DIR__ . '/phpmailer/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ── Config ────────────────────────────────────────────────────────────────────
const SMTP_HOST     = 'smtp.strato.de';
const SMTP_USER     = 'webmaster@bit-ka.de';
const SMTP_PASS     = 'Lefibu&9631';
const SMTP_PORT     = 587;
const FROM_ADDRESS  = 'no-reply@bit-ka.de';
const FROM_NAME     = 'Website Kursanmeldung';
const OWNER_EMAIL   = 'maike.kropff-personaltraining@outlook.de';

// ── Helpers ───────────────────────────────────────────────────────────────────
function jsonExit(bool $success, string $message, string $error = ''): never
{
    $payload = ['success' => $success, 'message' => $message];
    if ($error !== '') {
        $payload['error'] = $error;
    }
    echo json_encode($payload);
    exit;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function infoRow(string $label, string $value, bool $last = false, string $extraStyle = ''): string
{
    $padding = $last ? 'padding:0 0 8px;' : 'padding:0 0 18px;';
    return '
        <tr>
            <td style="' . $padding . '">
                <div style="font-size:12px;font-weight:bold;text-transform:uppercase;letter-spacing:0.08em;color:#6b7280;margin-bottom:6px;">' . $label . '</div>
                <div style="font-size:16px;color:#111827;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:14px 16px;' . $extraStyle . '">' . $value . '</div>
            </td>
        </tr>';
}

function buildHtmlBody(string $safeName, string $safeEmail, string $safeKurs, string $safeFragen): string
{
    $fragenRow = $safeFragen !== ''
        ? infoRow('Fragen', $safeFragen, true, 'white-space:pre-wrap;word-wrap:break-word;')
        : '';

    return '<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"><title>Neue Kursanmeldung</title></head>
<body style="margin:0;padding:0;background-color:#f4f4f7;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4f4f7;padding:30px 15px;">
<tr><td align="center">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px;background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,0.08);">

    <tr>
        <td style="background:linear-gradient(135deg,#111827,#374151);padding:32px 40px;">
            <h1 style="margin:0;font-size:26px;color:#ffffff;">Neue Kursanmeldung</h1>
            <p style="margin:10px 0 0;font-size:15px;color:#d1d5db;">Eine Person hat sich für einen Kurs angemeldet.</p>
        </td>
    </tr>

    <tr>
        <td style="padding:32px 40px 20px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                ' . infoRow('Kurs', $safeKurs)
                . infoRow('Name', $safeName)
                . infoRow('E-Mail', '<a href="mailto:' . $safeEmail . '" style="color:#2563eb;text-decoration:none;">' . $safeEmail . '</a>', $fragenRow === '')
                . $fragenRow . '
            </table>
        </td>
    </tr>

    <tr>
        <td style="padding:0 40px 32px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                <tr>
                    <td style="border-radius:10px;background-color:#111827;">
                        <a href="mailto:' . $safeEmail . '" style="display:inline-block;padding:14px 22px;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;">Direkt antworten</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <tr>
        <td style="padding:20px 40px;background-color:#f9fafb;border-top:1px solid #e5e7eb;">
            <p style="margin:0;font-size:13px;line-height:1.6;color:#6b7280;">Diese E-Mail wurde automatisch über das Kursanmeldeformular deiner Website versendet.</p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>';
}

function buildTextBody(string $name, string $email, string $kurs, string $fragen): string
{
    $text = "Neue Kursanmeldung\n\n"
        . "Kurs:   {$kurs}\n"
        . "Name:   {$name}\n"
        . "E-Mail: {$email}\n";

    if ($fragen !== '') {
        $text .= "Fragen: {$fragen}\n";
    }

    return $text;
}

// ── Request validation ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonExit(false, 'Ungültige Anfrage.');
}

$name   = trim((string) ($_POST['name']   ?? ''));
$email  = trim((string) ($_POST['email']  ?? ''));
$kurs   = trim((string) ($_POST['kurs']   ?? ''));
$fragen = trim((string) ($_POST['fragen'] ?? ''));

if ($name === '' || $email === '') {
    jsonExit(false, 'Bitte Name und E-Mail ausfüllen.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonExit(false, 'Bitte eine gültige E-Mail-Adresse eingeben.');
}

// ── Send mail ─────────────────────────────────────────────────────────────────
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom(FROM_ADDRESS, FROM_NAME);
    $mail->addAddress(OWNER_EMAIL);
    $mail->addReplyTo($email, $name);

    $mail->Subject = 'Neue Kursanmeldung: ' . $kurs;
    $mail->isHTML(true);
    $mail->Body    = buildHtmlBody(e($name), e($email), e($kurs), e($fragen));
    $mail->AltBody = buildTextBody($name, $email, $kurs, $fragen);

    $mail->send();

    jsonExit(true, 'Danke für deine Anmeldung! Ich melde mich zeitnah bei dir.');

} catch (Exception $e) {
    jsonExit(false, 'Die E-Mail konnte nicht versendet werden.', $mail->ErrorInfo);
}

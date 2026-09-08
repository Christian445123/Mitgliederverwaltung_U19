<?php
/**
 * Schlanker SMTP-Client ohne externe Abhängigkeiten (kein Composer nötig).
 * Zugangsdaten stehen in config/config.php (SMTP_*-Konstanten).
 *
 * Verwendung:
 *   sendMail('empfaenger@example.org', 'Betreff', 'Text-Version', '<p>HTML-Version</p>');
 *
 * Gibt bei Erfolg true zurück, wirft bei Konfigurations-/Übertragungsfehlern
 * eine RuntimeException (Aufrufer entscheidet, wie das dem Nutzer angezeigt wird).
 */

function sendMail(string $to, string $subject, string $textBody, ?string $htmlBody = null): bool
{
    if (!defined('SMTP_HOST') || SMTP_HOST === '') {
        throw new RuntimeException('E-Mail-Versand ist nicht konfiguriert (SMTP_HOST fehlt in config.php).');
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Ungültige Empfänger-E-Mail-Adresse.');
    }

    $host = SMTP_HOST;
    $port = defined('SMTP_PORT') ? (int) SMTP_PORT : 587;
    $encryption = defined('SMTP_ENCRYPTION') ? strtolower(SMTP_ENCRYPTION) : 'tls'; // 'tls'/'starttls', 'ssl' oder ''
    if ($encryption === 'starttls') {
        $encryption = 'tls';
    }
    $username = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
    $password = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
    $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : $username;
    $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Mitgliederverwaltung';

    $transport = $encryption === 'ssl' ? 'ssl://' : '';
    $socket = @stream_socket_client(
        $transport . $host . ':' . $port,
        $errno,
        $errstr,
        15,
        STREAM_CLIENT_CONNECT
    );
    if (!$socket) {
        throw new RuntimeException("Verbindung zum Mailserver fehlgeschlagen: $errstr");
    }

    try {
        smtpExpect($socket, 220);
        smtpCommand($socket, 'EHLO ' . (parse_url(BASE_URL, PHP_URL_HOST) ?: 'localhost'), 250);

        if ($encryption === 'tls') {
            smtpCommand($socket, 'STARTTLS', 220);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('TLS-Verschlüsselung zum Mailserver konnte nicht aufgebaut werden.');
            }
            smtpCommand($socket, 'EHLO ' . (parse_url(BASE_URL, PHP_URL_HOST) ?: 'localhost'), 250);
        }

        if ($username !== '') {
            smtpCommand($socket, 'AUTH LOGIN', 334);
            smtpCommand($socket, base64_encode($username), 334);
            smtpCommand($socket, base64_encode($password), 235);
        }

        smtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', 250);
        smtpCommand($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtpCommand($socket, 'DATA', 354);

        $boundary = 'bnd_' . bin2hex(random_bytes(12));
        $headers = [
            'From: ' . encodeMailHeaderWord($fromName) . ' <' . $fromEmail . '>',
            'To: <' . $to . '>',
            'Subject: ' . encodeMailHeaderWord($subject),
            'MIME-Version: 1.0',
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . (parse_url(BASE_URL, PHP_URL_HOST) ?: 'localhost') . '>',
        ];

        if ($htmlBody !== null) {
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
            $body = "--$boundary\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                . $textBody . "\r\n\r\n"
                . "--$boundary\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                . $htmlBody . "\r\n\r\n"
                . "--$boundary--\r\n";
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: 8bit';
            $body = $textBody . "\r\n";
        }

        // Punkt am Zeilenanfang gemäß SMTP-Protokoll escapen (Byte-Stuffing)
        $body = preg_replace('/^\./m', '..', $body);

        $message = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n";
        fwrite($socket, $message);
        smtpReadResponse($socket, 250);

        smtpCommand($socket, 'QUIT', 221);
    } finally {
        fclose($socket);
    }

    return true;
}

/** Kopfzeilen-Wert MIME-kodieren, falls Nicht-ASCII-Zeichen enthalten sind (z. B. Umlaute). */
function encodeMailHeaderWord(string $value): string
{
    if (preg_match('/^[\x20-\x7E]*$/', $value)) {
        return $value;
    }
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function smtpCommand($socket, string $command, $expectedCode): void
{
    fwrite($socket, $command . "\r\n");
    smtpReadResponse($socket, $expectedCode);
}

function smtpReadResponse($socket, $expectedCode): string
{
    $expected = (array) $expectedCode;
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        // Letzte Zeile einer (ggf. mehrzeiligen) Antwort hat ein Leerzeichen nach dem Code, keinen Bindestrich
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }
    if ($response === '') {
        throw new RuntimeException('Keine Antwort vom Mailserver erhalten.');
    }
    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $expected, true)) {
        throw new RuntimeException('Unerwartete Mailserver-Antwort: ' . trim($response));
    }
    return $response;
}

function smtpExpect($socket, $expectedCode): void
{
    smtpReadResponse($socket, $expectedCode);
}

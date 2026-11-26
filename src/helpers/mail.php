<?php

declare(strict_types=1);

function sendAppMail(array $app, string $to, string $subject, string $htmlBody, string $textBody = ''): bool
{
    $mail = $app['mail'];
    $from = $mail['from_address'];
    $fromName = $mail['from_name'];
    $host = (string) $mail['host'];
    $port = (int) $mail['port'];
    $user = (string) $mail['user'];
    $pass = (string) $mail['pass'];
    $encryption = strtolower((string) $mail['encryption']);

    if (!validateEmail($to) || !validateEmail($from)) {
        return false;
    }

    $subject = trim(str_replace(["\r", "\n"], '', $subject));
    $boundary = 'b_' . bin2hex(random_bytes(12));
    $textBody = $textBody !== '' ? $textBody : strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));

    $headers = [];
    $headers[] = 'From: ' . mb_encode_mimeheader($fromName, 'UTF-8') . ' <' . $from . '>';
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
    $headers[] = 'X-Mailer: SENIAT-Control';

    $message = "--{$boundary}\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $message .= $textBody . "\r\n";
    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $message .= $htmlBody . "\r\n";
    $message .= "--{$boundary}--\r\n";

    // Intento SMTP simple si está configurado
    if ($host !== '' && $host !== 'smtp.example.com' && $user !== '') {
        $sent = smtpSend($host, $port, $encryption, $user, $pass, $from, $to, $subject, implode("\r\n", $headers), $message);
        if ($sent) {
            return true;
        }
        error_log('SMTP send failed, falling back to mail() for ' . $to);
    }

    $headerStr = implode("\r\n", $headers);
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $message, $headerStr);
}

function smtpSend(string $host, int $port, string $encryption, string $user, string $pass, string $from, string $to, string $subject, string $headers, string $body): bool
{
    $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $fp = @stream_socket_client($remote, $errno, $errstr, 10, STREAM_CLIENT_CONNECT);
    if (!$fp) {
        error_log('SMTP connect error: ' . $errstr);
        return false;
    }
    stream_set_timeout($fp, 10);
    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };
    $cmd = function (string $command, string $expect) use ($fp, $read): bool {
        fwrite($fp, $command . "\r\n");
        $res = $read();
        return str_starts_with($res, $expect);
    };

    $read();
    if (!$cmd('EHLO localhost', '250')) { fclose($fp); return false; }
    if ($encryption === 'tls') {
        if (!$cmd('STARTTLS', '220')) { fclose($fp); return false; }
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { fclose($fp); return false; }
        if (!$cmd('EHLO localhost', '250')) { fclose($fp); return false; }
    }
    if (!$cmd('AUTH LOGIN', '334')) { fclose($fp); return false; }
    if (!$cmd(base64_encode($user), '334')) { fclose($fp); return false; }
    if (!$cmd(base64_encode($pass), '235')) { fclose($fp); return false; }
    if (!$cmd('MAIL FROM:<' . $from . '>', '250')) { fclose($fp); return false; }
    if (!$cmd('RCPT TO:<' . $to . '>', '250')) { fclose($fp); return false; }
    if (!$cmd('DATA', '354')) { fclose($fp); return false; }

    $payload = "To: <{$to}>\r\nSubject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n{$headers}\r\n\r\n{$body}\r\n.";
    if (!$cmd($payload, '250')) { fclose($fp); return false; }
    $cmd('QUIT', '221');
    fclose($fp);
    return true;
}

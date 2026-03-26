<?php
function sendWelcomeEmail(string $toEmail, string $toName): bool
{
    $cfg = loadMailConfig();

    $host = $cfg['smtp_host'] ?? '';
    $user = $cfg['smtp_user'] ?? '';
    $pass = $cfg['smtp_pass'] ?? '';
    $port = (int)($cfg['smtp_port'] ?? 587);

    if (!$host || !$user || !$pass) {
        error_log("SMTP: missing config. host={$host} user={$user}");
        return false;
    }

    $subject = "Your DriveNow account has been created";
    $body    = "Hi {$toName},\r\n\r\nThank you for registering with DriveNow!\r\n\r\nYour account has been successfully created. You can now log in and start booking cars.\r\n\r\nThank you,\r\nDriveNow Team";

    return sendViaSmtp($host, $port, $user, $pass, $user, $toEmail, $toName, $subject, $body);
}

function sendViaSmtp(string $host, int $port, string $user, string $pass,
                      string $from, string $toEmail, string $toName,
                      string $subject, string $body): bool
{
    $errno = 0; $errstr = '';
    $smtp = @fsockopen("tcp://{$host}", $port, $errno, $errstr, 15);
    if (!$smtp) {
        error_log("SMTP fsockopen failed [{$errno}]: {$errstr}");
        return false;
    }

    stream_set_timeout($smtp, 15);

    $read = function() use ($smtp) {
        $data = '';
        while ($line = fgets($smtp, 1024)) {
            $data .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        return $data;
    };
    $send = function(string $cmd) use ($smtp) {
        fwrite($smtp, $cmd . "\r\n");
    };

    $r = $read(); // 220 greeting
    if (strpos($r, '220') === false) { error_log("SMTP no greeting: {$r}"); fclose($smtp); return false; }

    $send("EHLO localhost"); $read();
    $send("STARTTLS"); $r = $read();
    if (strpos($r, '220') === false) { error_log("STARTTLS failed: {$r}"); fclose($smtp); return false; }

    if (!stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        error_log("SMTP TLS handshake failed");
        fclose($smtp);
        return false;
    }

    $send("EHLO localhost"); $read();
    $send("AUTH LOGIN"); $read();
    $send(base64_encode($user)); $read();
    $send(base64_encode($pass));
    $r = $read();
    if (strpos($r, '235') === false) {
        error_log("SMTP auth failed: {$r}");
        fclose($smtp);
        return false;
    }

    $send("MAIL FROM:<{$from}>"); $read();
    $send("RCPT TO:<{$toEmail}>"); $read();
    $send("DATA"); $read();

    $send("From: DriveNow <{$from}>");
    $send("To: {$toName} <{$toEmail}>");
    $send("Subject: {$subject}");
    $send("MIME-Version: 1.0");
    $send("Content-Type: text/plain; charset=UTF-8");
    $send("");
    $send($body);
    $send(".");
    $r = $read();

    $send("QUIT");
    fclose($smtp);

    if (strpos($r, '250') === false) {
        error_log("SMTP message rejected: {$r}");
        return false;
    }
    return true;
}

function loadMailConfig(): array
{
    $possiblePaths = [
        '/var/www/private/db-config.ini',
        'C:/xampp/drivenow-private/db-config.ini',
        getenv('USERPROFILE') . '/Herd/drivenow-private/db-config.ini',
        dirname(__DIR__) . '/../../drivenow-private/db-config.ini',
    ];
    foreach ($possiblePaths as $path) {
        if ($path && file_exists($path)) {
            return parse_ini_file($path) ?: [];
        }
    }
    error_log("SMTP: db-config.ini not found in any expected path");
    return [];
}

function sendPaymentConfirmationEmail(string $toEmail, string $toName,
                                       array $booking, string $cardType,
                                       string $card_last4): bool
{
    $car      = $booking['make'] . ' ' . $booking['model'];
    $plate    = $booking['plate_no'];
    $start    = date('d M Y, h:i A', strtotime($booking['start_time']));
    $end      = date('d M Y, h:i A', strtotime($booking['end_time']));
    $amount   = number_format($booking['total_cost'], 2);

    $subject = "Payment Confirmed - DriveNow Booking";
    $body    = "Hi {$toName},\r\n\r\n"
             . "Your payment has been confirmed! Here are your booking details:\r\n\r\n"
             . "Car       : {$car} ({$plate})\r\n"
             . "From      : {$start}\r\n"
             . "To        : {$end}\r\n"
             . "Amount    : \${$amount}\r\n"
             . "Paid with : {$cardType} ending in {$card_last4}\r\n\r\n"
             . "Thank you for choosing DriveNow!\r\n\r\n"
             . "DriveNow Team";

    $cfg  = loadMailConfig();
    $host = $cfg['smtp_host'] ?? 'smtp.gmail.com';
    $port = (int)($cfg['smtp_port'] ?? 587);
    $user = $cfg['smtp_user'] ?? '';
    $pass = $cfg['smtp_pass'] ?? '';

    return sendViaSmtp($host, $port, $user, $pass, $user, $toEmail, $toName, $subject, $body);
}

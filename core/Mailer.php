<?php
// core/Mailer.php
// Lightweight SMTP mailer using PHP's socket functions.
// Supports STARTTLS (port 587) for Brevo (Sendinblue) relay.

class Mailer {

    private string $host;
    private int    $port;
    private string $user;
    private string $pass;
    private string $from;
    private string $fromName;
    private bool   $debug;

    /** @var resource|null */
    private $socket = null;

    public function __construct() {
        $this->host     = getenv('SMTP_HOST') ?: 'smtp-relay.sendinblue.com';
        $this->port     = (int)(getenv('SMTP_PORT') ?: 587);
        $this->user     = getenv('SMTP_USER') ?: '';
        $this->pass     = getenv('SMTP_PASS') ?: '';
        $this->from     = getenv('SMTP_FROM') ?: 'noreply@example.com';
        $this->fromName = getenv('SMTP_FROM_NAME') ?: 'UX Pacific Shop';
        $this->debug    = (getenv('APP_DEBUG') === 'true');
    }

    /**
     * Send a plain-text + HTML email.
     *
     * @param string $to      Recipient email address
     * @param string $subject Email subject
     * @param string $html    HTML body
     * @param string $text    Plain-text fallback body
     * @throws RuntimeException on SMTP failure
     */
    public function send(string $to, string $subject, string $html, string $text = ''): void {
        if ($text === '') {
            $text = strip_tags($html);
        }

        $boundary = '==BOUNDARY_' . bin2hex(random_bytes(8));
        $msgId    = '<' . bin2hex(random_bytes(8)) . '@uxpacific.com>';
        $date     = date('r');

        // Build multipart/alternative message
        $body  = "MIME-Version: 1.0\r\n";
        $body .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $body .= "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= quoted_printable_encode($text) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= quoted_printable_encode($html) . "\r\n";
        $body .= "--{$boundary}--\r\n";

        // Headers (separate from DATA body)
        $fromEncoded = '=?UTF-8?B?' . base64_encode($this->fromName) . '?=';
        $headers  = "From: {$fromEncoded} <{$this->from}>\r\n";
        $headers .= "To: {$to}\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "Date: {$date}\r\n";
        $headers .= "Message-ID: {$msgId}\r\n";

        $this->smtpConnect();
        $this->smtpSend($to, $headers . $body);
        $this->smtpDisconnect();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private SMTP protocol methods
    // ──────────────────────────────────────────────────────────────────────────

    private function smtpConnect(): void {
        $errno  = 0;
        $errstr = '';

        // Connect plain first (STARTTLS upgrades the connection)
        $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, 10);
        if (!$this->socket) {
            throw new RuntimeException("SMTP connect failed [{$errno}]: {$errstr}");
        }
        stream_set_timeout($this->socket, 10);

        $this->expect('220');                                     // Server greeting
        $this->cmd("EHLO " . gethostname());                      // Introduce ourselves
        $this->expect('250');
        $this->cmd("STARTTLS");                                   // Request TLS upgrade
        $this->expect('220');

        // Upgrade socket to TLS
        if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException("SMTP STARTTLS negotiation failed");
        }

        $this->cmd("EHLO " . gethostname());                      // Re-introduce after TLS
        $this->expect('250');
        $this->cmd("AUTH LOGIN");                                 // Begin auth
        $this->expect('334');
        $this->cmd(base64_encode($this->user));                   // Username
        $this->expect('334');
        $this->cmd(base64_encode($this->pass));                   // Password
        $this->expect('235');                                     // Auth successful
    }

    private function smtpSend(string $to, string $fullMessage): void {
        $this->cmd("MAIL FROM:<{$this->from}>");
        $this->expect('250');
        $this->cmd("RCPT TO:<{$to}>");
        $this->expect('250');
        $this->cmd("DATA");
        $this->expect('354');

        // Dot-stuff message lines (RFC 5321 §4.5.2)
        $lines = explode("\r\n", $fullMessage);
        foreach ($lines as $line) {
            if ($line === '.') $line = '..';
            fwrite($this->socket, $line . "\r\n");
        }
        fwrite($this->socket, ".\r\n");                          // End DATA
        $this->expect('250');
    }

    private function smtpDisconnect(): void {
        if ($this->socket) {
            $this->cmd("QUIT");
            fclose($this->socket);
            $this->socket = null;
        }
    }

    private function cmd(string $command): void {
        if ($this->debug) {
            error_log("[SMTP >>] {$command}");
        }
        fwrite($this->socket, $command . "\r\n");
    }

    private function expect(string $code): string {
        $response = '';
        while ($line = fgets($this->socket, 512)) {
            if ($this->debug) {
                error_log("[SMTP <<] " . rtrim($line));
            }
            $response .= $line;
            // Last line of multi-line response has a space after the code
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        if (substr($response, 0, 3) !== $code) {
            throw new RuntimeException("SMTP unexpected response (expected {$code}): " . trim($response));
        }
        return $response;
    }
}

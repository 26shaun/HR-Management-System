<?php
/**
 * Dayflow HRMS - Lightweight Native SMTP Mailer
 * Connects directly to SMTP servers (Gmail, Brevo, Mailtrap, SendGrid, Outlook) without heavy external dependencies.
 */

class DayflowSMTPMailer {
    private $host;
    private $port;
    private $username;
    private $password;
    private $encryption;
    private $fromEmail;
    private $fromName;
    private $timeout = 15;
    private $debug = false;

    public function __construct($host, $port, $username, $password, $encryption = 'tls', $fromEmail = null, $fromName = 'Dayflow HRMS') {
        $this->host = $host;
        $this->port = (int)$port;
        $this->username = $username;
        $this->password = $password;
        $this->encryption = strtolower($encryption);
        $this->fromEmail = $fromEmail ?: $username;
        $this->fromName = $fromName;
    }

    public function send($toEmail, $toName, $subject, $htmlBody) {
        // If SMTP credentials are empty, fallback to basic mail()
        if (empty($this->host) || empty($this->username) || empty($this->password)) {
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: {$this->fromName} <{$this->fromEmail}>\r\n";
            return @mail($toEmail, $subject, $htmlBody, $headers);
        }

        $hostPrefix = ($this->encryption === 'ssl') ? 'ssl://' : '';
        $socket = @fsockopen($hostPrefix . $this->host, $this->port, $errno, $errstr, $this->timeout);

        if (!$socket) {
            error_log("SMTP Connection Error: {$errstr} ({$errno})");
            return false;
        }

        // Set stream timeout
        stream_set_timeout($socket, $this->timeout);

        $response = $this->readResponse($socket);
        if (substr($response, 0, 3) != '220') {
            fclose($socket);
            return false;
        }

        // EHLO
        $this->sendCommand($socket, "EHLO " . gethostname());
        $response = $this->readResponse($socket);

        // STARTTLS if using TLS on port 587
        if ($this->encryption === 'tls') {
            $this->sendCommand($socket, "STARTTLS");
            $response = $this->readResponse($socket);
            if (substr($response, 0, 3) != '220') {
                fclose($socket);
                return false;
            }

            // Upgrade connection to TLS
            $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (!$crypto) {
                fclose($socket);
                return false;
            }

            // Re-send EHLO after TLS handshake
            $this->sendCommand($socket, "EHLO " . gethostname());
            $this->readResponse($socket);
        }

        // AUTH LOGIN
        $this->sendCommand($socket, "AUTH LOGIN");
        $response = $this->readResponse($socket);
        if (substr($response, 0, 3) != '334') {
            fclose($socket);
            return false;
        }

        // Username (base64)
        $this->sendCommand($socket, base64_encode($this->username));
        $response = $this->readResponse($socket);
        if (substr($response, 0, 3) != '334') {
            fclose($socket);
            return false;
        }

        // Password (base64)
        $this->sendCommand($socket, base64_encode($this->password));
        $response = $this->readResponse($socket);
        if (substr($response, 0, 3) != '235') {
            error_log("SMTP Authentication Failed: " . $response);
            fclose($socket);
            return false;
        }

        // MAIL FROM
        $this->sendCommand($socket, "MAIL FROM: <{$this->fromEmail}>");
        $response = $this->readResponse($socket);
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            return false;
        }

        // RCPT TO
        $this->sendCommand($socket, "RCPT TO: <{$toEmail}>");
        $response = $this->readResponse($socket);
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            return false;
        }

        // DATA
        $this->sendCommand($socket, "DATA");
        $response = $this->readResponse($socket);
        if (substr($response, 0, 3) != '354') {
            fclose($socket);
            return false;
        }

        // Construct Email Headers & Body
        $emailData  = "From: =?UTF-8?B?" . base64_encode($this->fromName) . "?= <{$this->fromEmail}>\r\n";
        $emailData .= "To: =?UTF-8?B?" . base64_encode($toName) . "?= <{$toEmail}>\r\n";
        $emailData .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $emailData .= "MIME-Version: 1.0\r\n";
        $emailData .= "Content-Type: text/html; charset=UTF-8\r\n";
        $emailData .= "Date: " . date('r') . "\r\n";
        $emailData .= "\r\n";
        $emailData .= $htmlBody;
        $emailData .= "\r\n.\r\n";

        $this->sendCommand($socket, $emailData);
        $response = $this->readResponse($socket);

        // QUIT
        $this->sendCommand($socket, "QUIT");
        fclose($socket);

        return (substr($response, 0, 3) == '250');
    }

    private function sendCommand($socket, $command) {
        fwrite($socket, $command . "\r\n");
    }

    private function readResponse($socket) {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') {
                break;
            }
        }
        return $response;
    }
}

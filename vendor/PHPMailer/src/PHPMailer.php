<?php
namespace PHPMailer\PHPMailer;

/**
 * Minimal PHPMailer class for SMTP sending.
 * For production, replace with the full PHPMailer library via Composer:
 *   composer require phpmailer/phpmailer
 */
class PHPMailer
{
    const CHARSET_UTF8 = 'UTF-8';
    const ENCODING_QUOTED_PRINTABLE = 'quoted-printable';
    const ENCRYPTION_STARTTLS = 'tls';
    const ENCRYPTION_SMTPS = 'ssl';
    const VERSION = '6.9.1';

    public $Host = 'localhost';
    public $Port = 25;
    public $SMTPAuth = false;
    public $Username = '';
    public $Password = '';
    public $SMTPSecure = '';
    public $SMTPAutoTLS = true;
    public $SMTPDebug = 0;
    public $Timeout = 300;
    public $CharSet = self::CHARSET_UTF8;
    public $Encoding = self::ENCODING_QUOTED_PRINTABLE;
    public $Subject = '';
    public $Body = '';
    public $AltBody = '';
    public $From = '';
    public $FromName = '';
    public $Mailer = 'smtp';
    public $ErrorInfo = '';

    protected $to = [];
    protected $cc = [];
    protected $bcc = [];
    protected $ReplyTo = [];
    protected $exceptions = false;
    protected $smtp = null;

    public function __construct($exceptions = false)
    {
        $this->exceptions = $exceptions;
    }

    public function isSMTP()
    {
        $this->Mailer = 'smtp';
    }

    public function setFrom($address, $name = '')
    {
        $this->From = $address;
        $this->FromName = $name;
    }

    public function addAddress($address, $name = '')
    {
        $this->to[] = [$address, $name];
    }

    public function addCC($address, $name = '')
    {
        $this->cc[] = [$address, $name];
    }

    public function addBCC($address, $name = '')
    {
        $this->bcc[] = [$address, $name];
    }

    public function addReplyTo($address, $name = '')
    {
        $this->ReplyTo[] = [$address, $name];
    }

    public function isHTML($isHtml = true)
    {
        // For this minimal version, we only support plain text
    }

    public function send()
    {
        if ($this->Mailer !== 'smtp') {
            return $this->mailSend();
        }
        return $this->smtpSend();
    }

    protected function mailSend()
    {
        $to = implode(', ', array_map(fn($r) => $r[0], $this->to));
        $headers = "From: {$this->FromName} <{$this->From}>\r\n";
        $headers .= "Content-Type: text/plain; charset={$this->CharSet}\r\n";
        return mail($to, $this->Subject, $this->Body, $headers);
    }

    protected function smtpSend()
    {
        $this->smtp = new SMTP();
        $this->smtp->Timeout = $this->Timeout;

        $host = $this->Host;
        $port = $this->Port;

        // Connect
        if ($this->SMTPSecure === self::ENCRYPTION_SMTPS) {
            $host = 'ssl://' . $host;
        }

        if (!$this->smtp->connect($host, $port, $this->Timeout)) {
            $this->setError('SMTP connect failed');
            return false;
        }

        // EHLO
        if (!$this->smtp->hello(gethostname() ?: 'localhost')) {
            $this->setError('EHLO failed');
            $this->smtp->close();
            return false;
        }

        // STARTTLS
        if ($this->SMTPSecure === self::ENCRYPTION_STARTTLS) {
            if (!$this->smtp->startTLS()) {
                $this->setError('STARTTLS failed');
                $this->smtp->close();
                return false;
            }
            // Re-EHLO after TLS
            if (!$this->smtp->hello(gethostname() ?: 'localhost')) {
                $this->setError('EHLO after TLS failed');
                $this->smtp->close();
                return false;
            }
        }

        // Auth
        if ($this->SMTPAuth) {
            if (!$this->smtp->authenticate($this->Username, $this->Password)) {
                $this->setError('SMTP authentication failed');
                $this->smtp->close();
                return false;
            }
        }

        // MAIL FROM
        if (!$this->smtp->mail($this->From)) {
            $this->setError('MAIL FROM failed');
            $this->smtp->close();
            return false;
        }

        // RCPT TO
        $allRecipients = array_merge($this->to, $this->cc, $this->bcc);
        foreach ($allRecipients as $r) {
            if (!$this->smtp->recipient($r[0])) {
                $this->setError('RCPT TO failed for ' . $r[0]);
                $this->smtp->close();
                return false;
            }
        }

        // DATA
        $header = "Date: " . date('r') . "\r\n";
        $header .= "From: " . $this->formatAddress($this->From, $this->FromName) . "\r\n";
        $header .= "To: " . implode(', ', array_map(fn($r) => $this->formatAddress($r[0], $r[1]), $this->to)) . "\r\n";
        $header .= "Subject: " . $this->Subject . "\r\n";
        $header .= "MIME-Version: 1.0\r\n";
        $header .= "Content-Type: text/plain; charset=" . $this->CharSet . "\r\n";
        $header .= "Content-Transfer-Encoding: 7bit\r\n";
        $header .= "\r\n";
        $message = $header . $this->Body;

        if (!$this->smtp->data($message)) {
            $this->setError('DATA failed');
            $this->smtp->close();
            return false;
        }

        $this->smtp->quit();
        return true;
    }

    protected function formatAddress($email, $name = '')
    {
        if ($name !== '') {
            return '"' . str_replace('"', '\\"', $name) . '" <' . $email . '>';
        }
        return $email;
    }

    protected function setError($msg)
    {
        $this->ErrorInfo = $msg;
        if ($this->exceptions) {
            throw new Exception($msg);
        }
    }
}

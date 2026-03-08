<?php
namespace PHPMailer\PHPMailer;

/**
 * Minimal SMTP class for PHPMailer compatibility.
 * For production, replace with the full PHPMailer library via Composer.
 */
class SMTP
{
    const VERSION = '6.9.1';
    const CRLF = "\r\n";
    const DEFAULT_PORT = 25;
    const DEBUG_OFF = 0;
    const DEBUG_CLIENT = 1;
    const DEBUG_SERVER = 2;

    public $do_debug = self::DEBUG_OFF;
    public $Debugoutput = 'echo';
    public $do_verp = false;
    public $Timeout = 300;
    public $Timelimit = 300;

    protected $smtp_conn;
    protected $error = [];
    protected $helo_rply = null;
    protected $server_caps = null;
    protected $last_reply = '';

    public function connect($host, $port = null, $timeout = 30, $options = [])
    {
        if ($port === null) {
            $port = self::DEFAULT_PORT;
        }
        $errno = 0;
        $errstr = '';
        $socket_context = stream_context_create($options);
        $this->smtp_conn = @stream_socket_client(
            $host . ':' . $port,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $socket_context
        );
        if (!is_resource($this->smtp_conn)) {
            $this->error = ['error' => 'Failed to connect', 'errno' => $errno, 'errstr' => $errstr];
            return false;
        }
        stream_set_timeout($this->smtp_conn, $timeout);
        $this->last_reply = $this->get_lines();
        return true;
    }

    public function startTLS()
    {
        if (!$this->sendCommand('STARTTLS', 'STARTTLS', 220)) {
            return false;
        }
        $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        return stream_socket_enable_crypto($this->smtp_conn, true, $crypto_method);
    }

    public function authenticate($username, $password, $authtype = null, $OAuth = null)
    {
        if (!$this->sendCommand('AUTH LOGIN', 'AUTH LOGIN', 334)) {
            return false;
        }
        if (!$this->sendCommand('Username', base64_encode($username), 334)) {
            return false;
        }
        if (!$this->sendCommand('Password', base64_encode($password), 235)) {
            return false;
        }
        return true;
    }

    public function hello($host = '')
    {
        return $this->sendCommand('EHLO', 'EHLO ' . $host, 250);
    }

    public function mail($from)
    {
        return $this->sendCommand('MAIL FROM', 'MAIL FROM:<' . $from . '>', 250);
    }

    public function recipient($address, $dsn = '')
    {
        return $this->sendCommand('RCPT TO', 'RCPT TO:<' . $address . '>', [250, 251]);
    }

    public function data($msg_data)
    {
        if (!$this->sendCommand('DATA', 'DATA', 354)) {
            return false;
        }
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $msg_data));
        $field = '';
        $in_headers = true;
        foreach ($lines as $line) {
            if ($in_headers && $line === '') {
                $in_headers = false;
            }
            if (str_starts_with($line, '.')) {
                $line = '.' . $line;
            }
            $this->client_send($line . self::CRLF);
        }
        $this->client_send('.' . self::CRLF);
        return $this->parseResponse(250);
    }

    public function quit($close = true)
    {
        $this->sendCommand('QUIT', 'QUIT', 221);
        if ($close) {
            $this->close();
        }
        return true;
    }

    public function close()
    {
        if (is_resource($this->smtp_conn)) {
            fclose($this->smtp_conn);
            $this->smtp_conn = null;
        }
    }

    public function reset()
    {
        return $this->sendCommand('RSET', 'RSET', 250);
    }

    public function connected()
    {
        return is_resource($this->smtp_conn);
    }

    public function getError()
    {
        return $this->error;
    }

    public function getLastReply()
    {
        return $this->last_reply;
    }

    protected function sendCommand($command, $commandstring, $expect)
    {
        $this->client_send($commandstring . self::CRLF);
        $this->last_reply = $this->get_lines();
        return $this->parseResponse($expect);
    }

    protected function parseResponse($expected)
    {
        $code = (int) substr($this->last_reply, 0, 3);
        if (is_array($expected)) {
            return in_array($code, $expected);
        }
        return $code === $expected;
    }

    protected function client_send($data)
    {
        if (is_resource($this->smtp_conn)) {
            return fwrite($this->smtp_conn, $data);
        }
        return 0;
    }

    protected function get_lines()
    {
        if (!is_resource($this->smtp_conn)) {
            return '';
        }
        $data = '';
        $endtime = time() + $this->Timeout;
        stream_set_timeout($this->smtp_conn, $this->Timeout);
        while (is_resource($this->smtp_conn) && !feof($this->smtp_conn)) {
            $str = @fgets($this->smtp_conn, 515);
            $data .= $str;
            if (isset($str[3]) && $str[3] === ' ') {
                break;
            }
            if (time() > $endtime) {
                break;
            }
        }
        return $data;
    }
}

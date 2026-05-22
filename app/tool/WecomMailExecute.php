<?php

namespace app\tool;

use app\contract\ToolInterface;

class WecomMailExecute implements ToolInterface
{
    public function getName(): string
    {
        return 'wecom_mail_execute';
    }
    
    public function getDescription(): string
    {
        return '通过企业邮箱发送邮件，支持HTML格式';
    }
    
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'command' => [
                    'type' => 'string',
                    'description' => 'JSON格式的命令参数，包含to、subject、body等字段',
                ],
            ],
            'required' => ['command'],
        ];
    }
    
    public function execute(array $arguments): array
    {
        $config = \think\facade\Config::get('mcp_environments');
        $mailConfig = $config['wecom_mail'] ?? [];
        
        if (empty($mailConfig)) {
            throw new \Exception('未配置企业邮箱信息');
        }

        $commandStr = $arguments['command'] ?? '';
        if (empty($commandStr)) {
            throw new \Exception('command不能为空');
        }

        $command = json_decode($commandStr, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('command解析失败: ' . json_last_error_msg());
        }

        $to = $command['to'] ?? '';
        $subject = $command['subject'] ?? '';
        $body = $command['body'] ?? '';
        $cc = $command['cc'] ?? '';
        $bcc = $command['bcc'] ?? '';
        $isHtml = $command['is_html'] ?? true;

        if (empty($to)) {
            throw new \Exception('to不能为空');
        }

        if (empty($subject)) {
            throw new \Exception('subject不能为空');
        }

        if (empty($body)) {
            throw new \Exception('body不能为空');
        }

        $result = $this->sendSmtpMail($mailConfig, $to, $subject, $body, $cc, $bcc, $isHtml);

        return [
            'success' => true,
            'message' => '邮件发送成功',
            'result' => $result,
        ];
    }

    private function sendSmtpMail($config, $to, $subject, $body, $cc = '', $bcc = '', $isHtml = true)
    {
        $host = $config['host'];
        $port = $config['port'];
        $encryption = $config['encryption'];
        $username = $config['username'];
        $password = $config['password'];
        $fromAddress = $config['from_address'];
        $fromName = $config['from_name'];

        $socket = $this->connectSmtp($host, $port, $encryption);
        if (!$socket) {
            throw new \Exception('SMTP连接失败');
        }

        try {
            $this->smtpCommand($socket, 'EHLO ' . gethostname());
            
            if ($encryption === 'tls') {
                $this->smtpCommand($socket, 'STARTTLS');
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->smtpCommand($socket, 'EHLO ' . gethostname());
            }

            $this->smtpCommand($socket, 'AUTH LOGIN');
            $this->smtpCommand($socket, base64_encode($username));
            $this->smtpCommand($socket, base64_encode($password));

            $this->smtpCommand($socket, "MAIL FROM: <{$fromAddress}>");

            $toList = explode(',', $to);
            foreach ($toList as $toAddr) {
                $toAddr = trim($toAddr);
                if (!empty($toAddr)) {
                    $this->smtpCommand($socket, "RCPT TO: <{$toAddr}>");
                }
            }

            if (!empty($cc)) {
                $ccList = explode(',', $cc);
                foreach ($ccList as $ccAddr) {
                    $ccAddr = trim($ccAddr);
                    if (!empty($ccAddr)) {
                        $this->smtpCommand($socket, "RCPT TO: <{$ccAddr}>");
                    }
                }
            }

            if (!empty($bcc)) {
                $bccList = explode(',', $bcc);
                foreach ($bccList as $bccAddr) {
                    $bccAddr = trim($bccAddr);
                    if (!empty($bccAddr)) {
                        $this->smtpCommand($socket, "RCPT TO: <{$bccAddr}>");
                    }
                }
            }

            $headers = "Date: " . date('r') . "\r\n";
            $headers .= "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromAddress}>\r\n";
            $headers .= "To: {$to}\r\n";
            $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

            if (!empty($cc)) {
                $headers .= "Cc: {$cc}\r\n";
            }

            $this->smtpCommand($socket, 'DATA');
            $this->sendData($socket, $headers . "\r\n" . $body);
            $this->smtpCommand($socket, '.');

            $this->smtpCommand($socket, 'QUIT');

            fclose($socket);

            return [
                'to' => $to,
                'subject' => $subject,
                'sent_at' => date('Y-m-d H:i:s'),
            ];
        } catch (\Exception $e) {
            fclose($socket);
            throw $e;
        }
    }

    private function connectSmtp($host, $port, $encryption = 'ssl')
    {
        if ($encryption === 'ssl') {
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);
            $socket = stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
        } else {
            $socket = stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 30);
        }

        if (!$socket) {
            return false;
        }

        $response = fread($socket, 512);
        if (strpos($response, '220') === false) {
            fclose($socket);
            return false;
        }

        return $socket;
    }

    private function smtpCommand($socket, $command)
    {
        fwrite($socket, $command . "\r\n");
        $response = fread($socket, 512);

        $code = substr($response, 0, 3);
        if ($code != '250' && $code != '220' && $code != '235' && $code != '334' && $code != '354' && $code != '221') {
            throw new \Exception("SMTP命令失败: {$command} - {$response}");
        }

        return $response;
    }

    private function sendData($socket, $data)
    {
        $data = str_replace("\r\n.", "\r\n..", $data);
        fwrite($socket, $data . "\r\n");
    }
}
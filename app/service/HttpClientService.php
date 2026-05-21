<?php

namespace app\service;

class HttpClientService
{
    public static function request(string $method, string $url, array $data = [], array $options = []): array
    {
        $ch = curl_init();

        if ($method === 'GET' && !empty($data)) {
            $queryEncoding = $options['query_encoding'] ?? PHP_QUERY_RFC3986;
            $url .= '?' . http_build_query($data, '', '&', $queryEncoding);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        if (isset($options['timeout'])) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $options['timeout']);
        }

        $headers = $options['headers'] ?? [];

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (isset($options['content_type'])) {
                if ($options['content_type'] === 'json') {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                } elseif ($options['content_type'] === 'multipart') {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                } else {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                }
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        $logPath = runtime_path() . 'http_debug.log';
        $debugLog = "CURL调试: URL={$url}, Method={$method}, HttpCode={$httpCode}, Response={$response}\n";
        file_put_contents($logPath, $debugLog, FILE_APPEND);
        
        curl_close($ch);

        if ($error) {
            throw new \Exception("CURL请求失败: {$error}");
        }

        $result = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("JSON解析失败: " . json_last_error_msg());
        }

        return [
            'data' => $result,
            'http_code' => $httpCode,
        ];
    }
}

<?php

namespace app\service;

class TapdService
{
    private string $apiBaseUrl;
    private array $headers;
    private string $workspaceId;

    public function __construct()
    {
        $config = \think\facade\Config::get('mcp_environments.tapd');
        $this->apiBaseUrl = $config['api_base_url'] ?? 'https://api.tapd.cn';
        $this->workspaceId = $config['workspace_id'] ?? '';

        $accessToken = $config['access_token'] ?? '';
        if ($accessToken) {
            $this->headers = [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ];
        } else {
            $apiUser = $config['api_user'] ?? '';
            $apiPassword = $config['api_password'] ?? '';
            $this->headers = [
                'Authorization: Basic ' . base64_encode("{$apiUser}:{$apiPassword}"),
                'Content-Type: application/json',
            ];
        }
    }

    public function getBugCount(array $params = []): int
    {
        $params['workspace_id'] = $this->workspaceId;
        $result = $this->request('GET', 'bugs/count', $params);
        return $result['data']['count'] ?? 0;
    }

    public function getTaskCount(array $params = []): int
    {
        $params['workspace_id'] = $this->workspaceId;
        $result = $this->request('GET', 'tasks/count', $params);
        return $result['data']['count'] ?? 0;
    }

    public function getBugs(array $params = []): array
    {
        $params['workspace_id'] = $this->workspaceId;
        if (!isset($params['limit'])) {
            $params['limit'] = 200;
        }
        $result = $this->request('GET', 'bugs', $params);
        return $result['data'] ?? [];
    }

    public function getIterations(): array
    {
        $result = $this->request('GET', "iterations?workspace_id={$this->workspaceId}");
        return $result['data'] ?? [];
    }

    public function getWorkspaceId(): string
    {
        return $this->workspaceId;
    }

    private function request(string $method, string $endpoint, array $params = []): array
    {
        $url = "{$this->apiBaseUrl}/{$endpoint}";
        $separator = str_contains($url, '?') ? '&' : '?';
        $url .= "{$separator}s=script";

        $timeFields = ['created', 'modified', 'begin', 'due', 'completed', 'startdate', 'enddate'];
        foreach ($params as $k => $v) {
            if (in_array($k, $timeFields)) {
                $url .= "&{$k}=" . urlencode($v);
                unset($params[$k]);
            }
        }

        if ($method === 'GET' && !empty($params)) {
            foreach ($params as $k => $v) {
                $url .= "&{$k}=" . urlencode($v);
            }
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("TAPD API request failed: {$error}");
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("TAPD API response parse failed: " . json_last_error_msg());
        }

        return $result;
    }
}

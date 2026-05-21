<?php

namespace app\service;

use think\facade\Config;
use MongoDB\Client;

class McpMongoService
{
    protected $config;
    protected $environment;
    protected $client;

    public function __construct(string $environment = null)
    {
        $this->config = Config::get('mcp_environments');
        $this->environment = $environment ?: $this->config['default_environment'];
    }

    public function executeCommand(array $command): array
    {
        file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Starting executeCommand\n", FILE_APPEND);

        $envConfig = $this->getEnvironmentConfig();
        
        if (!$envConfig) {
            throw new \Exception('环境不存在: ' . $this->environment);
        }

        $this->checkCommandPermissions($command, $envConfig['read_only']);
        
        try {
            $startTime = microtime(true);
            
            $result = $this->executeMongoCommand($command);
            
            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);
            
            return [
                'success' => true,
                'environment' => $this->environment,
                'environment_name' => $envConfig['name'],
                'command' => $command,
                'execution_time_ms' => $executionTime,
                'read_only' => $envConfig['read_only'],
                'data' => $result,
            ];
            
        } catch (\Exception $e) {
            $errorMsg = 'MongoDB 命令执行失败: ' . $e->getMessage();
            file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Error: {$errorMsg}\n", FILE_APPEND);
            
            return [
                'success' => false,
                'environment' => $this->environment,
                'error' => [
                    'message' => $e->getMessage(),
                    'command' => $command,
                    'type' => get_class($e),
                ],
            ];
        }
    }

    protected function getEnvironmentConfig(): ?array
    {
        return $this->config['environments'][$this->environment] ?? null;
    }

    protected function getMongoClient(): Client
    {
        if (!$this->client) {
            $envConfig = $this->getEnvironmentConfig();
            $mongoConfig = $envConfig['mongodb'] ?? [];
            
            $hostname = $mongoConfig['hostname'] ?? 'localhost';
            $hostport = $mongoConfig['hostport'] ?? '27017';
            $username = $mongoConfig['username'] ?? '';
            $password = $mongoConfig['password'] ?? '';
            $database = $mongoConfig['database'] ?? 'test';
            $authSource = $mongoConfig['authSource'] ?? 'admin';
            
            $connectionString = "mongodb://";

            if (!empty($username) && !empty($password)) {
                $connectionString .= urlencode($username) . ':' . urlencode($password) . '@';
            }

            $connectionString .= $hostname . ':' . $hostport;
            $connectionString .= '/' . $database . '?authSource=' . $authSource;

            file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " MongoDB Connection String: {$connectionString}\n", FILE_APPEND);

            $this->client = new Client($connectionString);
        }
        
        return $this->client;
    }

    protected function executeMongoCommand(array $command): array
    {
        $client = $this->getMongoClient();
        $envConfig = $this->getEnvironmentConfig();
        $mongoConfig = $envConfig['mongodb'] ?? [];
        $databaseName = $mongoConfig['database'] ?? 'test';
        
        $database = $client->selectDatabase($databaseName);
        $result = $database->command($command)->toArray();

        $formattedResult = [];

        foreach ($result as $doc) {
            $docArray = $doc->getArrayCopy();

            if (!isset($docArray['ok']) || $docArray['ok'] == 1) {
                if (isset($docArray['cursor'])) {
                    if (isset($docArray['cursor']['firstBatch'])) {
                        $formattedResult = $docArray['cursor']['firstBatch'];
                    }
                } elseif (isset($docArray['result'])) {
                    $formattedResult = $docArray['result'];
                } elseif (isset($docArray['value'])) {
                    $formattedResult = $docArray['value'];
                } else {
                    $formattedResult = $result;
                }
            } else {
                throw new \Exception('MongoDB 命令执行失败: ' . json_encode($docArray));
            }
        }

        return $formattedResult;
    }

    protected function checkCommandPermissions(array $command, bool $readOnly): void
    {
        if (!$readOnly) {
            return;
        }

        $commandName = array_key_first($command);
        $commandName = strtoupper($commandName);
        
        $writeCommands = [
            'INSERT', 'UPDATE', 'DELETE', 'REPLACE',
            'CREATE', 'DROP', 'RENAME', 'ALTER',
            'CREATEINDEX', 'DROPINDEX', 'CREATEROLE', 'DROPROLE',
            'GRANTROLE', 'REVOKE', 'CREATEUSER', 'DROPUSER',
        ];
        
        if (in_array($commandName, $writeCommands)) {
            throw new \Exception("当前环境只读，不允许执行 {$commandName} 命令");
        }
    }

    public function getEnvironmentInfo(): array
    {
        return [
            'environment' => $this->environment,
            'config' => $this->getEnvironmentConfig(),
            'security' => [
                'enable_auth' => $this->config['security']['enable_auth'],
            ],
        ];
    }

    public function getAvailableEnvironments(): array
    {
        $environments = [];
        
        foreach ($this->config['environments'] as $key => $env) {
            $environments[] = [
                'key' => $key,
                'name' => $env['name'],
                'read_only' => $env['read_only'],
            ];
        }
        
        return $environments;
    }
}

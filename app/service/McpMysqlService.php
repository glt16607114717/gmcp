<?php

namespace app\service;

use think\facade\Db;
use think\facade\Config;

class McpMysqlService
{
    protected $config;
    protected $environment;
    
    public function __construct(string $environment = null)
    {
        $this->config = Config::get('mcp_environments');
        $this->environment = $environment ?: $this->config['default_environment'];
    }
    
    public function authenticate(string $username, string $password): bool
    {
        $prodConfig = $this->config['environments']['prod'];
        
        if (!$prodConfig) {
            throw new \Exception('正式环境配置不存在');
        }
        
        try {
            $db = Db::connect([
                'type' => 'mysql',
                'hostname' => $prodConfig['hostname'],
                'database' => $prodConfig['database'],
                'username' => $prodConfig['username'],
                'password' => $prodConfig['password'],
                'hostport' => $prodConfig['hostport'],
                'charset' => 'utf8mb4',
            ]);
            
            $userAuth = $db->table('r_user_auth')
                ->where('identity_type', 0)
                ->where('identifier', $username)
                ->where('is_delete', 0)
                ->find();
            
            if (!$userAuth) {
                file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Auth failed: User not found, username: {$username}\n", FILE_APPEND);
                return false;
            }
            
            $user = $db->table('r_user')->where('id', $userAuth['user_id'])->where('is_delete', 0)->where('status', 1)->find();
            
            if (!$user) {
                file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Auth failed: User disabled, username: {$username}\n", FILE_APPEND);
                return false;
            }
            
            $salt = $userAuth['salt'] ?? '';
            $inputPasswordHash = md5($password . $salt);
            $dbPasswordHash = $userAuth['credential'] ?? '';
            
            if ($inputPasswordHash === $dbPasswordHash) {
                file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Auth success: {$username}\n", FILE_APPEND);
                return true;
            }
            
            file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Auth failed: Password mismatch for {$username}\n", FILE_APPEND);
            return false;
            
        } catch (\Exception $e) {
            file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Auth error: " . $e->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }
    
    public function executeSql(string $sql, string $username, string $password): array
    {
        file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Starting executeSql, auth enabled: " . ($this->config['security']['enable_auth'] ? 'true' : 'false') . "\n", FILE_APPEND);
        
        if ($this->config['security']['enable_auth'] && !$this->authenticate($username, $password)) {
            throw new \Exception('鉴权失败: 用户名或密码错误');
        }
        
        $envConfig = $this->getEnvironmentConfig();
        
        if (!$envConfig) {
            throw new \Exception('环境不存在: ' . $this->environment);
        }
        
        $this->checkSqlPermissions($sql, $envConfig['read_only']);
        
        file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Getting connection\n", FILE_APPEND);
        
        try {
            $connection = $this->getConnection($username, $password);
            file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Connection established\n", FILE_APPEND);
        } catch (\Exception $e) {
            $errorMsg = "数据库连接失败: " . $e->getMessage();
            file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Error: {$errorMsg}\n", FILE_APPEND);
            throw new \Exception($errorMsg);
        }
        
        try {
            $startTime = microtime(true);
            
            $sqlType = $this->getSqlType($sql);
            
            if (in_array($sqlType, ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'])) {
                file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Executing query: {$sql}\n", FILE_APPEND);
                $result = $connection->query($sql);
                
                if (is_object($result) && method_exists($result, 'toArray')) {
                    $result = $result->toArray();
                }
                
                file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Query result count: " . count($result) . "\n", FILE_APPEND);
            } else {
                file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Executing command: {$sql}\n", FILE_APPEND);
                $affectedRows = $connection->execute($sql);
                $result = [
                    'affected_rows' => $affectedRows,
                    'message' => 'SQL 执行成功',
                ];
            }
            
            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);
            
            return [
                'success' => true,
                'environment' => $this->environment,
                'environment_name' => $envConfig['name'],
                'sql_type' => $sqlType,
                'execution_time_ms' => $executionTime,
                'read_only' => $envConfig['read_only'],
                'data' => $result,
            ];
            
        } catch (\Exception $e) {
            $errorMsg = 'SQL 执行失败: ' . $e->getMessage() . ', SQL: ' . $sql;
            file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Error: {$errorMsg}\n", FILE_APPEND);
            
            return [
                'success' => false,
                'environment' => $this->environment,
                'error' => [
                    'message' => $e->getMessage(),
                    'sql' => $sql,
                    'type' => get_class($e),
                ],
            ];
        }
    }
    
    protected function getEnvironmentConfig(): ?array
    {
        return $this->config['environments'][$this->environment] ?? null;
    }
    
    protected function getConnection(string $username, string $password)
    {
        $envConfig = $this->getEnvironmentConfig();
        
        return Db::connect([
            'type' => 'mysql',
            'hostname' => $envConfig['hostname'],
            'database' => $envConfig['database'],
            'username' => $envConfig['username'],
            'password' => $envConfig['password'],
            'hostport' => $envConfig['hostport'],
            'charset' => 'utf8mb4',
        ]);
    }
    
    protected function checkSqlPermissions(string $sql, bool $readOnly): void
    {
        $sqlType = $this->getSqlType($sql);
        
        $dangerousKeywords = $this->config['security']['dangerous_keywords'];
        
        foreach ($dangerousKeywords as $keyword) {
            if (stripos($sql, $keyword) !== false) {
                if ($readOnly) {
                    throw new \Exception("当前环境只读，不允许执行 {$keyword} 操作");
                }
            }
        }
    }
    
    protected function getSqlType(string $sql): string
    {
        $sql = trim(strtoupper($sql));
        $parts = preg_split('/\s+/', $sql, 2);
        
        return $parts[0] ?? 'UNKNOWN';
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

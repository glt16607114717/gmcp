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
    
    public function executeSql(string $sql, string $dbGroup = 'main'): array
    {
        file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Starting executeSql\n", FILE_APPEND);

        $envConfig = $this->getEnvironmentConfig();
        
        if (!$envConfig) {
            throw new \Exception('环境不存在: ' . $this->environment);
        }
        
        $this->checkSqlPermissions($sql, $envConfig['read_only']);
        
        file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Getting connection for group: {$dbGroup}\n", FILE_APPEND);
        
        try {
            $connection = $this->getConnection($dbGroup);
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
                'database_group' => $dbGroup,
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
                'database_group' => $dbGroup,
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
    
    protected function getConnection(string $dbGroup = 'main')
    {
        $envConfig = $this->getEnvironmentConfig();
        $mysqlConfig = $envConfig['mysql'] ?? [];
        
        if (!isset($mysqlConfig[$dbGroup])) {
            throw new \Exception("数据库分组不存在: {$dbGroup}，可用分组: " . implode(', ', array_keys($mysqlConfig)));
        }
        
        $dbConfig = $mysqlConfig[$dbGroup];

        return Db::connect([
            'type' => 'mysql',
            'hostname' => $dbConfig['hostname'] ?? '',
            'database' => $dbConfig['database'] ?? '',
            'username' => $dbConfig['username'] ?? '',
            'password' => $dbConfig['password'] ?? '',
            'hostport' => $dbConfig['hostport'] ?? '3306',
            'charset' => 'utf8mb4',
        ]);
    }
    
    protected function checkSqlPermissions(string $sql, bool $readOnly): void
    {
        $sqlType = $this->getSqlType($sql);
        
        $dangerousKeywords = $this->config['security']['dangerous_keywords'];
        
        foreach ($dangerousKeywords as $keyword) {
            if ($this->isDangerousKeywordUsed($sql, $keyword)) {
                if ($readOnly) {
                    throw new \Exception("当前环境只读，不允许执行 {$keyword} 操作");
                }
            }
        }
    }
    
    protected function isDangerousKeywordUsed(string $sql, string $keyword): bool
    {
        $sqlUpper = strtoupper($sql);
        $keywordUpper = strtoupper($keyword);
        
        $sqlType = $this->getSqlType($sql);
        
        if ($sqlType === $keywordUpper) {
            return true;
        }
        
        $position = stripos($sql, $keyword);
        if ($position === false) {
            return false;
        }
        
        $sqlLength = strlen($sql);
        $keywordLength = strlen($keyword);
        
        while ($position !== false) {
            if ($this->isKeywordAsCommand($sql, $position, $sqlLength, $keywordLength)) {
                return true;
            }
            $position = stripos($sql, $keyword, $position + 1);
        }
        
        return false;
    }
    
    protected function isKeywordAsCommand(string $sql, int $position, int $sqlLength, int $keywordLength): bool
    {
        $beforeChar = $position > 0 ? $sql[$position - 1] : ' ';
        $afterChar = $position + $keywordLength < $sqlLength ? $sql[$position + $keywordLength] : '';
        
        if ($beforeChar === '.' || $beforeChar === '_') {
            return false;
        }
        
        if ($afterChar === '_' || $afterChar === '.') {
            return false;
        }
        
        $validBeforeChars = [' ', '(', ';', "\n", "\r", "\t"];
        $validAfterChars = [' ', '(', "\n", "\r", "\t", ';', ')'];
        
        return in_array($beforeChar, $validBeforeChars) && in_array($afterChar, $validAfterChars);
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

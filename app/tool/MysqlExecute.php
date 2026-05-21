<?php

namespace app\tool;

use app\contract\ToolInterface;
use app\service\McpMysqlService;

class MysqlExecute implements ToolInterface
{
    public function getName(): string
    {
        return 'mysql_execute';
    }
    
    public function getDescription(): string
    {
        return '执行SQL查询，支持多环境';
    }
    
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'sql' => [
                    'type' => 'string',
                    'description' => '要执行的SQL语句',
                ],
                'environment' => [
                    'type' => 'string',
                    'enum' => ['dev', 'test', 'gray', 'prod'],
                    'default' => 'dev',
                    'description' => '数据库环境：dev(开发)、test(测试)、gray(灰度)、prod(正式)',
                ],
                'dbGroup' => [
                    'type' => 'string',
                    'enum' => ['main', 'bi'],
                    'default' => 'main',
                    'description' => '数据库分组：main(主业务库)、bi(BI分析库)',
                ],
            ],
            'required' => ['sql'],
        ];
    }
    
    public function execute(array $arguments): array
    {
        $sql = $arguments['sql'] ?? '';
        $environment = $arguments['environment'] ?? 'dev';
        $dbGroup = $arguments['dbGroup'] ?? 'main';

        if (empty($sql)) {
            throw new \Exception('SQL不能为空');
        }

        $config = \think\facade\Config::get('mcp_environments');
        $availableEnvironments = array_keys($config['environments']);
        $envNames = array_map(function($env) use ($config) {
            return "{$env}({$config['environments'][$env]['name']})";
        }, $availableEnvironments);

        if (!in_array($environment, $availableEnvironments)) {
            throw new \Exception("环境 '{$environment}' 不存在。可用环境: " . implode(', ', $envNames));
        }

        file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Starting SQL execution: {$sql}, env: {$environment}, dbGroup: {$dbGroup}\n", FILE_APPEND);

        $service = new McpMysqlService($environment);
        $result = $service->executeSql($sql, $dbGroup);

        file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " SQL execution completed\n", FILE_APPEND);

        return $result;
    }
}

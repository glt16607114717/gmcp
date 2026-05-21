<?php

namespace app\tool;

use app\contract\ToolInterface;
use app\service\McpMongoService;

class MongoExecute implements ToolInterface
{
    public function getName(): string
    {
        return 'mongo_execute';
    }
    
    public function getDescription(): string
    {
        return '执行MongoDB命令，支持多环境';
    }
    
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'command' => [
                    'type' => 'object',
                    'description' => '要执行的MongoDB命令对象，格式: {"commandName": {...参数}}，例如: {"find": "collection", "query": {"status": 1}}',
                ],
                'environment' => [
                    'type' => 'string',
                    'enum' => ['test', 'gray', 'prod'],
                    'default' => 'test',
                    'description' => '数据库环境：test(测试)、gray(灰度)、prod(正式)。MongoDB无开发环境',
                ],
            ],
            'required' => ['command'],
        ];
    }
    
    public function execute(array $arguments): array
    {
        $command = $arguments['command'] ?? [];
        $environment = $arguments['environment'] ?? 'dev';

        if (empty($command)) {
            throw new \Exception('MongoDB命令不能为空');
        }

        $config = \think\facade\Config::get('mcp_environments');
        $availableEnvironments = array_keys($config['environments']);
        $envNames = array_map(function($env) use ($config) {
            return "{$env}({$config['environments'][$env]['name']})";
        }, $availableEnvironments);

        if (!in_array($environment, $availableEnvironments)) {
            throw new \Exception("环境 '{$environment}' 不存在。可用环境: " . implode(', ', $envNames));
        }

        file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Starting MongoDB command execution: " . json_encode($command, JSON_UNESCAPED_UNICODE) . ", env: {$environment}\n", FILE_APPEND);

        $service = new McpMongoService($environment);
        $result = $service->executeCommand($command);

        file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " MongoDB command execution completed\n", FILE_APPEND);

        return $result;
    }
}

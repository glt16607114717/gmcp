<?php

namespace app\controller;

use app\manager\ToolManager;
use app\service\McpResponseService;
use think\Request;
use think\Response;

class McpController
{
    public function index(Request $request): Response
    {
        try {
            $content = $request->getContent();
            $accept = $request->header('Accept', '');
            
            file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Accept: {$accept}\n", FILE_APPEND);
            file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Request: " . $content . "\n", FILE_APPEND);
            
            if (empty($content)) {
                $emptyResponse = ['status' => 'ok', 'message' => 'NND-MCP Server is running'];
                $sseData = 'data: ' . json_encode($emptyResponse, JSON_UNESCAPED_UNICODE) . "\n\n";
                return response($sseData, 200, [
                    'Content-Type' => 'text/event-stream',
                    'Cache-Control' => 'no-cache',
                    'Connection' => 'keep-alive',
                ]);
            }
            
            $jsonrpcRequest = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return McpResponseService::error(null, -32700, 'Parse error', 'Invalid JSON');
            }
            
            if (!isset($jsonrpcRequest['jsonrpc']) || $jsonrpcRequest['jsonrpc'] !== '2.0') {
                return McpResponseService::error($jsonrpcRequest['id'] ?? null, -32600, 'Invalid Request', 'Invalid JSON-RPC version');
            }
            
            if (!isset($jsonrpcRequest['method'])) {
                return McpResponseService::error($jsonrpcRequest['id'] ?? null, -32600, 'Invalid Request', 'Missing method');
            }
            
            $method = $jsonrpcRequest['method'];
            $params = $jsonrpcRequest['params'] ?? [];
            $id = $jsonrpcRequest['id'] ?? null;
            
            $username = $request->header('X-MCP-Username', '');
            $password = $request->header('X-MCP-Password', '');
            
            file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Method: {$method}, ID: {$id}\n", FILE_APPEND);
            
            switch ($method) {
                case 'initialize':
                    return $this->handleInitialize($id, $params, $accept);
                
                case 'notifications/initialized':
                    return response('', 204);
                
                case 'tools/list':
                    return $this->handleToolsList($id, $accept);
                
                case 'tools/call':
                    return $this->handleToolsCall($id, $params, $username, $password, $accept);
                
                default:
                    return McpResponseService::error($id, -32601, 'Method not found', "Method '{$method}' not found");
            }
            
        } catch (\Exception $e) {
            file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Error: " . $e->getMessage() . "\n", FILE_APPEND);
            return McpResponseService::error(null, -32603, 'Internal error', $e->getMessage());
        }
    }
    
    private function handleInitialize($id, $params, $accept): Response
    {
        $supportedVersions = ['2025-11-25', '2025-06-18', '2025-03-26', '2024-11-05', '2024-10-07'];
        $clientVersion = $params['protocolVersion'] ?? '2024-11-05';
        $negotiatedVersion = in_array($clientVersion, $supportedVersions) ? $clientVersion : '2024-11-05';
        
        return McpResponseService::success($id, [
            'protocolVersion' => $negotiatedVersion,
            'capabilities' => [
                'tools' => [
                    'listChanged' => false,
                ],
            ],
            'serverInfo' => [
                'name' => 'nnd-mcp',
                'version' => '1.0.0',
            ],
        ], $accept);
    }
    
    private function handleToolsList($id, $accept): Response
    {
        $tools = ToolManager::getAllTools();
        $toolsArray = [];
        
        file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Found tools: " . count($tools) . "\n", FILE_APPEND);
        
        foreach ($tools as $tool) {
            $toolsArray[] = [
                'name' => $tool->getName(),
                'title' => $tool->getName(),
                'description' => $tool->getDescription(),
                'inputSchema' => $tool->getInputSchema(),
            ];
        }
        
        file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Tools array: " . json_encode($toolsArray, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        
        return McpResponseService::success($id, [
            'tools' => $toolsArray,
        ], $accept);
    }
    
    private function handleToolsCall($id, $params, $username, $password, $accept): Response
    {
        $toolName = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];

        $enableAuth = config('mcp_environments.security.enable_auth', false);

        if ($enableAuth) {
            if (empty($username) || empty($password)) {
                return McpResponseService::error($id, -32602, 'Invalid params', 'Missing auth credentials');
            }

            $authService = new \app\service\AuthService();
            if (!$authService->authenticate($username, $password)) {
                return McpResponseService::error($id, -32602, 'Invalid params', '鉴权失败: 用户名或密码错误');
            }
        }
        
        $tool = ToolManager::getTool($toolName);
        
        if (!$tool) {
            return McpResponseService::error($id, -32601, 'Method not found', "Tool '{$toolName}' not found");
        }
        
        set_time_limit(30);
        
        try {
            $result = $tool->execute($arguments);
            
            if (isset($result['success']) && $result['success'] === false) {
                return McpResponseService::success($id, [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "查询失败: " . ($result['error']['message'] ?? '未知错误') . "\n\n详细信息:\n" . json_encode($result['error'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                        ]
                    ],
                    'isError' => true,
                ], $accept);
            }
            
            return McpResponseService::success($id, [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode($result, JSON_UNESCAPED_UNICODE),
                    ]
                ],
            ], $accept);
        } catch (\Exception $e) {
            return McpResponseService::success($id, [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "执行失败: " . $e->getMessage(),
                    ]
                ],
                'isError' => true,
            ], $accept);
        }
    }
}

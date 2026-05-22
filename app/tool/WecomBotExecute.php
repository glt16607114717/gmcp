<?php

namespace app\tool;

use app\contract\ToolInterface;

class WecomBotExecute implements ToolInterface
{
    public function getName(): string
    {
        return 'wecom_bot_execute';
    }
    
    public function getDescription(): string
    {
        return '通过企业微信群机器人webhook发送消息，支持text/markdown/textcard格式';
    }
    
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'command' => [
                    'type' => 'string',
                    'description' => 'JSON格式的命令参数，包含content、msgtype等字段',
                ],
            ],
            'required' => ['command'],
        ];
    }
    
    public function execute(array $arguments): array
    {
        $config = \think\facade\Config::get('mcp_environments');
        $webhookUrl = $config['wecom_bot']['webhook_url'] ?? '';
        
        if (empty($webhookUrl)) {
            throw new \Exception('未配置企业微信机器人webhook地址');
        }

        $commandStr = $arguments['command'] ?? '';
        if (empty($commandStr)) {
            throw new \Exception('command不能为空');
        }

        $command = json_decode($commandStr, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('command解析失败: ' . json_last_error_msg());
        }

        $content = $command['content'] ?? '';
        $msgtype = $command['msgtype'] ?? 'markdown';

        if (empty($content)) {
            throw new \Exception('content不能为空');
        }

        if (strlen($content) > 4096) {
            throw new \Exception('消息内容不能超过4096字节');
        }

        $data = [
            'msgtype' => $msgtype,
        ];

        switch ($msgtype) {
            case 'text':
                $data['text'] = [
                    'content' => $content,
                ];
                break;
            
            case 'markdown':
                $data['markdown'] = [
                    'content' => $content,
                ];
                break;
            
            case 'textcard':
                $title = $command['title'] ?? '';
                $url = $command['url'] ?? '';
                
                if (empty($title)) {
                    throw new \Exception('textcard类型必须提供title参数');
                }
                
                $data['textcard'] = [
                    'title' => $title,
                    'description' => $content,
                    'url' => $url ?: '',
                ];
                break;
            
            default:
                throw new \Exception("不支持的消息类型: {$msgtype}");
        }

        $result = \app\service\HttpClientService::request('POST', $webhookUrl, $data, ['content_type' => 'json']);

        return [
            'success' => true,
            'message' => '消息发送成功',
            'result' => $result,
        ];
    }
}
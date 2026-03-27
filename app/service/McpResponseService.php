<?php

namespace app\service;

use think\Response;

class McpResponseService
{
    public static function success($id, $result): Response
    {
        $response = [
            'jsonrpc' => '2.0',
            'result' => $result,
        ];
        
        if ($id !== null) {
            $response['id'] = $id;
        }
        
        $sseData = 'data: ' . json_encode($response, JSON_UNESCAPED_UNICODE) . "\n\n";
            
        return response($sseData, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }
    
    public static function error($id, $code, $message, $data = null): Response
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];
        
        if ($data !== null) {
            $error['data'] = $data;
        }
        
        $response = [
            'jsonrpc' => '2.0',
            'error' => $error,
        ];
        
        if ($id !== null) {
            $response['id'] = $id;
        }
        
        $sseData = 'data: ' . json_encode($response, JSON_UNESCAPED_UNICODE) . "\n\n";
            
        return response($sseData, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }
}

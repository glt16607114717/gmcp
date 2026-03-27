<?php

namespace app\controller;

use think\Response;
use think\exception\HttpException;

class ExportController
{
    public function download()
    {
        $filename = input('get.file');
        
        if (empty($filename)) {
            throw new HttpException(400, '文件名不能为空');
        }
        
        if (!preg_match('/^[^_]+_[a-f0-9]{32}_\d+_[a-z0-9]{6}\.xlsx$/', $filename)) {
            throw new HttpException(400, '无效的文件名格式');
        }
        
        $filePath = runtime_path('exports' . DIRECTORY_SEPARATOR . date('Y-m') . DIRECTORY_SEPARATOR . $filename);
        $filePath = rtrim($filePath, '\\/');
        
        if (!file_exists($filePath)) {
            throw new HttpException(404, '文件不存在');
        }
        
        $safeFilename = $this->extractSafeFilename($filename);
        
        $fileContent = file_get_contents($filePath);
        
        return response($fileContent, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $safeFilename . '.xlsx"',
            'Content-Length' => filesize($filePath),
        ]);
    }
    
    private function extractSafeFilename(string $filename): string
    {
        $parts = explode('_', $filename);
        return $parts[0] ?? 'export';
    }
}

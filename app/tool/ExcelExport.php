<?php

namespace app\tool;

use app\contract\ToolInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelExport implements ToolInterface
{
    public function getName(): string
    {
        return 'excel_export';
    }
    
    public function getDescription(): string
    {
        return '导出Excel文件，支持自定义表头和数据';
    }
    
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'table_headers' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                    ],
                    'description' => '表头数组',
                ],
                'data' => [
                    'type' => 'array',
                    'description' => '数据数组，每个元素是一个对象，key与表头对应',
                ],
                'filename' => [
                    'type' => 'string',
                    'description' => '文件名（可选，不传则默认为"导出文件"）',
                ],
            ],
            'required' => ['table_headers', 'data'],
        ];
    }
    
    public function execute(array $arguments): array
    {
        $tableHeaders = $arguments['table_headers'] ?? [];
        $data = $arguments['data'] ?? [];
        $filename = $arguments['filename'] ?? '导出文件';
        $username = $arguments['username'] ?? 'unknown';
        
        if (empty($tableHeaders)) {
            throw new \Exception('表头不能为空');
        }
        
        if (empty($data)) {
            throw new \Exception('数据不能为空');
        }
        
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        $col = 'A';
        foreach ($tableHeaders as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }
        
        $row = 2;
        foreach ($data as $item) {
            $col = 'A';
            foreach ($tableHeaders as $header) {
                $value = is_array($item) ? ($item[$header] ?? '') : ($item->$header ?? '');
                $sheet->setCellValue($col . $row, $value);
                $col++;
            }
            $row++;
        }
        
        $monthDir = runtime_path('exports' . DIRECTORY_SEPARATOR . date('Y-m'));
        if (!is_dir($monthDir)) {
            mkdir($monthDir, 0777, true);
        }
        
        $userMd5 = md5($username);
        $timestamp = time();
        $random = $this->generateRandomString(6);
        
        $safeFilename = $this->sanitizeFilename($filename);
        $fullFilename = "{$safeFilename}_{$userMd5}_{$timestamp}_{$random}.xlsx";
        $filepath = $monthDir . DIRECTORY_SEPARATOR . $fullFilename;
        
        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);
        
        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $downloadUrl = "{$scheme}://{$host}/export/download?file={$fullFilename}";
        
        return [
            'success' => true,
            'filename' => $safeFilename . '.xlsx',
            'download_url' => $downloadUrl,
            'file_size' => filesize($filepath),
        ];
    }
    
    private function sanitizeFilename(string $filename): string
    {
        $filename = preg_replace('/[\\\\\/*?"<>|]/u', '', $filename);
        $filename = mb_substr($filename, 0, 50);
        return $filename ?: 'export';
    }
    
    private function generateRandomString(int $length = 6): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
    }
}

<?php

namespace app\manager;

use app\contract\ToolInterface;

class ToolManager
{
    public static function getTool(string $name): ?ToolInterface
    {
        $className = self::toolNameToClassName($name);
        $fullClassName = "app\\tool\\{$className}";
        
        if (!class_exists($fullClassName)) {
            return null;
        }
        
        return new $fullClassName();
    }
    
    public static function getAllTools(): array
    {
        $tools = [];
        $toolDir = app_path('tool');
        
        if (is_dir($toolDir)) {
            $files = scandir($toolDir);
            
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                    $className = pathinfo($file, PATHINFO_FILENAME);
                    
                    try {
                        $fullClassName = "app\\tool\\{$className}";
                        if (class_exists($fullClassName)) {
                            $tools[] = new $fullClassName();
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        }
        
        return $tools;
    }
    
    protected static function toolNameToClassName(string $toolName): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $toolName)));
    }
}

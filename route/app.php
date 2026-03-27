<?php
use think\facade\Route;

Route::group(function () {
    Route::any('/mcp', 'McpController/index');
    Route::get('/export/download', 'ExportController/download');
});

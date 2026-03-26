<?php
use think\facade\Route;

Route::group(function () {
    Route::any('/mcp', 'McpController/index');
});

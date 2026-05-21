<?php

namespace app\service;

use think\facade\Db;
use think\facade\Config;

class AuthService
{
    protected $config;

    public function __construct()
    {
        $this->config = Config::get('mcp_environments');
    }

    public function authenticate(string $username, string $password): bool
    {
        $prodConfig = $this->config['environments']['prod'];

        if (!$prodConfig) {
            throw new \Exception('正式环境配置不存在');
        }

        try {
            $mysqlConfig = $prodConfig['mysql'] ?? [];
            
            $dbConfig = isset($mysqlConfig['main']) ? $mysqlConfig['main'] : $mysqlConfig;

            $db = Db::connect([
                'type' => 'mysql',
                'hostname' => $dbConfig['hostname'] ?? '',
                'database' => $dbConfig['database'] ?? '',
                'username' => $dbConfig['username'] ?? '',
                'password' => $dbConfig['password'] ?? '',
                'hostport' => $dbConfig['hostport'] ?? '3306',
                'charset' => 'utf8mb4',
            ]);

            $userAuth = $db->table('r_user_auth')
                ->where('identity_type', 0)
                ->where('identifier', $username)
                ->where('is_delete', 0)
                ->find();

            if (!$userAuth) {
                file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Auth failed: User not found, username: {$username}\n", FILE_APPEND);
                return false;
            }

            $user = $db->table('r_user')->where('id', $userAuth['user_id'])->where('is_delete', 0)->where('status', 1)->find();

            if (!$user) {
                file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Auth failed: User disabled, username: {$username}\n", FILE_APPEND);
                return false;
            }

            $salt = $userAuth['salt'] ?? '';
            $inputPasswordHash = md5($password . $salt);
            $dbPasswordHash = $userAuth['credential'] ?? '';

            if ($inputPasswordHash === $dbPasswordHash) {
                file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Auth success: {$username}\n", FILE_APPEND);
                return true;
            }

            file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Auth failed: Password mismatch for {$username}\n", FILE_APPEND);
            return false;

        } catch (\Exception $e) {
            file_put_contents(__DIR__ . '/../../runtime/mcp_debug.log', date('Y-m-d H:i:s') . " Auth error: " . $e->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }
}

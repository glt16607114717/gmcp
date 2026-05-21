<?php

return [
    'environments' => [
        'prod' => [
            'name' => '正式环境',
            'read_only' => true,
            'mysql' => [
                'main' => [
                    'database' => 'nnd_robot',
                    'hostname' => 'gz-cynosdbmysql-grp-4xvs5cz5.sql.tencentcdb.com',
                    'hostport' => '28961',
                    'username' => 'robot_read',
                    'password' => 'b&G#C4_#|+xjMwwZ',
                ],
                'bi' => [
                    'database' => 'nnd_adm',
                    'hostname' => 'gz-cdb-morch28x.sql.tencentcdb.com',
                    'hostport' => '28833',
                    'username' => 'bi_read',
                    'password' => 'nnd@1234',
                ],
            ],
            'mongodb' => [
                'database' => 'robot_log',
                'hostname' => '1.14.225.133',
                'hostport' => '43010',
                'username' => 'robot_prod_read',
                'password' => 'aHv#m6u9E7KA88wK',
                'authSource' => 'admin',
            ],
            'ssh' => [
                'hostname' => 'develop.nndrobot.com',
                'port' => 22,
                'username' => 'root',
                'private_key' => '-----BEGIN OPENSSH PRIVATE KEY-----
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
-----END OPENSSH PRIVATE KEY-----',
            ],
        ],
        'test' => [
            'name' => '测试环境',
            'read_only' => true,
            'mysql' => [
                'main' => [
                    'database' => 'nnd_robot_test',
                    'hostname' => 'gz-cdb-morch28x.sql.tencentcdb.com',
                    'hostport' => '28833',
                    'username' => 'robot_test',
                    'password' => 'nnd@1234TEST',
                ],
                'bi' => [
                    'database' => 'nnd_adm_test',
                    'hostname' => 'gz-cdb-morch28x.sql.tencentcdb.com',
                    'hostport' => '28833',
                    'username' => 'bi_read',
                    'password' => 'nnd@1234',
                ],
            ],
            'mongodb' => [
                'database' => 'robot_log_test',
                'hostname' => '1.14.225.133',
                'hostport' => '43010',
                'username' => 'robot_test',
                'password' => 'DN77TpexfRnLwZwW',
                'authSource' => 'admin',
            ],
        ],
        'dev' => [
            'name' => '开发环境',
            'read_only' => false,
            'mysql' => [
                'main' => [
                    'database' => 'dev_nndrobot_20251027',
                    'hostname' => '172.16.90.62',
                    'hostport' => '3306',
                    'username' => 'root',
                    'password' => 'admin',
                ],
                'bi' => [
                    'database' => 'dev_nnd_adm',
                    'hostname' => '172.16.90.62',
                    'hostport' => '3306',
                    'username' => 'root',
                    'password' => 'admin',
                ],
            ],
            'mongodb' => [
                'database' => 'dev_nndrobot',
                'hostname' => '172.16.90.62',
                'hostport' => '27017',
                'username' => 'root',
                'password' => 'admin',
            ],
            'ssh' => [
                'hostname' => '',
                'port' => 22,
                'username' => '',
                'password' => '',
            ],
        ],
        'gray' => [
            'name' => '灰度环境',
            'read_only' => true,
            'mysql' => [
                'main' => [
                    'database' => 'nnd_robot_gray',
                    'hostname' => 'gz-cynosdbmysql-grp-irwd9b99.sql.tencentcdb.com',
                    'hostport' => '23022',
                    'username' => 'nnd_gray',
                    'password' => 'KOcjAr8NFl&!e$Sg',
                ],
                'bi' => [
                    'database' => 'nnd_adm_gray',
                    'hostname' => 'gz-cdb-morch28x.sql.tencentcdb.com',
                    'hostport' => '28833',
                    'username' => 'bi_read',
                    'password' => 'nnd@1234',
                ],
            ],
            'mongodb' => [
                'database' => 'robot_log_gray',
                'hostname' => '1.14.225.133',
                'hostport' => '43010',
                'username' => 'robot_gray',
                'password' => 'UQXP3KA7!4f#v6uf',
                'authSource' => 'admin',
            ],
        ],
    ],
    
    'default_environment' => 'dev',
    
    'security' => [
        'enable_auth' => true,
        'allow_sql_keywords' => [
            'SELECT', 'FROM', 'WHERE', 'ORDER BY', 'LIMIT', 'OFFSET',
            'LEFT JOIN', 'RIGHT JOIN', 'INNER JOIN', 'OUTER JOIN',
            'GROUP BY', 'HAVING', 'UNION', 'DISTINCT',
            'CASE', 'WHEN', 'THEN', 'ELSE', 'END',
        ],
        'dangerous_keywords' => [
            'DROP', 'DELETE', 'UPDATE', 'INSERT', 'ALTER', 
            'CREATE', 'TRUNCATE', 'GRANT', 'REVOKE',
        ],
    ],
];
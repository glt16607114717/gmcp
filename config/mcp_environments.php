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
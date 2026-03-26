<?php

return [
    'environments' => [
        'prod' => [
            'name' => '正式环境',
            'read_only' => true,
            'database' => 'nnd_robot',
            'hostname' => 'gz-cynosdbmysql-grp-4xvs5cz5.sql.tencentcdb.com',
            'hostport' => '28961',
            'username' => 'robot_read',
            'password' => 'b&G#C4_#|+xjMwwZ',
        ],
        'test' => [
            'name' => '测试环境',
            'read_only' => true,
            'database' => 'nnd_robot_test',
            'hostname' => 'gz-cdb-morch28x.sql.tencentcdb.com',
            'hostport' => '28833',
            'username' => 'robot_test',
            'password' => 'nnd@1234TEST',
        ],
        'dev' => [
            'name' => '开发环境',
            'read_only' => false,
            'database' => 'dev_nndrobot_20251027',
            'hostname' => '172.16.90.62',
            'hostport' => '3306',
            'username' => 'root',
            'password' => 'admin',
        ],
        'gray' => [
            'name' => '灰度环境',
            'read_only' => true,
            'database' => 'nnd_robot_gray',
            'hostname' => 'gz-cynosdbmysql-grp-irwd9b99.sql.tencentcdb.com',
            'hostport' => '23022',
            'username' => 'nnd_gray',
            'password' => 'KOcjAr8NFl&!e$Sg',
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
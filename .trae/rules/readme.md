---
alwaysApply: true
description: mcp 自研 MCP 框架，为 AI 助手提供数据库查询工具接口
---

# mcp 自研 MCP 框架

## 项目定位

公司自研的 MCP（Model Context Protocol）服务端，为 Trae IDE 等 AI 客户端提供统一的数据库查询工具接口。

## 技术栈

| 类别 | 技术 |
|------|------|
| 语言 | PHP 8.0+ |
| 框架 | ThinkPHP 8.0 |
| 数据库 | MySQL + MongoDB |

## 核心能力

- MCP 接口：`http://mcp.nndrobot.com/mcp`
- 多环境：dev（读写）/ test（只读）/ gray（只读）/ prod（只读）
- 安全机制：用户鉴权 + 危险 SQL 拦截（DELETE/DROP/ALTER）

## 目录结构

```
app/
├── controller/Index.php
└── tool/
    ├── MysqlExecute.php   ← MySQL 查询工具
    └── MongoExecute.php   ← MongoDB 查询工具
```

## 修改权限

允许修改。轻量级工具服务，持续迭代中。

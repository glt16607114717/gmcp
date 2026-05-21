# MCP 聚合平台

基于 ThinkPHP 8 的 MCP (Model Context Protocol) 聚合平台，为大语言模型提供统一的工具调用接口。

## 特性

- 多环境数据库支持：dev(开发)、test(测试)、gray(灰度)、prod(正式)
- 多数据库支持：MySQL 和 MongoDB
- 统一鉴权机制：基于正式环境用户表的安全验证
- 权限控制：环境级别的读写权限控制
- 错误友好提示：详细的错误信息和环境可用性提示
- 完整日志记录：所有操作和错误的详细日志
- 超时保护：30秒执行超时限制
- 策略模式：易于扩展的工具架构

## 安装

```bash
composer install
```

## 配置

### 环境配置

配置文件位于 `config/mcp_environments.php`，包含所有环境的数据库连接信息：

- **dev**: 开发环境，允许读写操作
- **test**: 测试环境，只读权限
- **gray**: 灰度环境，只读权限  
- **prod**: 正式环境，只读权限，同时用于用户鉴权

每个环境支持两种数据库：
- `mysql`: MySQL 数据库配置
- `mongodb`: MongoDB 数据库配置

### 安全配置

```php
'security' => [
    'enable_auth' => true,  // 是否启用鉴权
    'allow_sql_keywords' => [...],  // 允许的SQL关键字
    'dangerous_keywords' => [...],  // 危险SQL关键字
],
```

## Trae IDE MCP 配置

在 Trae IDE 中配置 MCP，使用以下 JSON 配置：

```json
{
  "mcpServers": {
    "nnd-mcp": {
      "url": "http://mcp.me/mcp",
      "headers": {
        "X-MCP-Username": "your_username",
        "X-MCP-Password": "your_password"
      }
    }
  }
}
```

**配置说明：**
- `url`: MCP 服务器地址
- `headers`: 请求头信息，包含用户名和密码用于鉴权
- `X-MCP-Username`: 用户名（对应 r_user_auth 表的 identifier 字段）
- `X-MCP-Password`: 密码（需要与 r_user_auth 表的 salt 拼接后 MD5 匹配）

## 当前功能

### MySQL 工具

#### mysql_execute

执行 MySQL 数据库查询，支持多环境安全连接。

**参数：**
- `sql` (required): 要执行的 SQL 语句
- `environment` (optional): 目标环境，可选值：dev, test, gray, prod，默认为 dev

**使用示例：**
```json
{
  "environment": "prod",
  "sql": "SELECT * FROM r_user WHERE id = 1"
}
```

**环境提示：**
- 当选择错误环境时，会提示所有可用环境：
  ```
  环境 'invalid' 不存在。可用环境: prod(正式环境), test(测试环境), dev(开发环境), gray(灰度环境)
  ```

**权限控制：**
- 开发环境：允许 SELECT、INSERT、UPDATE、DELETE 等操作
- 测试/灰度/正式环境：仅允许 SELECT 查询操作

### MongoDB 工具

#### mongo_execute

执行 MongoDB 命令，支持多环境安全连接。

**参数：**
- `command` (required): 要执行的 MongoDB 命令对象
- `environment` (optional): 目标环境，可选值：dev, test, gray, prod，默认为 dev

**使用示例：**

查询文档：
```json
{
  "environment": "prod",
  "command": {
    "find": "users",
    "query": {"status": 1},
    "limit": 10
  }
}
```

聚合查询：
```json
{
  "environment": "test",
  "command": {
    "aggregate": "orders",
    "pipeline": [
      {"$match": {"status": "completed"}},
      {"$group": {"_id": "$userId", "total": {"$sum": "$amount"}}}
    ]
  }
}
```

统计文档数量：
```json
{
  "environment": "dev",
  "command": {
    "count": "users",
    "query": {"active": true}
  }
}
```

**环境提示：**
- 当选择错误环境时，会提示所有可用环境：
  ```
  环境 'invalid' 不存在。可用环境: prod(正式环境), test(测试环境), dev(开发环境), gray(灰度环境)
  ```

**权限控制：**
- 开发环境：允许所有 MongoDB 操作
- 测试/灰度/正式环境：仅允许查询操作（find、count、aggregate 等）

## 开发

### 启动开发服务器

```bash
php think run
```

访问地址：http://localhost:8000

### MCP 端点

主要端点：`/mcp`

支持的协议：
- `initialize`: 初始化 MCP 连接
- `notifications/initialized`: 初始化完成通知
- `tools/list`: 获取可用工具列表
- `tools/call`: 调用工具

### 添加新工具

1. 在 `app/tool/` 目录创建工具类，实现 `ToolInterface` 接口
2. 工具类会自动被发现，无需手动注册
3. 工具类名与工具名对应（如 `MysqlExecute` 对应 `mysql_execute`）

**接口定义：**
```php
interface ToolInterface {
    public function getName(): string;
    public function getDescription(): string;
    public function getInputSchema(): array;
    public function execute(array $arguments): array;
}
```

## 日志

调试日志位于 `runtime/mcp_debug.log`，包含：
- 请求记录
- 鉴权状态
- SQL/MongoDB 执行信息
- 错误详情

## 技术栈

- **框架**: ThinkPHP 8.x
- **PHP**: 8.0+
- **数据库**: MySQL 5.7+, MongoDB 4.4+
- **协议**: MCP JSON-RPC 2.0
- **传输**: HTTP + SSE (Server-Sent Events)

## 项目结构

```
mcp/
├── app/
│   ├── contract/          # 接口定义
│   │   └── ToolInterface.php
│   ├── controller/        # 控制器
│   │   └── McpController.php
│   ├── service/          # 业务逻辑
│   │   ├── McpMysqlService.php
│   │   └── McpMongoService.php
│   ├── tool/            # 工具实现
│   │   ├── MysqlExecute.php
│   │   └── MongoExecute.php
│   └── manager/          # 工具管理
│       └── ToolManager.php
├── config/
│   ├── mcp_environments.php  # 环境配置
│   └── route.php
├── public/
│   └── index.php
└── runtime/
    └── mcp_debug.log
```

## 鉴权流程

1. 用户通过 HTTP 请求头提供用户名和密码
2. MCP 服务器连接正式环境 MySQL 数据库
3. 在 `r_user_auth` 表中查找用户认证信息
4. 验证：`md5(用户密码 + salt) == credential`
5. 验证通过后，连接到用户指定的目标环境执行命令

## 安全特性

- **SQL 注入防护**: 使用参数化查询
- **MongoDB 命令过滤**: 只读环境禁止写操作
- **权限分级**: 不同环境不同权限级别
- **鉴权保护**: 所有操作需通过正式环境用户验证
- **超时限制**: 30秒执行超时
- **关键字过滤**: 危险 SQL 关键字检测
- **详细日志**: 完整的操作审计追踪

## 许可证

遵循 ThinkPHP Apache2 开源协议
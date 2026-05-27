<?php

namespace app\tool;

use app\contract\ToolInterface;
use think\facade\Config;

class CnblogsExecute implements ToolInterface
{
    private $config;

    public function __construct()
    {
        $request = request();
        
        $this->config = [
            'blog_name' => $request->header('X-Cnblogs-BlogName') ?? '',
            'username' => $request->header('X-Cnblogs-Username') ?? '',
            'access_token' => $request->header('X-Cnblogs-AccessToken') ?? '',
            'endpoint' => $request->header('X-Cnblogs-Endpoint') ?? '',
        ];
        
        $logFile = runtime_path() . 'mcp_debug.log';
        file_put_contents($logFile, date('Y-m-d H:i:s') . " CnblogsExecute config: " . json_encode($this->config, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        
        if (empty($this->config['access_token'])) {
            throw new \Exception('博客园配置未正确设置，请在客户端 mcp.json 的 headers 中配置 X-Cnblogs-* 参数');
        }
    }

    public function getName(): string
    {
        return 'cnblogs_execute';
    }

    public function getDescription(): string
    {
        return '博客园文章管理工具，支持发布、编辑、删除、查询博客文章';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'enum' => ['info', 'categories', 'publish', 'edit', 'get', 'list', 'sync', 'delete'],
                    'description' => '操作类型：info(获取博客信息)、categories(获取分类)、publish(发布)、edit(编辑)、get(获取详情)、list(列表)、sync(同步)、delete(删除)',
                ],
                'post_id' => [
                    'type' => 'string',
                    'description' => '文章ID（edit、get、delete 操作必填）',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => '文章标题（publish、edit 操作必填）',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => '文章内容（publish、edit 操作必填）',
                ],
                'category' => [
                    'type' => 'string',
                    'description' => '随笔分类名称（如：AI、php、mysql）',
                ],
                'tags' => [
                    'type' => 'string',
                    'description' => '标签，逗号分隔',
                ],
                'draft' => [
                    'type' => 'boolean',
                    'description' => '是否保存为草稿（默认直接发布）',
                ],
                'publish_to_home' => [
                    'type' => 'boolean',
                    'description' => '是否发布至博客园首页',
                ],
                'count' => [
                    'type' => 'number',
                    'description' => '获取数量（list、sync 操作默认10）',
                ],
            ],
            'required' => ['action'],
        ];
    }

    public function execute(array $arguments): array
    {
        $action = $arguments['action'] ?? '';

        switch ($action) {
            case 'info':
                return $this->getBlogInfo($arguments['verify'] ?? false);
            case 'categories':
                return $this->getCategories();
            case 'publish':
                return $this->publishPost($arguments);
            case 'edit':
                return $this->editPost($arguments);
            case 'get':
                return $this->getPost($arguments);
            case 'list':
                return $this->getRecentPosts($arguments['count'] ?? 10);
            case 'sync':
                return $this->syncPosts($arguments['count'] ?? 50);
            case 'delete':
                return $this->deletePost($arguments);
            default:
                throw new \Exception("未知的操作类型: {$action}");
        }
    }

    private function callXmlRpc(string $method, array $params): mixed
    {
        $xml = $this->buildXmlRpcRequest($method, $params);
        
        $logFile = runtime_path() . 'mcp_debug.log';
        file_put_contents($logFile, date('Y-m-d H:i:s') . " callXmlRpc method={$method} xml_length=" . strlen($xml) . "\n", FILE_APPEND);
        
        $ch = curl_init($this->config['endpoint']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: text/xml',
            'User-Agent: NND-MCP-Cnblogs/1.0',
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        file_put_contents($logFile, date('Y-m-d H:i:s') . " callXmlRpc response raw: " . $response . "\n", FILE_APPEND);
        
        if ($error) {
            throw new \Exception("HTTP请求失败: {$error}");
        }
        
        if ($httpCode !== 200) {
            throw new \Exception("HTTP状态码错误: {$httpCode}");
        }
        
        return $this->parseXmlRpcResponse($response);
    }

    private function buildXmlRpcRequest(string $method, array $params): string
    {
        $paramsXml = '';
        foreach ($params as $param) {
            $paramsXml .= $this->buildXmlRpcValue($param);
        }
        
        return <<<XML
<?xml version="1.0"?>
<methodCall>
<methodName>{$method}</methodName>
<params>
{$paramsXml}
</params>
</methodCall>
XML;
    }

    private function buildXmlRpcValue($value): string
    {
        if (is_string($value)) {
            $escaped = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            return "<param><value><string>{$escaped}</string></value></param>\n";
        } elseif (is_int($value)) {
            return "<param><value><int>{$value}</int></value></param>\n";
        } elseif (is_bool($value)) {
            return "<param><value><boolean>" . ($value ? '1' : '0') . "</boolean></value></param>\n";
        } elseif (is_array($value)) {
            if ($this->isAssociativeArray($value)) {
                $members = '';
                foreach ($value as $key => $val) {
                    $members .= "<member><name>{$key}</name>" . 
                               substr($this->buildXmlRpcValue($val), 7, -9) . 
                               "</member>";
                }
                return "<param><value><struct>{$members}</struct></value></param>\n";
            } else {
                $values = '';
                foreach ($value as $val) {
                    $values .= substr($this->buildXmlRpcValue($val), 7, -9);
                }
                return "<param><value><array><data>{$values}</data></array></value></param>\n";
            }
        }
        
        return "<param><value><string>" . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . "</string></value></param>\n";
    }

    private function isAssociativeArray(array $arr): bool
    {
        if (empty($arr)) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    private function parseXmlRpcResponse(string $xml): mixed
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        
        $fault = $dom->getElementsByTagName('fault');
        if ($fault->length > 0) {
            $faultValue = $fault->item(0)->getElementsByTagName('value')->item(0);
            $faultStruct = $faultValue->getElementsByTagName('struct')->item(0);
            $faultCode = null;
            $faultString = '';
            
            foreach ($faultStruct->getElementsByTagName('member') as $member) {
                $name = $member->getElementsByTagName('name')->item(0)->nodeValue;
                $value = $member->getElementsByTagName('value')->item(0)->getElementsByTagName('string')->item(0);
                if ($value) {
                    if ($name === 'faultCode') {
                        $faultCode = $value->nodeValue;
                    } elseif ($name === 'faultString') {
                        $faultString = $value->nodeValue;
                    }
                }
            }
            
            throw new \Exception("API错误: {$faultString} (code: {$faultCode})");
        }
        
        $params = $dom->getElementsByTagName('params');
        if ($params->length === 0) {
            return [];
        }
        
        $param = $params->item(0)->getElementsByTagName('param')->item(0);
        if (!$param) {
            return [];
        }
        
        return $this->parseXmlRpcValue($param->getElementsByTagName('value')->item(0));
    }

    private function getDirectChildren(\DOMNode $node, string $tagName): array
    {
        $result = [];
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && $child->tagName === $tagName) {
                $result[] = $child;
            }
        }
        return $result;
    }

    private function getDirectChild(\DOMNode $node, string $tagName): ?\DOMNode
    {
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && $child->tagName === $tagName) {
                return $child;
            }
        }
        return null;
    }

    private function parseXmlRpcValue(\DOMNode $node)
    {
        $children = $node->childNodes;
        foreach ($children as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $tagName = $child->tagName;
                $nodeValue = $child->nodeValue;
                
                switch ($tagName) {
                    case 'string':
                    case 'dateTime.iso8601':
                        return $nodeValue;
                    case 'int':
                    case 'i4':
                        return (int)$nodeValue;
                    case 'boolean':
                        return $nodeValue === '1';
                    case 'array':
                        $data = $this->getDirectChild($child, 'data');
                        $result = [];
                        if ($data) {
                            foreach ($this->getDirectChildren($data, 'value') as $value) {
                                $result[] = $this->parseXmlRpcValue($value);
                            }
                        }
                        return $result;
                    case 'struct':
                        $result = [];
                        foreach ($this->getDirectChildren($child, 'member') as $member) {
                            $nameNode = $this->getDirectChild($member, 'name');
                            $valueNode = $this->getDirectChild($member, 'value');
                            if ($nameNode && $valueNode) {
                                $result[$nameNode->nodeValue] = $this->parseXmlRpcValue($valueNode);
                            }
                        }
                        return $result;
                    default:
                        return $nodeValue;
                }
            }
        }
        
        return null;
    }

    private function getBlogInfo(bool $verify = false): array
    {
        try {
            $result = $this->callXmlRpc('blogger.getUsersBlogs', [
                '',
                $this->config['username'],
                $this->config['access_token'],
            ]);
            
            if (is_array($result) && count($result) > 0) {
                $blogInfo = $result[0];
                $returnValue = [
                    'success' => true,
                    'blog_id' => $blogInfo['blogid'] ?? '',
                    'blog_name' => $blogInfo['blogName'] ?? '',
                    'blog_url' => $blogInfo['url'] ?? '',
                ];
                
                if ($verify) {
                    $returnValue['message'] = '配置验证通过，博客连接正常';
                }
                
                return $returnValue;
            }
            
            return ['success' => false, 'error' => '未获取到博客信息，请检查配置'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function getCategories(): array
    {
        try {
            $result = $this->callXmlRpc('metaWeblog.getCategories', [
                $this->config['blog_name'],
                $this->config['username'],
                $this->config['access_token'],
            ]);
            
            $categories = [];
            $prefix = '[随笔分类]';

            foreach ($result as $cat) {
                $title = $cat['title'] ?? '';
                if (strpos($title, $prefix) === 0) {
                    $categories[] = [
                        'full_name' => $title,
                        'name' => substr($title, strlen($prefix)),
                    ];
                }
            }

            return [
                'success' => true,
                'count' => count($categories),
                'categories' => $categories,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function buildCategoriesList(?string $category, ?string $tags, bool $publishToHome): array
    {
        $categories = ['[Markdown]'];
        
        if ($publishToHome) {
            $categories[] = '[发布至博客园首页]';
        }
        
        if ($category) {
            if (strpos($category, '[随笔分类]') === 0) {
                $categories[] = $category;
            } else {
                $categories[] = "[随笔分类]{$category}";
            }
        }
        
        if ($tags) {
            $tagArray = array_map('trim', explode(',', $tags));
            foreach ($tagArray as $tag) {
                if ($tag && !in_array($tag, $categories)) {
                    $categories[] = $tag;
                }
            }
        }
        
        return $categories;
    }

    private function publishPost(array $arguments): array
    {
        $title = $arguments['title'] ?? '';
        $content = $arguments['content'] ?? '';
        
        if (empty($title) || empty($content)) {
            return ['success' => false, 'error' => '文章标题和内容不能为空'];
        }

        try {
            $categories = $this->buildCategoriesList(
                $arguments['category'] ?? null,
                $arguments['tags'] ?? null,
                $arguments['publish_to_home'] ?? false
            );

            $postStruct = [
                'title' => $title,
                'description' => $content,
                'categories' => $categories,
            ];

            $postId = $this->callXmlRpc('metaWeblog.newPost', [
                $this->config['blog_name'],
                $this->config['username'],
                $this->config['access_token'],
                $postStruct,
                !($arguments['draft'] ?? false),
            ]);
            
            $logFile = runtime_path() . 'mcp_debug.log';
            file_put_contents($logFile, date('Y-m-d H:i:s') . " publishPost postId type=" . gettype($postId) . " value=" . json_encode($postId) . "\n", FILE_APPEND);
            
            $postId = (string) $postId;
            
            $this->saveLocalPost($postId, $title, $content);

            $tags = $arguments['tags'] ? array_map('trim', explode(',', $arguments['tags'])) : [];
            
            return [
                'success' => true,
                'post_id' => $postId,
                'title' => $title,
                'category' => $arguments['category'] ?? '未分类',
                'tags' => $tags,
                'status' => ($arguments['draft'] ?? false) ? '草稿' : '已发布',
                'url' => "https://www.cnblogs.com/{$this->config['blog_name']}/p/{$postId}.html",
                'local_file' => $this->getLocalPath($postId, $title),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function editPost(array $arguments): array
    {
        $postId = $arguments['post_id'] ?? '';
        
        if (empty($postId)) {
            return ['success' => false, 'error' => '文章ID不能为空'];
        }

        try {
            $postStruct = [];

            if (!empty($arguments['title'])) {
                $postStruct['title'] = $arguments['title'];
            }
            
            if (!empty($arguments['content'])) {
                $postStruct['description'] = $arguments['content'];
            }
            
            if (isset($arguments['category']) || isset($arguments['tags'])) {
                $postStruct['categories'] = $this->buildCategoriesList(
                    $arguments['category'] ?? null,
                    $arguments['tags'] ?? null,
                    $arguments['publish_to_home'] ?? false
                );
            }

            $result = $this->callXmlRpc('metaWeblog.editPost', [
                $postId,
                $this->config['username'],
                $this->config['access_token'],
                $postStruct,
                true,
            ]);
            
            if ($result && !empty($arguments['content'])) {
                $title = $arguments['title'] ?? $postId;
                $this->saveLocalPost($postId, $title, $arguments['content']);
            }

            return [
                'success' => (bool) $result,
                'post_id' => $postId,
                'title' => $arguments['title'] ?? '(未修改标题)',
                'status' => $result ? '已更新' : '更新失败',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function getPost(array $arguments): array
    {
        $postId = $arguments['post_id'] ?? '';
        
        if (empty($postId)) {
            return ['success' => false, 'error' => '文章ID不能为空'];
        }

        try {
            $post = $this->callXmlRpc('metaWeblog.getPost', [
                $postId,
                $this->config['username'],
                $this->config['access_token'],
            ]);
            
            $rawCategories = $post['categories'] ?? [];
            list($category, $tags) = $this->parseRawCategories($rawCategories);
            
            $title = $post['title'] ?? '';
            $description = $post['description'] ?? '';
            
            $this->saveLocalPost($postId, $title, $description);

            return [
                'success' => true,
                'post_id' => $post['postid'] ?? $postId,
                'title' => $title,
                'description' => $description,
                'category' => $category,
                'tags' => $tags,
                'date_created' => $post['dateCreated'] ?? '',
                'link' => $post['link'] ?? '',
                'local_file' => $this->getLocalPath($postId, $title),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function getRecentPosts(int $count = 10): array
    {
        try {
            $posts = $this->callXmlRpc('metaWeblog.getRecentPosts', [
                $this->config['blog_name'],
                $this->config['username'],
                $this->config['access_token'],
                $count,
            ]);
            
            $result = [];
            
            foreach ($posts as $post) {
                $rawCategories = $post['categories'] ?? [];
                list($category, $tags) = $this->parseRawCategories($rawCategories);
                
                $title = $post['title'] ?? '';
                $postId = $post['postid'] ?? '';
                
                $result[] = [
                    'post_id' => $postId,
                    'title' => $title,
                    'date_created' => $post['dateCreated'] ?? '',
                    'link' => $post['link'] ?? '',
                    'category' => $category,
                    'tags' => $tags,
                    'local_file' => $this->getLocalPath($postId, $title),
                ];
            }

            return [
                'success' => true,
                'count' => count($result),
                'posts' => $result,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function syncPosts(int $count = 50): array
    {
        try {
            $posts = $this->callXmlRpc('metaWeblog.getRecentPosts', [
                $this->config['blog_name'],
                $this->config['username'],
                $this->config['access_token'],
                $count,
            ]);
            
            $synced = [];
            
            foreach ($posts as $post) {
                $title = $post['title'] ?? '';
                $postId = $post['postid'] ?? '';
                $description = $post['description'] ?? '';
                
                $localPath = $this->getLocalPath($postId, $title);
                
                if (!file_exists($localPath)) {
                    $this->saveLocalPost($postId, $title, $description);
                    $synced[] = [
                        'post_id' => $postId,
                        'title' => $title,
                        'local_file' => $localPath,
                    ];
                }
            }

            return [
                'success' => true,
                'total' => count($posts),
                'synced' => count($synced),
                'already_exists' => count($posts) - count($synced),
                'articles' => $synced,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function deletePost(array $arguments): array
    {
        $postId = $arguments['post_id'] ?? '';
        
        if (empty($postId)) {
            return ['success' => false, 'error' => '文章ID不能为空'];
        }

        try {
            $result = $this->callXmlRpc('blogger.deletePost', [
                '',
                $postId,
                $this->config['username'],
                $this->config['access_token'],
                true,
            ]);

            return [
                'success' => (bool) $result,
                'post_id' => $postId,
                'status' => $result ? '已删除' : '删除失败',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function parseRawCategories(array $rawCategories): array
    {
        $category = '';
        $tags = [];
        $prefix = '[随笔分类]';
        $specialCategories = ['[Markdown]', '[发布至博客园首页]', '[发布为日记]', '[发布为文章]', '[发布为新闻]'];

        foreach ($rawCategories as $cat) {
            if (strpos($cat, $prefix) === 0) {
                $category = substr($cat, strlen($prefix));
            } elseif (!in_array($cat, $specialCategories)) {
                $tags[] = $cat;
            }
        }

        return [$category, $tags];
    }

    private function getLocalPath(string $postId, string $title): string
    {
        $sanitizedTitle = preg_replace('/[\\/:*?"<>|]/', '_', $title);
        $articlesDir = runtime_path() . 'cnblogs';
        
        if (!is_dir($articlesDir)) {
            mkdir($articlesDir, 0777, true);
        }
        
        return "{$articlesDir}/{$postId}-{$sanitizedTitle}.md";
    }

    private function saveLocalPost(string $postId, string $title, string $content): void
    {
        try {
            $localPath = $this->getLocalPath($postId, $title);
            file_put_contents($localPath, $content);
        } catch (\Exception $e) {
            $logFile = runtime_path() . 'mcp_debug.log';
            file_put_contents($logFile, date('Y-m-d H:i:s') . " saveLocalPost error: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }
}
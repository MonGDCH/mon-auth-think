# 更新日志

## v1.1.0（2026-11-07）

安全加固与标准加密升级版本。**包含多处行为变更，升级前请阅读文末的升级说明。**

### 安全修复

- **修复Signature签名校验完全失效的严重漏洞**：修复前签名不匹配时校验逻辑被注释且返回值语义反转，上层又忽略返回值，导致任意伪造签名的请求均可通过校验。现签名错误直接抛出`SIGN_VERIFY_FAIL`；
- **修复RBAC子角色越权提升漏洞**：修复前仅当角色同时修改父级时才校验越权，只修改权限规则时可添加父级没有的权限。现父级越权校验在`pid > 0`时无条件执行；
- **新增RBAC循环层级检测**：禁止将角色/规则移动到自身或自身后代之下，避免`pids`层级链形成环；
- **JWT密钥强制配置**：`key`为空或为占位符（如`%s`）时构造/初始化直接抛出`JWT_CONFIG_NOT_FOUND`，移除默认密钥兜底，防止弱密钥上线；
- **JwtMiddleware移除Cookie兜底**：新增标准`Authorization: Bearer`头支持（兼容`X-Authorization`），消除Cookie自动携带带来的CSRF风险。

### 新特性

- **签名nonce防重放**：新增`mon\auth\api\contract\NonceStore`契约与`mon\auth\api\nonce\RedisNonceStore`实现（`SET NX EX`原子占位）。配置`nonce_store`即启用，同一`(app_id, nonce)`在签名有效期内仅接受一次；默认关闭，行为与旧版完全兼容。校验顺序为验签→有效期→nonce占位，非法请求不消耗存储；
- **AccessToken标准加密升级**：新增AES-256-GCM认证加密格式（`v2.`前缀，随机IV，GCM tag自动校验完整性），默认签发新格式；旧版`Nbed64`格式token通过双读自动兼容，可配置`legacy_parse => false`关闭。彻底移除旧版"解密输出靠正则剥离控制字符"的补救逻辑；
- 新增配置项：`AccessTokenAuth`的`token_format`（默认`gcm`）、`legacy_parse`（默认`true`）；
- 新增错误码：`APIException::SIGN_REPLAY = 40230`。

### 修复与改进

- 修复`UpdateChildrenService::getTree()`使用`static`缓存导致常驻内存进程下跨请求脏数据、跨表数据错乱的问题，改为实例级缓存；
- 修复`SignatureAuth`校验链中timestamp字段缺失直接触发PHP notice的问题，缺失或非法值按过期拒绝；
- 修复`AccessToken`解析时token数据缺必要字段（app_id/expire/ip）触发notice的问题，明确抛出`ACCESS_TOKEN_ERROR`；
- `Auth::getConfig($key)`未命中时返回`null`而非触发notice（jwt/rbac/api三处统一）；
- `rbac\Auth::check()`中`trim(null)`加类型转换，消除PHP 8.1+ deprecation警告；
- RBAC事件名拼写修正：`faild` → `failed`；
- `DbSignatureCommand`默认表名与配置统一（`api_signs` → `api_sign`）。

### 测试

- 建立PHPUnit测试体系（`phpunit ^9.6`，兼容PHP 7.4）：`phpunit.xml` + `tests/`目录（jwt/api/rbac三个套件）；
- 覆盖84个用例、184项断言，包括全部安全修复的P0回归用例（签名篡改拒绝、token伪造拒绝、防重放、越权校验、循环层级检测等）。

### 升级说明

1. **JWT**：确认`AUTH_JWT_KEY`环境变量（或配置）已设置为真实随机密钥，否则服务启动即抛异常；
2. **Signature**：校验从形同虚设变为严格拒绝，如线上存在依赖旧漏洞发出的非法签名请求，升级后将被拒绝（属预期行为）；
3. **AccessToken**：默认行为变化仅一处——新签发token变为`v2.`/GCM格式。客户端将token作为不透明字符串透传（正常用法）则完全无感。存量token在`expire`时间内由双读兼容，确认过期后可关闭`legacy_parse`；
4. **RBAC**：如事件监听方监听了`faild`事件类型，需同步改为`failed`。

# 更新日志

## v1.3.1（2026-11-15）

安全与稳定性修复，**无破坏性变更（`APIException` 改名保留向后兼容）**，建议所有常驻进程（Workerman/Webman/Swoole）用户升级。

### 安全修复

- **修复JWT未鉴权TypeError**：`Token::getData()` 对payload为JSON标量（如`123`）的token，在**签名校验之前**返回非数组并触发`TypeError`，可被未鉴权请求利用造成持续500。现统一抛出`JWT_TOKEN_PAYLOAD_ERROR`；同时补充header的`typ`校验与`alg`缺失防护，防跨协议拼接；
- **修复JWT签发TypeError**：扩展数据包含非法UTF-8字符时`json_encode`返回false直接触发`TypeError`，现抛出`JWT_ENCODE_ERROR`；
- **修复签名时间窗单边校验**：原实现仅校验`time() > timestamp + expire`，**未来时间戳的签名永久有效**。现改为双向窗口`abs(time() - timestamp) > expire`；
- **移除内置默认盐，必须显式配置**：`AccessTokenAuth`/`SignatureAuth`及其驱动的默认salt改为空值，未配置盐初始化时直接抛出异常（驱动构造与`setSalt`同步拦截，双保险）；原公开默认盐常量与`BUILTIN_SALTS`黑名单一并移除，库中不再保留任何内置盐字面量，避免公开密钥导致Token/签名可被伪造；accesstoken配置模板内置的有效测试凭据（`TEST123456789`）同步移除，示例改为注释；
- **修复RBAC权限校验fail-open**：`Auth::check()`传入空规则数组且`relation=false`时，`array_diff([], [])`为空导致直接放行。现先规范化规则，空规则列表按无权限处理（fail-closed）；
- **超管标志与规则匹配改为严格比较**：消除PHP松散比较下`'*'`与数字规则ID的误判隐患。

### 修复与改进

- **修复校验器错误信息丢失**：`validate()`每次返回新实例，`Role/Rule/Access`中`$this->validate()->getError()`永远取到空，参数校验失败时上层拿不到任何原因。现复用同一实例；
- **修复RBAC pid环路拖死worker**：`UpdateChildrenService::getTree()`构建树前新增`TreeGuard::hasCycle()`检测。`pids`校验只能防止*新增*环路，历史脏数据一旦成环，`mon\util\Tree`的`getChildren()/getParents()`仍会无限递归，常驻进程worker直接内存耗尽崩溃或单核100%永久挂死（CLI下无执行时限兜底）；
- **修复RBAC修改接口并发竞态（TOCTOU）**：`Role::modify()`/`Rule::modify()`的校验数据在事务加锁前读取，并发修改下可能基于过期快照写入越权数据。现事务内加锁重读并复核，数据已变更则拒绝（提示重试）；`Role::modify()`的子树快照读取同步移入事务内；`UpdateChildrenService::updatePids()`读取父节点增加行锁，防止并发移动同一父级导致pids链错乱；
- **批量更新SQL值域白名单校验**：`UpdateChildrenService::batchUpdateSql()`废弃`addslashes`脆弱转义，字符串值仅允许数字与逗号组合（pids/rules实际值域），非法值直接拒绝（fail-closed）；
- **JWT时间校验完善**：`exp`等于当前时间即视为过期（原`<`边界多放行1秒）；新增`iat`签发时间校验（拒绝容差外的未来签发时间）；新增`leeway`时钟容差配置（默认0）；
- **Signature验签改用`hash_equals`恒时比较**，防止时序攻击；
- **Signature新增`alg`配置**：支持`hmac-sha256`（推荐），默认保持`md5`向后兼容；`alg`为`hmac-sha256`时自动兼容校验旧版md5签名（`accept_legacy`，默认开启），客户端全部升级后可关闭；
- `vendor:publish`不再覆盖已存在的密钥配置文件（避免重新生成密钥导致存量Token全部失效、滚动reload期间新旧worker密钥不一致出现随机401），已存在时跳过并提示；新配置写入改为临时文件+`rename`原子替换，避免并发publish产生半写内容或密钥交错覆盖；
- **中间件健壮性**：`RbacMiddleware`登录判断改为`isset`/严格比较（uid=0不再误判未登录，消除读取未定义动态属性的PHP 8.2+ deprecation）；`AccessTokenMiddleware`参数校验改为严格比较（`"0"`值不再被`empty`误判）；`JwtMiddleware`对payload缺少`aud`的Token fail-closed拒绝；`SignatureMiddleware`清理不可达分支并明确签名数据取自POST表单的约定；
- **错误码语义修正**：新增`ApiException::SIGN_NONCE_STORE_ERROR = 40240`，Redis等nonce存储故障不再误报为签名重放（`SIGN_REPLAY`）；
- `AccessTokenAuth::createToken()/getTokenData()`补充初始化检查，未初始化时抛出干净异常而非fatal error；
- `rbac\Auth::getAuthList()`复用同一请求内的规则ID查询结果，每次权限校验少一次访问表查询（常驻进程下DB压力减半）；
- 数据库命令：`DbRbacCommand`补齐`Db::setConfig`初始化（与其他db*命令一致）；三个命令的表名统一做反引号转义；`execute()`统一返回int退出码；
- `Install::getKey()`增加`string`返回类型声明。

### 其他

- 异常类统一命名：`APIException` → `ApiException`（文件名同步）。PHP类名不区分大小写，运行时旧类名引用（如`catch (APIException $e)`）无需改动即可解析；针对大小写敏感文件系统（Linux）下旧类名自动加载查找失败的场景，新增`src/compat.php`注册前置自动加载器（经composer `files`自动加载），旧代码零改动兼容；
- `composer.json`声明`version 1.3.1`。

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
- 新增错误码：`ApiException::SIGN_REPLAY = 40230`。

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

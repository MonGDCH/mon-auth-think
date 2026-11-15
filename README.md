# mon-auth-think

#### 介绍

基于think-orm的PHP权限管理类库，包含`Jwt`、`RBAC`、`AccessToken`、`Signature`等权限控制类库。

> 更新记录见 [CHANGELOG.md](CHANGELOG.md)。当前版本：`v1.3.2`（常驻进程状态隔离与安全加固，含行为变更，升级前请阅读[升级说明](#版本与升级说明)）。

#### 环境要求

- PHP >= 7.4（需要 openssl 扩展）

#### 安装使用

1. composer安装本项目

```bash
composer require mongdch/mon-auth-think
```

2. 如需使用RBAC库，则运行导入database目录下`rbac.sql`文件到数据库中。按需修改修改增加字段即可。

3. 如需使用Mysql版本的`AccessToken`库，则运行导入database目录下`access_token.sql`文件到数据库中。按需修改修改增加字段即可。

4. 如需使用Mysql版本的`Signature`库，则运行导入database目录下`signature.sql`文件到数据库中，按需修改配置即可

5. `Gaia`框架使用则执行再运行一下脚本

```bash
php gaia vendor:publish mon\auth
```

> `publish` 不会再覆盖已存在的配置文件（避免重新生成密钥导致存量 Token 失效、滚动 reload 期间新旧 worker 密钥不一致）。如需重新生成，请手动删除对应配置文件后再执行。

#### 单元测试

```bash
composer install
vendor/bin/phpunit
```

---

## 常驻进程（Workerman / Gaia / Swoole）注意事项

常驻进程模型下，进程内所有请求共享同一个 PHP 内存空间，`static` 属性、单例、事件监听器都会跨请求存活。本库对此做了如下约束，接入时请逐条确认：

### 1. 服务类初始化语义

| 方法 | 语义 | 使用场景 |
|---|---|---|
| `new Auth($config)` / `newInstance($config)` | 每次返回**独立实例** | 多租户、需要不同配置并存的场景（推荐） |
| `Auth::instance($config)` | 返回进程内单例；**带配置调用会以「默认配置 + 入参」重新初始化** | 全进程只有一套配置 |
| `Auth::init($config)` | 以**默认配置**为基准重建，不继承上一次的调用残留 | 启动阶段初始化 / 复位 |
| `Auth::setConfig($config)` | 在当前生效配置上**增量**合并 | 运行期只覆盖个别配置项（如注入 `nonce_store`） |

要点：

- `init()` 不会累积历史配置。旧版本是 `array_merge` 累积，导致“请求A设置的 `expire` 一直影响后续所有请求”，现已修正；
- `Auth::instance($config)` 不会静默丢弃 `$config`（旧版本 `mon\util\Instance` 会在首个实例创建后忽略后续参数，常驻进程内表现为“首个请求的盐/Dao 被固定”）；
- 子配置（`field`、`dao`、`dao.construct`）为**递归合并**，只传局部键不会再覆盖整组默认值；
- RBAC 的 `init()/setConfig()` 会同时丢弃已缓存的 Dao 实例（Dao 在构造时快照了表名），避免切换表名后仍读写旧表。

### 2. 事件监听器只能在启动阶段注册

`mon\util\Event` 是进程级单例，`listen()` 只追加不清理。若在控制器/中间件里注册监听器，每处理一个请求就会多一个闭包，回调被重复执行且闭包捕获的 `$request` 永不释放：

```php
// 正确：在 Worker 启动回调（如 Gaia 的插件 Bootstrap、onWorkerStart）中注册一次
Event::instance()->listen('access_check', function (array $data) {
    // 抛出 ApiException 表示校验不通过
});

// 错误：放在控制器/中间件内，常驻进程下监听器会持续累积
```

自查方式：`count(Event::instance()->get('access_check'))` 应恒为注册时的数量。

### 3. 数据库连接与事务

- 事务由 `mon\thinkORM` 的连接对象管理，请确保同一业务逻辑中的查询都落在同一连接；`Role/Rule` 的子树批量更新已支持按 Dao 的 `connection` 执行（`UpdateChildrenService` 构造函数第 5 个参数）；
- `Role::modify()` / `Rule::modify()` 在事务内使用 `SELECT ... FOR UPDATE` 复核数据，属于正常开销；并发修改同一批角色时请关注 `innodb_lock_wait_timeout`，避免单个 worker 长时间阻塞；
- 常驻进程下请勿长期开启 `trigger_sql` 而不注入日志对象，否则 SQL 日志会持续占用内存。

### 4. 其他

- 盐/密钥必须显式配置，空值与配置模板占位符 `%s` 都会被拒绝初始化（fail-closed）；
- `nonce_store`（Redis 防重放）为进程外共享状态，多 worker / 多机部署下依赖它才能有效防重放；
- 时间窗口校验（签名 `timestamp`、JWT `exp/nbf/iat`）依赖服务器时钟，多机部署请开启 NTP。

---

## JWT

`mon\auth\jwt\Auth` 服务封装了JWT签发与校验。**加密密钥`key`必须显式配置**，未配置将直接抛出异常，不提供默认密钥兜底。

> 自 v1.2.0 起 `Auth` 不再提供 `instance()`，请直接 `new Auth($config)`；常驻进程内建议在启动阶段构建一次并复用（Gaia 框架可使用 `support\auth\JwtService`）。

```php
use mon\auth\jwt\Auth;
use mon\auth\exception\JwtException;

$auth = new Auth([
    // 加密密钥，必填，请使用足够随机的长字符串
    'key'    => 'your-random-secret-key',
    // 签名算法，支持 HS256、HS384、HS512、RS256、RS384、RS512
    'alg'    => 'HS256',
    // 默认签发单位
    'iss'    => 'Gaia-Auth',
    // 默认签发主题
    'sub'    => 'User-Auth',
    // 默认有效期（秒）
    'exp'    => 7200,
    // 校验时间有效性的时钟偏移容差（秒）
    'leeway' => 0,
]);

// 签发：aud为签发对象（如用户ID），ext为扩展数据
$token = $auth->createToken(1, ['name' => 'mon'], 'sub标记', 'iss标记');

// 校验并获取数据（含签名、有效期、sub/iss一致性校验）
try {
    $data = $auth->getTokenData($token, 'sub标记', 'iss标记');
} catch (JwtException $e) {
    // 校验失败：签名不匹配、已过期、未生效等
}
```

低层驱动`Token`与`Payload`也可以独立使用（驱动无状态，`Token::instance()` 可直接使用）：

```php
use mon\auth\jwt\driver\Token;
use mon\auth\jwt\driver\Payload;
use mon\auth\exception\JwtException;

try {
    $key = 'your-random-secret-key';
    $alg = 'HS256';

    $token = new Token();
    $payload = (new Payload())->setIss('abc')->setSub('def')->setExt(['a' => '123'])->setExp(3600)->setAud('127.0.0.1');

    // 创建jwt
    $jwt = $token->create($payload, $key, $alg);

    // 解析并校验签名（返回payload数据）
    $data = $token->getData($jwt, $key, $alg);

    // 校验有效期（exp / nbf / iat / leeway）
    $token->verify($data);
} catch (JwtException $e) {
    // 校验失败
}
```

注意：`$nbf`参数为偏移秒数（生效时间为签发时间+nbf），不要传绝对时间戳。

## RBAC

基于角色-规则的权限校验，支持父子角色层级（子角色权限不允许超出父级范围，且不允许形成循环层级）。

> 同样自 v1.2.0 起不再提供 `instance()`，请直接 `new Auth($config)`；Gaia 框架可使用 `support\auth\RbacService::instance()`。

```php
use mon\auth\rbac\Auth;

$auth = new Auth([
    // 角色表
    'auth_role'         => 'auth_role',
    // 用户-角色关系表
    'auth_role_access'  => 'auth_access',
    // 权限规则表
    'auth_rule'         => 'auth_rule',
    // 超级管理员权限标志
    'admin_mark'        => '*',
    // 有效的状态值 / 无效的状态值
    'effective_status'  => 1,
    'invalid_status'    => 0,
]);

$check = $auth->check('/admin/sys/auth/group/add', 1);
```

`check($rule, $uid, $relation = true)`：

- `$rule` 支持字符串或数组，比较前统一 `trim + strtolower`；
- `$relation = true`（默认）表示满足任一规则即通过，`false` 表示必须全部具备；
- 规则列表为空按**无权限**处理（fail-closed），不再直接放行。

DAO层支持角色/规则的树形维护（自动维护`pids`层级链，并同步更新子级权限）：

- `getRoleDao()`：角色的增删改查、层级移动（含**越权校验**与**循环层级检测**）
- `getRuleDao()`：规则的增删改查、树形维护
- `getAccessDao()`：用户-角色关系的绑定与解绑、`getUserRole()`

```php
// 新增规则 → 返回规则ID
$ruleId = $auth->getRuleDao()->add([
    'pid'    => 0,
    'title'  => '系统管理',
    'rule'   => '/admin/sys',
    'remark' => '',
    'status' => 1,
]);

// 新增角色 → 返回角色ID
$roleId = $auth->getRoleDao()->add([
    'pid'    => 0,
    'title'  => '测试角色',
    'rules'  => [1, 2],
    'status' => 1,
]);

// 绑定用户
$auth->getAccessDao()->bind(['uid' => 10, 'gid' => $roleId]);
```

## AccessToken

接口访问令牌，基于应用（app_id/secret）签发。**默认签发AES-256-GCM认证加密格式**（`v2.`前缀），并双读兼容旧版`Nbed64`格式token。

```php
use mon\auth\api\AccessTokenAuth;
use mon\auth\api\dao\ArrayDao;
use mon\auth\exception\ApiException;

// 带配置调用会以「默认配置 + 入参」重新初始化；多套配置并存请用 newInstance()
$auth = AccessTokenAuth::instance([
    // 加密盐，必须显式配置（空值、占位符 '%s' 均会被拒绝）
    'salt'         => 'your-random-salt',
    // 有效期（秒）
    'expire'       => 7200,
    // 签发格式：gcm（默认，推荐）| nbed64（旧版）
    'token_format' => 'gcm',
    // 是否兼容解析旧版格式token
    'legacy_parse' => true,
    // 数据源
    'dao'          => [
        'driver'    => ArrayDao::class,
        'construct' => ['data' => [
            // ['app_id' => 'TEST123456789', 'secret' => 'xxx', 'status' => 1, 'expired_time' => ''],
        ]],
    ],
]);

// 签发（第二个参数为绑定IP，传空字符串表示不绑定IP）
$token = $auth->createToken('TEST123456789', '127.0.0.1', ['scope' => 'api']);

// 校验并获取token数据
try {
    $data = $auth->getTokenData($token, 'TEST123456789', '127.0.0.1');
} catch (ApiException $e) {
    // code: 40100 无效 / 40110 过期或IP不匹配 / 40120 AppID不匹配 / 40300 APPID不存在 ...
}

// 驱动级用法：直接传入秘钥
$token = $auth->getDriver()->create($appid, $secret, ['scope' => 'api'], '127.0.0.1', 7200);
$data = $auth->getDriver()->parse($token, $secret);
```

说明：

- 扩展数据为**平铺结构**合并进token数据，注意不要与保留字段（`app_id`/`expire`/`ip`及自定义字段映射名）重名；
- 扩展数据含非法UTF-8等无法 `json_encode` 的内容时抛出 `ACCESS_TOKEN_ENCODE_ERROR`（40130），不会再出现 `TypeError`；
- `app_id` 采用**严格比较**，避免 `'0e1'` 与 `'0e2'` 之类的数字字符串被松散比较判定相等；
- **IP 绑定语义**：签发时 `ip` 为空字符串表示不绑定IP（任意IP可用）；签发时传了IP，则校验必须与之严格一致。

#### Token格式与迁移

| 配置项 | 默认值 | 说明 |
|---|---|---|
| `token_format` | `gcm` | 新token格式：`gcm`=AES-256-GCM认证加密（推荐），`nbed64`=旧版自研加密 |
| `legacy_parse` | `true` | 是否兼容解析旧版格式token（迁移期双读） |

升级步骤：

1. 升级后无需任何配置变更：新签发token自动为`v2.`/GCM格式，存量旧token由双读自动兼容，直至自然过期；
2. 确认存量token全部过期后（最长等待`expire`秒数），设置`'legacy_parse' => false`关闭旧格式解析。

## Signature

接口签名校验，基于`app_id`+`secret`对请求参数签名，防篡改。签名错误、秘钥错误、参数篡改均直接抛出异常。

支持`hmac-sha256`（推荐，配置`'alg' => 'hmac-sha256'`）与`md5`（默认，向后兼容；校验时可通过`accept_legacy`兼容旧版md5签名）。强制双向时间窗校验（未来时间戳的签名同样被拒绝）。注意：

- 签名串必须以服务端算法为准：服务端对参数做`ksort + http_build_query`后拼接签名，客户端必须用相同方式生成；
- 数字键参数、嵌套数组、布尔值的序列化形态需与客户端一致，否则会出现验签不一致（与密钥无关）；
- 库不再内置默认盐，未配置盐（或残留占位符 `%s`）初始化直接抛出异常，请务必配置自定义盐。

```php
use mon\auth\api\SignatureAuth;
use mon\auth\api\dao\ArrayDao;
use mon\auth\exception\ApiException;

$auth = SignatureAuth::instance([
    'salt'   => 'your-random-salt',
    'alg'    => 'hmac-sha256',
    'expire' => 3600,
    'dao'    => [
        'driver'    => ArrayDao::class,
        'construct' => ['data' => [
            // ['app_id' => 'TEST123456789', 'secret' => 'xxx', 'status' => 1, 'expired_time' => ''],
        ]],
    ],
]);

$data = ['a' => 1, 'b' => 'asd', 'c' => true];

// 生成签名数据（服务级，自动查询应用秘钥）
$tokenData = $auth->createToken('TEST123456789', $data);

// 完整校验：验签 → 双向有效期 → 应用状态 → nonce防重放（配置了nonce_store时）
try {
    $check = $auth->checkToken($tokenData);
} catch (ApiException $e) {
    // code: 40210 签名错误 / 40220 已过期 / 40230 重复提交 / 40300 APPID不存在 ...
}

// 驱动级用法：直接传入秘钥
$tokenData = $auth->getDriver()->create($appid, $secret, $data);
$auth->getDriver()->check($secret, $tokenData);
```

#### 防重放（nonce）

签名数据中的`noncestr`每次请求随机生成。默认情况下服务端不记录nonce，截获的合法请求可在有效期窗口内被重放。通过配置`nonce_store`启用防重放：同一个`(app_id, nonce)`在签名有效期内只允许接受一次。

```php
use mon\auth\api\nonce\RedisNonceStore;

// 方式一：初始化时传入
$auth = SignatureAuth::instance([
    // ...原有配置
    'nonce_store' => new RedisNonceStore($redis), // $redis为\Redis或\Predis\Client实例
]);

// 方式二：运行期增量注入（不影响已加载的其它配置）
$auth->setConfig(['nonce_store' => new RedisNonceStore($redis)]);
```

说明：

- 存储TTL自动等于签名有效期`expire`，无需人工管理过期；
- 校验顺序为**验签 → 有效期 → nonce占位**，非法请求不会消耗存储；
- `RedisNonceStore`使用`SET NX EX`原子操作，多进程/多机并发安全；Redis不可用时抛出`SIGN_NONCE_STORE_ERROR`（fail-closed），底层异常通过 `getPrevious()` 保留，消息体不再拼接连接地址等内部信息；
- 自定义存储只需实现`NonceStore::put(string $app_id, string $nonce, int $ttl): bool`，返回`false`表示nonce已存在（重放）；
- 默认`nonce_store => null`不启用，行为与旧版完全兼容。

## Gaia框架中间件

执行`php gaia vendor:publish mon\auth`后，`support/middleware/`目录下提供四个中间件：

| 中间件 | 说明 |
|---|---|
| `JwtMiddleware` | 校验`X-Authorization`或`Authorization: Bearer`头中的JWT |
| `AccessTokenMiddleware` | 校验从POST的`app_id`/`access_token`取出的AccessToken（IP取自`$request->ip()`） |
| `SignatureMiddleware` | 校验接口签名（签名数据取自POST表单） |
| `RbacMiddleware` | 按请求path校验RBAC权限 |

---

## 错误码参考

### 通用（AuthException）

| 常量 | 值 | 说明 |
|---|---|---|
| AUTH_INIT_ERROR | 10000 | 服务未初始化 / 配置无效（含盐未配置、盐为占位符） |
| OPENSSL_ERROR | 10100 | openssl操作失败 |

### API（ApiException）

| 常量 | 值 | 说明 |
|---|---|---|
| ACCESS_TOKEN_ERROR | 40100 | 无效的AccessToken |
| ACCESS_TOKEN_INVALID | 40110 | AccessToken已过期或IP不匹配 |
| ACCESS_TOKEN_FAILD | 40120 | AccessToken应用不匹配 |
| ACCESS_TOKEN_ENCODE_ERROR | 40130 | AccessToken数据编码失败（如含非法UTF-8） |
| SIGN_NOT_FOUND | 40200 | 签名数据缺失 |
| SIGN_VERIFY_FAIL | 40210 | 签名校验失败 |
| SIGN_TIME_INVALID | 40220 | 签名已过期 |
| SIGN_REPLAY | 40230 | 签名重复提交（nonce重放） |
| SIGN_NONCE_STORE_ERROR | 40240 | nonce存储不可用（Redis异常等） |
| APPID_NOT_FOUND | 40300 | 应用不存在 |
| APPID_STATUS_ERROR | 40310 | 应用已停用 |
| APPID_TIME_INVALID | 40320 | 应用授权已过期 |
| APPID_PARAMS_FAILD | 40330 | 应用参数缺失 |

### JWT（JwtException）

| 常量 | 值 | 说明 |
|---|---|---|
| JWT_CONFIG_NOT_FOUND | 30010 | 配置缺失（如未配置key） |
| JWT_ALG_NOT_FOUND | 30100 | 未指定算法 |
| JWT_ALG_NOT_SUPPORT | 30110 | 不支持的算法 |
| JWT_TOKEN_FORMAT_ERROR | 30200 | token格式错误 |
| JWT_TOKEN_HEADER_ERROR | 30210 | token头错误 |
| JWT_TOKEN_PAYLOAD_ERROR | 30220 | token载荷错误 |
| JWT_TOKEN_SIGN_ERROR | 30230 | 签名生成失败 |
| JWT_TOKEN_VERIFY_ERROR | 30300 | 签名校验失败 |
| JWT_TOKEN_NBF_ERROR | 30310 | token未生效 |
| JWT_TOKEN_EXP_ERROR | 30320 | token已过期 |
| JWT_PAYLOAD_NOT_EMPTY | 30400 | 载荷数据为空 |
| JWT_PAYLOAD_ISS_ERROR | 30410 | iss不匹配 |
| JWT_PAYLOAD_SUB_ERROR | 30420 | sub不匹配 |

### RBAC（RbacException）

| 常量 | 值 | 说明 |
|---|---|---|
| RBAC_AUTH_INIT_ERROR | 20000 | 服务未初始化 |
| RBAC_MODEL_NOT_FOUND | 20100 | 数据模型不存在 |
| RBAC_RULE_NOT_SUPPORT | 20200 | 不支持的规则 |

---

## 版本与升级说明

### v1.3.2 升级注意

1. **配置合并语义变更**：`init($config)` 现在以默认配置为基准重建（不再累积历史配置）；需要增量覆盖请使用新增的 `setConfig($config)`。Gaia 的 `support\auth\*Service::register()` 已改为增量语义，行为基本无感知；
2. **`instance($config)` 不再丢弃配置**：带配置调用会重新初始化单例，多套配置并存请改用 `newInstance($config)`；
3. **占位符盐被拒绝**：`salt` 为 `''` 或 `'%s'` 时启动即抛 `AUTH_INIT_ERROR`。若曾手工复制 `vendor/mongdch/mon-auth-think/src/install/config/*.php` 模板，请确认盐已替换为真实随机值；
4. **AccessToken IP 绑定语义明确化**：签发时 `ip` 为空表示不绑定IP。此前这类 token 在传入真实请求IP时会被判定“Token授权IP无效”，导致签发方漏传IP时该应用全部请求失败；
5. **app_id 严格比较**：`AccessTokenAuth::getTokenData()` 与 `ArrayDao::getInfo()` 改为严格比较，`'0e1'`/`'0e2'` 一类的应用ID不再互相命中；
6. **新增错误码** `ACCESS_TOKEN_ENCODE_ERROR`（40130），签发时扩展数据编码失败抛出该异常；
7. RBAC 配置变更后会自动失效已缓存的 Dao（切换表名即时生效）；验证器按次创建，避免常驻进程内残留上次请求的校验错误信息；
8. `UpdateChildrenService` 支持传入连接配置（第5个参数），须与 Dao 的连接一致。

### 历史版本

- v1.3.1：JWT未鉴权场景 TypeError、签名单边时间窗、RBAC fail-open、内置默认盐移除、RBAC pid环路拖死worker等修复，详见 [CHANGELOG.md](CHANGELOG.md)；
- v1.1.0：Signature签名校验严格化、RBAC子角色越权修复、AES-256-GCM格式Token、nonce防重放。

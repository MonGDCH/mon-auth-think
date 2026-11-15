# mon-auth-think

#### 介绍

基于think-orm的PHP权限管理类库，包含`Jwt`、`RBAC`、`AccessToken`、`Signature`等权限控制类库。

> 更新记录见 [CHANGELOG.md](CHANGELOG.md)。当前版本：`v1.3.1`（安全与稳定性修复，无破坏性变更）。

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

#### 单元测试

```bash
composer install
vendor/bin/phpunit
```

测试覆盖JWT、AccessToken、Signature、RBAC核心安全路径（签名篡改拒绝、token伪造拒绝、防重放、越权校验等）。

---

## JWT

`mon\auth\jwt\Auth`服务封装了JWT签发与校验。**加密密钥`key`必须显式配置**，未配置或使用占位符（如`%s`）将直接抛出异常，不再提供默认密钥兜底。

```php

use mon\auth\jwt\Auth;
use mon\auth\exception\JwtException;

// 初始化（key必填）
Auth::instance()->init([
    // 加密密钥，必填，请使用足够随机的长字符串
    'key'   => 'your-random-secret-key',
    // 签名算法，支持 HS256、HS384、HS512、RS256、RS384、RS512
    'alg'   => 'HS256',
    // 默认有效期（秒），签发时间+exp
    'exp'   => 7200,
]);

// 签发：aud为签发对象（如用户ID），ext为扩展数据
$token = Auth::instance()->createToken(1, ['name' => 'mon'], 'sub标记', 'iss标记');

// 校验并获取数据（含签名、有效期、sub/iss一致性校验）
try {
    $data = Auth::instance()->getTokenData($token, 'sub标记', 'iss标记');
} catch (JwtException $e) {
    // 校验失败：签名不匹配、已过期、未生效等
}
```

低层驱动`Token`与`Payload`也可以独立使用：

```php

use mon\auth\jwt\driver\Token;
use mon\auth\jwt\driver\Payload;
use mon\auth\exception\JwtException;

try{
	// 加密密钥
	$key = 'aaaaaaa';
	// 加密算法
	$alg = 'HS256';
	$build = new Payload;
	$token = Token::instance();

	// 构建payload
	$payload = $build->setIss('abc')->setSub('def')->setExt(['a' => '123'])->setExp(3600)->setAud('127.0.0.1');
	// 创建jwt
	$jwt = $token->create($payload, $key, $alg);
	dd($jwt);

	// 验证jwt
	$data = $token->check($jwt, $key, $alg);
	dd($data);
}
catch (JwtException $e){
	dd('Msg: '.$e->getMessage(), 'Line: '.$e->getLine(), 'Code: '.$e->getCode());
}
```

注意：`$nbf`参数为偏移秒数（生效时间为签发时间+nbf），不要传绝对时间戳。

## RBAC

基于角色-规则的权限校验，支持父子角色层级（子角色权限不允许超出父级范围，且不允许形成循环层级）。

```php

use mon\auth\rbac\Auth;

$config = [
    // 权限开关
    'auth_on'           => true,
    // 用户组数据表名
    'auth_role'         => 'auth_role',
    // 用户-用户组关系表
    'auth_role_access'  => 'auth_access',
    // 权限规则表
    'auth_rule'         => 'auth_rule',
    // 超级管理员权限标志
    'admin_mark'        => '*',
];

Auth::instance()->init($config);
$check = Auth::instance()->check('/admin/sys/auth/group/add', 1);
debug($check);
```

DAO层支持角色/规则的树形维护（自动维护`pids`层级链，并同步更新子级权限）：

- `Role`：角色的增删改查、层级移动（含**越权校验**与**循环层级检测**）、绑定/解绑规则
- `Rule`：规则的增删改查、树形维护
- `Access`：用户-角色关系的绑定与解绑

## AccessToken

接口访问令牌，基于应用（app_id/secret）签发。**v1.1.0起默认签发AES-256-GCM认证加密格式**（`v2.`前缀），并双读兼容旧版`Nbed64`格式token。

```php

use mon\util\Event;
use mon\auth\api\AccessTokenAuth;
use mon\auth\exception\ApiException;

// 初始化（默认读取config('auth.access_token')配置）
AccessTokenAuth::instance()->init();

$appid = 'abcdefg';
$secret = 'asdas234';

// 自定义验证事件
Event::instance()->listen('access_check', function ($data) {
    // token数据
    // dd($data);

    // 抛出异常 ApiException 作为验证不通过的标志
    throw new ApiException('自定义验证错误', 0, null, $data);
});

// 签发token（服务级：自动查询应用秘钥）
$token = AccessTokenAuth::instance()->createToken($appid, '127.0.0.1', ['scope' => 'api']);

// 校验并获取token数据（服务级）
try {
    $decode = AccessTokenAuth::instance()->getTokenData($token, $appid, '127.0.0.1');
} catch (ApiException $e) {
    dd('验证不通过！' . $e->getMessage() . ' code: ' . $e->getCode());
}

// 驱动级用法：直接传入秘钥
$token = AccessTokenAuth::instance()->getDriver()->create($appid, $secret, ['scope' => 'api'], '127.0.0.1', 7200);
$data = AccessTokenAuth::instance()->getDriver()->parse($token, $secret);
```

说明：扩展数据为**平铺结构**合并进token数据，注意不要与保留字段（`app_id`/`expire`/`ip`及自定义字段映射名）重名。

#### Token格式与迁移

| 配置项 | 默认值 | 说明 |
|---|---|---|
| `token_format` | `gcm` | 新token格式：`gcm`=AES-256-GCM认证加密（推荐），`nbed64`=旧版自研加密 |
| `legacy_parse` | `true` | 是否兼容解析旧版格式token（迁移期双读） |

升级步骤：

1. 升级后无需任何配置变更：新签发token自动为`v2.`/GCM格式，存量旧token由双读自动兼容，直至自然过期；
2. 确认存量token全部过期后（最长等待`expire`秒数），设置`'legacy_parse' => false`关闭旧格式解析。

## Signature

接口签名校验，基于`app_id`+`secret`对请求参数签名，防篡改。**v1.1.0起签名校验严格化**：签名错误、秘钥错误、参数篡改均直接抛出异常。

**v1.3.1起支持`hmac-sha256`签名算法**（配置`'alg' => 'hmac-sha256'`，默认保持`md5`向后兼容，校验时自动兼容旧版md5签名），并强制双向时间窗校验（未来时间戳的签名同样被拒绝）。注意：

- 签名串必须以服务端算法为准：服务端对参数做`ksort + http_build_query`后拼接签名，客户端必须用相同方式生成；
- 数字键参数、嵌套数组、布尔值的序列化形态需与客户端一致，否则会出现验签不一致（与密钥无关）；
- 库不再内置默认盐，未配置盐初始化会直接抛出异常，请务必配置自定义盐。

```php

use mon\auth\api\SignatureAuth;

// 初始化（服务级，自动查询应用秘钥）
SignatureAuth::instance()->init();

$appid = 'TEST123456789';
$secret = 'asdas234';

$data = [
    'a' => 1,
    'b' => 'asd',
    'c' => true,
];

// 生成签名数据（服务级）
$tokenData = SignatureAuth::instance()->createToken($appid, $data);

// 完整校验（服务级：验签+有效期+应用校验）
try {
    $check = SignatureAuth::instance()->checkToken($tokenData);
} catch (\mon\auth\exception\ApiException $e) {
    dd('校验失败：' . $e->getMessage() . ' code: ' . $e->getCode());
}

// 驱动级用法：直接传入秘钥
$tokenData = SignatureAuth::instance()->getDriver()->create($appid, $secret, $data);
SignatureAuth::instance()->getDriver()->check($secret, $tokenData);
```

#### 防重放（nonce）

签名数据中的`noncestr`每次请求随机生成。默认情况下服务端不记录nonce，截获的合法请求可在有效期窗口内被重放。通过配置`nonce_store`启用防重放：同一个`(app_id, nonce)`在签名有效期内只允许接受一次。

```php

use mon\auth\api\nonce\RedisNonceStore;
use mon\auth\api\SignatureAuth;

SignatureAuth::instance()->init([
    // ...原有配置
    // 传入实现了 mon\auth\api\contract\NonceStore 契约的存储实例即启用
    'nonce_store' => new RedisNonceStore($redis),   // $redis为\Redis或\Predis\Client实例
]);
```

说明：

- 存储TTL自动等于签名有效期`expire`，无需人工管理过期；
- 校验顺序为**验签 → 有效期 → nonce占位**，非法请求不会消耗存储；
- `RedisNonceStore`使用`SET NX EX`原子操作，多进程/多机并发安全；Redis不可用时抛出异常（fail-close）；
- 自定义存储只需实现`NonceStore::put(string $app_id, string $nonce, int $ttl): bool`，返回`false`表示nonce已存在（重放）；
- 默认`nonce_store => null`不启用，行为与旧版完全兼容。

## Gaia框架中间件

执行`php gaia vendor:publish mon\auth`后，`support/middleware/`目录下提供四个中间件：

| 中间件 | 说明 |
|---|---|
| `JwtMiddleware` | 校验`X-Authorization`或`Authorization: Bearer`头中的JWT |
| `AccessTokenMiddleware` | 校验AccessToken |
| `SignatureMiddleware` | 校验接口签名 |
| `RbacMiddleware` | 按请求path校验RBAC权限 |

---

## 错误码参考

### 通用（AuthException）

| 常量 | 值 | 说明 |
|---|---|---|
| AUTH_INIT_ERROR | 10000 | 服务未初始化 |
| OPENSSL_ERROR | 10100 | openssl操作失败 |

### API（ApiException）

| 常量 | 值 | 说明 |
|---|---|---|
| ACCESS_TOKEN_ERROR | 40100 | 无效的AccessToken |
| ACCESS_TOKEN_INVALID | 40110 | AccessToken已过期或IP不匹配 |
| ACCESS_TOKEN_FAILD | 40120 | AccessToken应用不匹配 |
| SIGN_NOT_FOUND | 40200 | 签名数据缺失 |
| SIGN_VERIFY_FAIL | 40210 | 签名校验失败 |
| SIGN_TIME_INVALID | 40220 | 签名已过期 |
| SIGN_REPLAY | 40230 | 签名重复提交（nonce重放） |
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

## 安全版本说明（v1.1.0）

升级前请阅读[CHANGELOG](CHANGELOG.md)。重要的行为变更：

1. **JWT密钥强制配置**：`key`为空或占位符时抛出`JWT_CONFIG_NOT_FOUND`，请确认已配置真实密钥；
2. **Signature严格校验**：签名错误从"静默放行"变为抛出`SIGN_VERIFY_FAIL`，如有依赖旧行为的调用方需自查；
3. **RBAC越权防护强化**：子角色修改权限规则时无条件与父级比对，且禁止形成循环层级；
4. **AccessToken默认新格式**：默认签发AES-256-GCM格式token（见上方迁移指南）。

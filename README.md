# mon-auth-think

#### 介绍

基于think-orm的PHP权限管理类库，包含`Jwt`、`RBAC`、`AccessToken`、`Signature`等权限控制类库。


#### 安装使用

1. composer安装本项目

```bash
composer require mongdch/mon-auth-think
```

2. 如需使用RBAC库，则运行导入database目录下`rbac.sql`文件到数据库中。按需修改修改增加字段即可。

3. 如需使用Mysql版本的`AccessToken`、`Signature`，则运行导入database目录下`api.sql`文件到数据库中，按需修改配置即可

4. `Gaia`框架使用则执行再运行一下脚本

```bash
php gaia vendor:publish mon\auth
```

#### API文档

- 暂未编写，请通过查看examples目录下的demo，阅读了解更多使用方法。


#### Demo

1. JWT

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
	// $token = new Token;
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

```php

use mon\auth\jwt\Auth;

$token = Auth::instance()->create(1, ['pm' => 'tch']);

dd($token);

$data = Auth::instance()->check($token);
dd($data);

```

2. RBAC

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

3. AccessToken

```php

use mon\util\Event;
use mon\auth\api\AccessTokenAuth;
use mon\auth\exception\APIException;

// 初始化
AccessTokenAuth::instance()->init();

$appid = 'abcdefg';
$secret = 'asdas234';

// 自定义验证事件
Event::instance()->listen('access_check', function ($data) {
    // token数据
    // dd($data);

    // 抛出异常 APIException 作为验证不通过的标志
    throw new APIException('自定义验证错误', 0, null, $data);
});



$token = AccessTokenAuth::instance()->create($appid, $secret);

dd($token);

try {
    $decode = AccessTokenAuth::instance()->check($token, $appid, $secret);
    dd($decode);
} catch (APIException $e) {
    dd('验证不通过！' . $e->getMessage() . ' code: ' . $e->getCode());
    // 异常绑定的数据
    dd($e->getData());
}


```

4. apiSignature

```php

use mon\auth\api\SignatureAuth;

SignatureAuth::instance()->init();

$appid = 'TEST123456789';
$secret = 'asdas234';

$data = [
    'a' => 1,
    'b' => 'asd',
    'c' => true,
];

$tokenData = SignatureAuth::instance()->create($appid, $secret, $data);

dd($tokenData);


$check = SignatureAuth::instance()->check($secret, $tokenData);

dd($check);

```

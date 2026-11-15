<?php

/**
 * JWT 服务级用法
 *
 * 注意：mon\auth\jwt\Auth 自 v1.2.0 起不再提供 instance()，请直接 new Auth($config)。
 * 常驻进程内请只初始化一次并复用同一对象（或在 Service 层自行持有）。
 */

require __DIR__ . '/../vendor/autoload.php';

use mon\auth\jwt\Auth;
use mon\auth\exception\JwtException;

$auth = new Auth([
    // 加密密钥，必填
    'key'   => 'please-change-me-to-a-random-long-key',
    // 签名算法
    'alg'   => 'HS256',
    // 默认签发单位
    'iss'   => 'Gaia-Auth',
    // 默认签发主题
    'sub'   => 'User-Auth',
    // 默认有效期（秒）
    'exp'   => 7200,
    // 时钟偏移容差（秒）
    'leeway' => 0,
]);

try {
    // 签发
    $jwt = $auth->createToken(1, ['name' => 'mon']);
    var_dump($jwt);

    // 校验并获取数据
    $data = $auth->getTokenData($jwt);
    var_dump($data);
} catch (JwtException $e) {
    var_dump('Msg: ' . $e->getMessage(), 'Code: ' . $e->getCode());
}

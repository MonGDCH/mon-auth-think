<?php

/**
 * Signature 签名校验（ArrayDao 数据源，无需数据库）
 *
 * 注意：v1.3.1 起盐必须显式配置，且不接受配置模板占位符 '%s'。
 */

use mon\auth\api\SignatureAuth;
use mon\auth\api\dao\ArrayDao;
use mon\auth\exception\ApiException;

require_once __DIR__ . '/../vendor/autoload.php';

$config = [
    // 加密盐，必须显式配置（建议16位以上随机字符串）
    'salt'   => 'please-change-me-a-random-salt',
    // 签名算法：md5（默认，向后兼容）| hmac-sha256（推荐）
    'alg'    => 'hmac-sha256',
    // 字段映射
    'field'  => [
        'app_id'    => 'app_id',
        'signature' => 'signature',
        'timestamp' => 'timestamp',
        'noncestr'  => 'noncestr',
        'secret'    => 'key',
    ],
    // 有效时间（秒），校验为双向时间窗
    'expire' => 3600,
    // 数据源
    'dao'    => [
        'driver'    => ArrayDao::class,
        'construct' => [
            'data' => [
                [
                    'app_id'       => 'TEST123456789',
                    'secret'       => 'klasjhghaalskfjqwpetoijhxc',
                    'name'         => '测试',
                    'status'       => 1,
                    'expired_time' => '',
                ],
            ],
        ],
    ],
];

// 单例用法：带配置调用会以「默认配置 + 入参」重新初始化
$auth = SignatureAuth::instance($config);
// 需要每个请求使用独立实例时：$auth = SignatureAuth::newInstance($config);

$appid = 'TEST123456789';
$secret = 'klasjhghaalskfjqwpetoijhxc';

try {
    // 生成签名数据（服务级，自动查询应用秘钥）
    $tokenData = $auth->createToken($appid, ['a' => 1, 'b' => 'asd', 'c' => true]);
    var_dump($tokenData);

    // 完整校验：验签 + 双向有效期 + 应用状态 + nonce防重放（配置了nonce_store时）
    var_dump($auth->checkToken($tokenData));

    // 驱动级用法：直接传入秘钥
    $driverData = $auth->getDriver()->create($appid, $secret, ['x' => 1]);
    var_dump($auth->getDriver()->check($secret, $driverData));
} catch (ApiException $e) {
    var_dump('[error] ' . $e->getMessage() . ' code: ' . $e->getCode());
}

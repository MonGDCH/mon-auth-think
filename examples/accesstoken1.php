<?php

/**
 * AccessToken 签发与校验（ArrayDao 数据源，无需数据库）
 *
 * 注意：
 * 1. v1.3.1 起盐必须显式配置，且不接受配置模板占位符 '%s'；
 * 2. createToken($app_id, $ip, $extend)，$ip 传空字符串表示不绑定IP（任意IP可用），传了则严格校验。
 */

use mon\auth\api\AccessTokenAuth;
use mon\auth\api\dao\ArrayDao;
use mon\auth\exception\ApiException;

require_once __DIR__ . '/../vendor/autoload.php';

$config = [
    // 加密盐，必须显式配置（建议16位以上随机字符串）
    'salt'          => 'please-change-me-a-random-salt',
    // 有效期（秒）
    'expire'        => 7200,
    // 签发格式：gcm（默认，推荐）| nbed64（旧版）
    'token_format'  => 'gcm',
    // 兼容解析旧版格式token
    'legacy_parse'  => true,
    // 数据源
    'dao'           => [
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
$auth = AccessTokenAuth::instance($config);

try {
    $appid = 'TEST123456789';
    $ip = '127.0.0.1';

    // 签发（第二参数为绑定的IP，传空字符串表示不绑定）
    $token = $auth->createToken($appid, $ip, ['s1' => 'yadan', 's2' => 'xiawa']);
    var_dump($token);

    // 校验并获取token数据
    $data = $auth->getTokenData($token, $appid, $ip);
    var_dump($data);

    // 驱动级用法：直接传入秘钥（create($app_id, $secret, $extend, $ip, $exp)）
    $driverToken = $auth->getDriver()->create($appid, 'klasjhghaalskfjqwpetoijhxc', ['scope' => 'api'], $ip, 7200);
    var_dump($auth->getDriver()->parse($driverToken, 'klasjhghaalskfjqwpetoijhxc'));
} catch (ApiException $e) {
    var_dump('[error] ' . $e->getMessage() . ' code: ' . $e->getCode());
}

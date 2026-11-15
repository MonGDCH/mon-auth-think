<?php

/**
 * AccessToken 签发与校验（DatabaseDao 数据源，需要数据库）
 */

use mon\auth\api\AccessTokenAuth;
use mon\auth\exception\ApiException;
use mon\thinkORM\Db;

require_once __DIR__ . '/../vendor/autoload.php';

Db::setConfig([
    // 默认数据连接标识
    'default' => 'mysql',
    // 数据库连接信息
    'connections' => [
        'mysql' => [
            'type'            => 'mysql',
            'hostname'        => '127.0.0.1',
            'database'        => 'test',
            'username'        => 'root',
            'password'        => '123456',
            'hostport'        => '3306',
            'params'          => [\PDO::ATTR_TIMEOUT => 3],
            'charset'         => 'utf8mb4',
            'prefix'          => '',
            'deploy'          => 0,
            'rw_separate'     => false,
            'master_num'      => 1,
            'slave_no'        => '',
            'fields_strict'   => true,
            'auto_timestamp'  => false,
            'datetime_format' => false,
            'break_reconnect' => true,
            'fields_cache'    => false,
            // 常驻进程下开启SQL监听务必注入日志对象，否则会持续占用内存
            'trigger_sql'     => false,
            'query'           => \mon\thinkORM\extend\Query::class,
        ],
    ],
]);

$config = [
    // 加密盐，必须显式配置
    'salt'         => 'please-change-me-a-random-salt',
    // 有效期（秒）
    'expire'       => 7200,
    // 签发格式
    'token_format' => 'gcm',
    // 数据源：数据库驱动
    'dao'          => [
        'driver'    => \mon\auth\api\dao\DatabaseDao::class,
        'construct' => [
            'table' => 'api_sign',
        ],
    ],
];

$auth = AccessTokenAuth::newInstance($config);

try {
    $appid = 'TEST123456789';
    $ip = '127.0.0.1';

    // 签发（$ip 传空字符串表示不绑定IP）
    $token = $auth->createToken($appid, $ip, ['s1' => 'yadan']);
    var_dump($token);

    // 校验并获取token数据
    var_dump($auth->getTokenData($token, $appid, $ip));
} catch (ApiException $e) {
    var_dump('[error] ' . $e->getMessage() . ' code: ' . $e->getCode());
}

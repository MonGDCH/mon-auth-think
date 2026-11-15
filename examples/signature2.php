<?php

/**
 * Signature 签名校验（DatabaseDao 数据源，需要数据库）
 */

use mon\auth\api\SignatureAuth;
use mon\auth\api\nonce\RedisNonceStore;
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
    'salt'   => 'please-change-me-a-random-salt',
    // 签名算法
    'alg'    => 'hmac-sha256',
    // 有效期（秒）
    'expire' => 3600,
    // 数据源：数据库驱动
    'dao'    => [
        'driver'    => \mon\auth\api\dao\DatabaseDao::class,
        'construct' => [
            'table' => 'api_sign',
        ],
    ],
];

// 可选：启用nonce防重放（需注入Redis客户端实例）
// $config['nonce_store'] = new RedisNonceStore(new \Redis(), 'auth:nonce:');

$auth = SignatureAuth::newInstance($config);

try {
    $tokenData = $auth->createToken('TEST123456789', ['a' => 1, 'b' => 'asd']);
    var_dump($tokenData);

    var_dump($auth->checkToken($tokenData));
} catch (ApiException $e) {
    var_dump('[error] ' . $e->getMessage() . ' code: ' . $e->getCode());
}

<?php

use mon\auth\api\AccessTokenAuth;
use mon\auth\exception\APIException;

require_once __DIR__ . '/../vendor/autoload.php';

$config = [
    // 字段映射
    'field'     => [
        // app_id字段名
        'app_id'    => 'app_id',
        // 有效时间字段名
        'expire'    => 'expire',
        // 签发的IP
        'ip'        => 'ip',
    ],
    // 有效时间，单位秒
    'expire'    => 7200,
    // 默认加密盐
    'salt'      => 'a!khg#-$%iu_ow1.08',
    // 数据源配置
    'dao'      => [
        // 驱动，默认数组驱动
        'driver'    => \mon\auth\api\dao\ArrayDao::class,
        // 构造方法传入参数
        'construct'    => [
            // 数组驱动APP应用数据列表，驱动为 ArrayDao 时有效
            'data'  => [
                [
                    // 应用ID
                    'app_id'    => 'TEST123456789',
                    // 应用秘钥
                    'secret'    => 'klasjhghaalskfjqwpetoijhxc',
                    // 应用名称
                    'name'      => '测试',
                    // 应用状态，1有效 0无效
                    'status'    => 1,
                    // 应用过期时间戳
                    'expired_time'  => 0,
                ]
            ],
            // 数据库驱动操作表，驱动为 DatabaseDao 时有效
            'table'     => 'api_sign'
        ]
    ]
];


// 初始化
AccessTokenAuth::instance()->init($config);

try {
    $appid = 'TEST123456789';

    $token = AccessTokenAuth::instance()->createToken($appid, [
        's1'    => 'yadan',
        's2'    => 'xiawa'
    ]);
    dd($token);

    $data = AccessTokenAuth::instance()->checkToken($token, $appid);
    dd($data);
} catch (APIException $e) {
    dd('[error] ' . $e->getMessage() . ' code: ' . $e->getCode());
    // 异常绑定的数据
    dd($e->getData());
}

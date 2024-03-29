<?php

return [
    // 默认加密盐
    'salt'      => '%s',
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
    // 数据源配置
    'dao'      => [
        // 驱动，默认数组驱动
        'driver'    => \mon\auth\api\dao\ArrayDao::class,
        // 构造方法传入参数
        'construct'    => [
            // 数组驱动APP应用数据列表，driver驱动为 ArrayDao 时有效
            'data'  => [
                [
                    // 应用ID
                    'app_id'        => 'TEST123456789',
                    // 应用秘钥
                    'secret'        => 'klasjhghaalskfjqwpetoijhxc',
                    // 应用名称
                    'name'          => '测试',
                    // 应用状态，1有效 0无效
                    'status'        => 1,
                    // 应用过期时间戳
                    'expired_time'  => 0,
                ]
            ],
            // 数据库驱动操作表，driver驱动为 DatabaseDao 时有效
            'table'     => 'api_sign',
        ]
    ],
    // 中间件配置
    'middleware'    => [
        // 中间件回调处理
        'handler'       => \support\auth\middleware\handler\ErrorHandler::class,
        // 请求参数中AppID键名
        'appid_name'    => 'app_id',
        // 请求参数中Token键名
        'token_name'    => 'access_token',
        // Token数据在Request实例的属性名
        'access_token'  => 'access_token'
    ],
];

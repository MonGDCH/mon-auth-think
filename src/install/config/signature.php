<?php

return [
    // 默认加密盐
    'salt'  => '%s',
    // 字段映射
    'field' => [
        // app_id字段名
        'app_id'    => 'app_id',
        // 签名字段名
        'signature' => 'signature',
        // 签名时间字段名
        'timestamp' => 'timestamp',
        // 随机字符串字段名
        'noncestr'  => 'noncestr',
        // secret key名
        'secret'    => 'key'
    ],
    // 有效时间，单位秒
    'expire'    => 7200,
    // 数据源配置
    'dao'      => [
        // 驱动，默认数组驱动
        'driver'    => \mon\auth\api\dao\DatabaseDao::class,
        // 构造方法传入参数
        'construct' => [
            // 数组驱动APP应用数据列表，driver驱动为 ArrayDao 时有效
            'data'  => [],
            // 数据库驱动操作表，driver驱动为 DatabaseDao 时有效
            'table' => 'api_sign',
        ]
    ]
];

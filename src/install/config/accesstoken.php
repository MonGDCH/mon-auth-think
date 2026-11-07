<?php

return [
    // 默认加密盐
    'salt'      => env('AUTH_ACCESS_TOKEN_SALT', '%s'),
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
    // Token签发格式：gcm=AES-256-GCM认证加密（推荐），nbed64=旧版自研加密
    'token_format'  => 'gcm',
    // 兼容解析旧版格式token（迁移期双读，确认存量token全部过期后可关闭）
    'legacy_parse'  => true,
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
                    // 应用过期时间
                    'expired_time'  => '',
                ]
            ],
            // 数据库驱动操作表，driver驱动为 DatabaseDao 时有效
            'table'     => 'api_sign',
        ]
    ]
];

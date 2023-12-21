<?php

use mon\auth\api\AccessTokenAuth;
use mon\auth\exception\APIException;
use think\facade\Db;

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
        'driver'    => \mon\auth\api\dao\DatabaseDao::class,
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
AccessTokenAuth::instance()->init($config);

$dbConfig = [
    // 默认数据连接标识
    'default' => 'mysql',
    // 数据库连接信息
    'connections' => [
        'mysql' => [
            // 数据库类型
            'type'              => 'mysql',
            // 服务器地址
            'hostname'          => '127.0.0.1',
            // 数据库名
            'database'          => 'test',
            // 数据库用户名
            'username'          => 'root',
            // 数据库密码
            'password'          => '123456',
            // 数据库连接端口
            'hostport'          => '3306',
            // 数据库连接参数
            'params'            => [
                // 连接超时3秒
                \PDO::ATTR_TIMEOUT => 3,
            ],
            // 数据库编码默认采用utf8
            'charset'           => 'utf8mb4',
            // 数据库表前缀
            'prefix'            => '',
            // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
            'deploy'            => 0,
            // 数据库读写是否分离 主从式有效
            'rw_separate'       => false,
            // 读写分离后 主服务器数量
            'master_num'        => 1,
            // 指定从服务器序号
            'slave_no'          => '',
            // 检查字段是否存在
            'fields_strict'     => true,
            // 自动写入时间戳字段
            'auto_timestamp'    => false,
            // 不自动格式化时间戳
            'datetime_format'   => false,
            // 断线重连
            'break_reconnect'   => true,
            // 是否开启字段缓存
            'fields_cache'      => false,
            // 是否开启SQL监听，默认关闭，如需要开启，则需要调用 Db::setLog 注入日志记录对象，否则常驻进程长期运行会爆内存
            'trigger_sql'       => true,
        ],
    ],
];
Db::setConfig($dbConfig);



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

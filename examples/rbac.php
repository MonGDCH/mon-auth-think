<?php

use think\facade\Db;
use mon\auth\rbac\Auth;

require __DIR__ . '/../vendor/autoload.php';

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
            // 自定义查询类，支持Dao对象调用
            'query'             => \mon\thinkOrm\extend\Query::class,
        ],
    ],
];
Db::setConfig($dbConfig);


$auth = Auth::instance()->init();

// 新增规则
// $save = $auth->dao('rule')->add([
//     'title'     => 't',
//     'name'      => 't7',
//     'remark'    => 't3_remark',
//     'pid'       => 0
// ]);


// 修改规则
// $save = $auth->dao('rule')->modify([
//     'title'     => 'ttsx',
//     'name'      => '123asda',
//     'remark'    => '',
//     'pid'       => 0,
//     'status'    => 1,
//     'idx'       => 369
// ]);


// 新增组别
// $save = $auth->dao('group')->add([
//     'pid'   => 0,
//     'title' => 'testsss',
//     'rules' => [8, 6]
// ]);


// 更新组别
// $save = $auth->dao('group')->modify([
//     'idx'   => 3,
//     'pid'   => 0,
//     'title' => 'demo1',
//     'rules' => [8, 7, 9],
//     'status'=> 1,
// ]);

// 绑定用户组别
// $save = $access = $auth->dao('access')->bind([
//     'uid'   => 10,
//     'gid'   => 12,
// ]);

// 修改用户组别
// $save = $access = $auth->dao('access')->modify([
//     'uid'   => 2,
//     'gid'   => 1,
//     'new_gid'   => 2
// ]);

// 解除组别绑定
// $save = $access = $auth->dao('access')->unbind([
//     'uid'   => 10,
//     'gid'   => 12,
// ]);

// 获取用户所在组别
// $access = $auth->dao('access')->getUserGroup(2);



// 获取用户权限节点
// $data = Auth::instance()->getAuthIds(1);

// 获取用户权限列表
// $data = Auth::instance()->getAuthList(2);

// 获取用户权限
// $data = Auth::instance()->getRule(1);

// 校验单个权限
// $data = Auth::instance()->check('123asda', 2, true);
// 校验多个权限
// $check = Auth::instance()->check(['123asda', 'aa'], 1, false);

// var_dump($check);

// try {
//     dd($auth->check('/admin/sys/auth/group/add', 3));
// } catch (Throwable $e) {
//     dd($e->getMessage());
// }


dd($data);
// dd($save);

dd(Db::getDbLog());

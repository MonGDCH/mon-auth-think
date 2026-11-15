<?php

/**
 * RBAC 权限校验与角色/规则维护（需要数据库）
 *
 * 注意：mon\auth\rbac\Auth 自 v1.2.0 起不再提供 instance()，请直接 new Auth($config)；
 * 常驻进程内请在启动阶段构建一次并复用（Gaia 框架可直接使用 support\auth\RbacService）。
 */

use mon\auth\rbac\Auth;
use mon\thinkORM\Db;

require __DIR__ . '/../vendor/autoload.php';

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

$auth = new Auth([
    // 角色表
    'auth_role'         => 'auth_role',
    // 用户-角色关联表
    'auth_role_access'  => 'auth_access',
    // 权限规则表
    'auth_rule'         => 'auth_rule',
    // 超级管理员权限标志
    'admin_mark'        => '*',
]);

// ---------- 规则维护（Rule） ----------
// $ruleId = $auth->getRuleDao()->add([
//     'pid'    => 0,
//     'title'  => '系统管理',
//     'rule'   => '/admin/sys',
//     'remark' => '',
//     'status' => 1,
// ]);

// ---------- 角色维护（Role） ----------
// $roleId = $auth->getRoleDao()->add([
//     'pid'    => 0,
//     'title'  => '测试角色',
//     'rules'  => [1, 2],
//     'status' => 1,
// ]);
// $auth->getRoleDao()->modify([
//     'id'     => 1,
//     'pid'    => 0,
//     'title'  => '测试角色',
//     'rules'  => [1, 2, 3],
//     'status' => 1,
// ]);

// ---------- 用户-角色关联（Access） ----------
// $auth->getAccessDao()->bind(['uid' => 10, 'gid' => 1]);
// $auth->getAccessDao()->modify(['uid' => 10, 'gid' => 1, 'new_gid' => 2]);
// $auth->getAccessDao()->unbind(['uid' => 10, 'gid' => 2]);
// $auth->getAccessDao()->getUserRole(10);

// ---------- 权限校验 ----------
// 规则名与 auth_rule.rule 一致，比较时统一转小写
$check = $auth->check('/admin/sys/auth/group/add', 1);
var_dump($check);

// 批量校验：第三个参数 false 表示必须全部具备
var_dump($auth->check(['/admin/sys', '/admin/sys/auth/group/add'], 1, false));

// 获取用户的权限列表
var_dump($auth->getAuthList(1));

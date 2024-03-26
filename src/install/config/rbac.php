<?php
/*
|--------------------------------------------------------------------------
| RBAC权限控制配置文件
|--------------------------------------------------------------------------
| 定义RBAC权限控制配置信息
|
*/

return [
    // 用户组数据表名               
    'auth_group'        => 'auth_group',
    // 用户-用户组关系表     
    'auth_group_access' => 'auth_access',
    // 权限规则表    
    'auth_rule'         => 'auth_rule',
    // 超级管理员权限标志       
    'admin_mark'        => '*',
    // 有效的状态值
    'effective_status'  => 1,
    // 无效的状态值
    'invalid_status'    => 0,
    // 中间件配置
    'middleware'        => [
        // 中间件回调处理
        'handler'   => \support\auth\middleware\handler\ErrorHandler::class,
        // Request实例中用户ID的属性名
        'uid'       => 'uid',
        // 权限验证根路径
        'root_path' => ''
    ],
];

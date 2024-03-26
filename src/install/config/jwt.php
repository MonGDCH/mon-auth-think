<?php
/*
|--------------------------------------------------------------------------
| JWT权限控制配置文件
|--------------------------------------------------------------------------
| 定义JWT权限控制配置信息
|
*/

return [
    // 加密key
    'key'       => '%s',
    // 加密算法
    'alg'       => 'HS256',
    // 签发单位
    'iss'       => 'Gaia-Auth',
    // 签发主题
    'sub'       => 'User-Auth',
    // 生效时间，签发时间 + nbf
    'nbf'       => 0,
    // 有效时间，生效时间 + exp
    'exp'       => 3600,
    // 中间件配置
    'middleware'    => [
        // 中间件回调处理
        'handler'   => \support\auth\middleware\handler\ErrorHandler::class,
        // 请求头token名
        'header'    => 'Mon-Auth-Token',
        // cookie的token名
        'cookie'    => 'Mon-Auth-Token',
        // 用户ID(aud)在Request实例的属性名
        'uid'       => 'uid',
        // Token数据在Request实例的属性名
        'jwt'       => 'jwt'
    ],
];

<?php

declare(strict_types=1);

namespace support\auth;

use mon\env\Config;
use mon\util\Instance;
use mon\auth\jwt\Auth;

/**
 * JWT权限控制服务
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class JwtService
{
    use Instance;

    /**
     * 缓存服务对象
     *
     * @var Auth
     */
    protected $service;

    /**
     * 私有构造方法
     */
    protected function __construct()
    {
        $config = Config::instance()->get('auth.jwt', []);
        $this->service = new Auth($config);
    }

    /**
     * 获取权限服务
     *
     * @return Auth
     */
    public function getService(): Auth
    {
        return $this->service;
    }

    /**
     * 创建Token
     *
     * @param int|string $aud   面向的用户ID
     * @param array $ext        扩展的JWT内容
     * @param string $sub       签发主题
     * @param string $iss       签发单位
     * @param integer $exp      有效时间
     * @param integer $nbf      生效时间
     * @param mixed $jti        jwt编号
     * @throws \mon\auth\exception\JwtException
     * @return string
     */
    public function createToken($aud, array $ext = [], string $sub = '', string $iss = '', int $exp = 0, int $nbf = 0, $jti = null): string
    {
        return $this->getService()->createToken($aud, $ext, $sub, $iss, $exp, $nbf, $jti);
    }

    /**
     * 校验Token，获取用户信息
     *
     * @param string $token jwt数据
     * @param string $sub   签发主题
     * @param string $iss   签发单位
     * @throws \mon\auth\exception\JwtException
     * @return array
     */
    public function getTokenData(string $token, string $sub = '', string $iss = ''): array
    {
        return $this->getService()->getTokenData($token, $sub, $iss);
    }
}

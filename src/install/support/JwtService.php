<?php

declare(strict_types=1);

namespace support\auth;

use mon\env\Config;
use mon\util\Instance;
use mon\auth\jwt\Auth;
use mon\auth\exception\JwtException;

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
     * 服务对象列表
     *
     * @var Auth[]
     */
    protected $services;

    /**
     * 获取权限服务
     *
     * @param string $name 配置标识
     * @return Auth
     */
    public function getService(string $name = ''): Auth
    {
        $name = $name ?: Config::instance()->get('auth.jwt.default', '');
        if (!isset($this->services[$name])) {
            $config = Config::instance()->get('auth.jwt.configs.' . $name, []);
            if (!$config) {
                throw new JwtException('JWT配置标识不存在', JwtException::JWT_CONFIG_NOT_FOUND);
            }
            $this->services[$name] = new Auth($config);
        }

        return $this->services[$name];
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
     * @param string $jwt       配置标识
     * @throws \mon\auth\exception\JwtException
     * @return string
     */
    public function createToken($aud, array $ext = [], string $sub = '', string $iss = '', int $exp = 0, int $nbf = 0, $jti = null, string $jwt = ''): string
    {
        return $this->getService($jwt)->createToken($aud, $ext, $sub, $iss, $exp, $nbf, $jti);
    }

    /**
     * 校验Token，获取用户信息
     *
     * @param string $token jwt数据
     * @param string $sub   签发主题
     * @param string $iss   签发单位
     * @param string $jwt   配置标识
     * @throws \mon\auth\exception\JwtException
     * @return array
     */
    public function getTokenData(string $token, string $sub = '', string $iss = '', string $jwt = ''): array
    {
        return $this->getService($jwt)->getTokenData($token, $sub, $iss);
    }
}

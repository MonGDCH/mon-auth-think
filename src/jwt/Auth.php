<?php

declare(strict_types=1);

namespace mon\auth\jwt;

use mon\auth\jwt\driver\Token;
use mon\auth\jwt\driver\Payload;
use mon\auth\exception\JwtException;

/**
 * jwt权限控制
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class Auth
{
    /**
     * 配置信息
     *
     * @var array
     */
    protected $config = [
        // 加密key，必须显式配置，未配置时初始化将抛出异常
        'key'       => '',
        // 加密算法
        'alg'       => 'HS256',
        // 默认签发单位
        'iss'       => 'Gaia-Auth',
        // 默认签发主题
        'sub'       => 'User-Auth',
        // 默认生效时间，签发时间 + nbf
        'nbf'       => 0,
        // 默认有效时间，生效时间 + exp
        'exp'       => 3600,
        // 校验时间有效性的时钟偏移容差，单位秒
        'leeway'    => 0,
    ];

    /**
     * 构造方法
     *
     * @param array $config 配置信息
     */
    public function __construct(array $config = [])
    {
        $this->init($config);
    }

    /**
     * 初始化方法
     *
     * @param array $config 配置信息
     * @throws JwtException
     * @return Auth
     */
    public function init(array $config = []): Auth
    {
        if (!empty($config)) {
            $this->config = array_merge($this->config, $config);
        }

        // 校验加密key配置，避免空key或发布占位符被直接用于生产签名
        if (empty($this->config['key']) || $this->config['key'] === '%s') {
            throw new JwtException('JWT加密key未配置', JwtException::JWT_CONFIG_NOT_FOUND);
        }

        return $this;
    }

    /**
     * 创建JWT
     *
     * @param int|string $aud   面向的用户ID
     * @param array $ext        扩展的JWT内容
     * @param string $sub       签发主题
     * @param string $iss       签发单位
     * @param integer $exp      有效时间
     * @param integer $nbf      生效时间
     * @param mixed $jti        jwt编号
     * @throws JwtException
     * @return string
     */
    public function createToken($aud, array $ext = [], string $sub = '', string $iss = '', int $exp = 0, int $nbf = 0, $jti = null): string
    {
        $payload = new Payload();
        // 设置签发单位
        $iss = $iss ?: $this->getConfig('iss');
        $payload->setIss($iss);
        // 设置签发主题
        $sub = $sub ?: $this->getConfig('sub');
        $payload->setSub($sub);
        // 设置生效时间
        $nbf = $nbf > 0 ? $nbf : $this->getConfig('nbf');
        $payload->setNbf($nbf);
        // 设置有效时间
        $exp = $exp > 0 ? $exp : $this->getConfig('exp');
        $payload->setExp($exp);
        // 设置接收用户
        $payload->setAud($aud);
        // 设置扩展的数据
        $payload->setExt($ext);
        // 设置jwt标号
        if (!is_null($jti)) {
            $payload->setJti($jti);
        }

        return Token::instance()->create($payload, $this->getConfig('key'), $this->getConfig('alg'));
    }

    /**
     * 验证获取jwt数据
     *
     * @param string $jwt   jwt数据
     * @param string $sub   签发主题
     * @param string $iss   签发单位
     * @throws JwtException
     * @return array        解析后的payload
     */
    public function getTokenData(string $jwt, string $sub = '', string $iss = ''): array
    {
        // 获取jwt内容
        $payload = Token::instance()->getData($jwt, $this->getConfig('key'), $this->getConfig('alg'));
        // 验证签发主题
        $sub = $sub ?: $this->getConfig('sub');
        if ($sub && (!isset($payload['sub']) || $payload['sub'] != $sub)) {
            throw new JwtException('Token Sub 异常', JwtException::JWT_PAYLOAD_SUB_ERROR);
        }
        // 验证签发单位
        $iss = $iss ?: $this->getConfig('iss');
        if ($iss && (!isset($payload['iss']) || $payload['iss'] != $iss)) {
            throw new JwtException('Token Iss 异常', JwtException::JWT_PAYLOAD_ISS_ERROR);
        }
        // 校验时间有效性
        Token::instance()->verify($payload, (int) $this->getConfig('leeway'));

        return $payload;
    }

    /**
     * 获取配置信息
     *
     * @param string $key   配置索引
     * @return mixed
     */
    public function getConfig($key = '')
    {
        if (!empty($key)) {
            return $this->config[$key] ?? null;
        }

        return $this->config;
    }
}

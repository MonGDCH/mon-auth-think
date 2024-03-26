<?php

declare(strict_types=1);

namespace support\auth\middleware;

use Closure;
use mon\env\Config;
use mon\http\Response;
use support\auth\JwtService;
use InvalidArgumentException;
use mon\auth\ErrorHandlerInterface;
use mon\http\interfaces\RequestInterface;
use mon\http\interfaces\Middlewareinterface;

/**
 * JWT校验中间件
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class JwtMiddleware implements Middlewareinterface
{
    /**
     * 配置信息
     *
     * @var array
     */
    protected $config = [];

    /**
     * 回调处理
     *
     * @var mixed
     */
    protected $handler;

    /**
     * 构造方法
     */
    public function __construct()
    {
        $this->config = array_merge($this->config, Config::instance()->get('auth.jwt.middleware', []));
    }

    /**
     * 获取配置信息
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * 中间件实现接口
     *
     * @param RequestInterface $request  请求实例
     * @param Closure $callback 执行下一个中间件回调方法
     * @return Response
     */
    public function process(RequestInterface $request, Closure $callback): Response
    {
        // 中间件配置
        $config = $this->getConfig();
        // 获取Token，优先的请求头中获取，不存在则从cookie中获取
        $token = $this->getToken($request);
        if (!$token) {
            return $this->getHandler()->notFound();
        }

        // 验证Token
        $check = $this->getService()->check($token);
        // Token验证不通过
        if (!$check) {
            // 错误码
            $code = $this->getService()->getErrorCode();
            // 错误信息
            $msg = $this->getService()->getError();
            return $this->getHandler()->checkError($code, $msg);
        }

        // 获取Token数据
        $data = $this->getService()->getData();
        // 记录用户ID
        $uid = $config['uid'];
        $request->{$uid} = $data['aud'];
        // 记录Token数据
        $jwt = $config['jwt'];
        $request->{$jwt} = $data;

        return $callback($request);
    }

    /**
     * 获取Token
     *
     * @param RequestInterface $request
     * @return string
     */
    public function getToken(RequestInterface $request): string
    {
        if (!$this->config['header']) {
            throw new InvalidArgumentException('JWT header key is required');
        }
        $token = $request->header($this->config['header'], '');
        if (!$token && !empty($this->config['cookie'])) {
            $token = $request->cookie($this->config['cookie'], '');
        }

        return $token;
    }

    /**
     * 获取错误处理回调
     *
     * @return mixed
     */
    public function getHandler(): ErrorHandlerInterface
    {
        if (is_null($this->handler)) {
            $this->handler = new $this->config['handler']($this->config);
        }

        return $this->handler;
    }

    /**
     * 获取服务
     *
     * @return JwtService
     */
    public function getService(): JwtService
    {
        return JwtService::instance();
    }
}

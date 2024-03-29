<?php

declare(strict_types=1);

namespace support\auth\middleware;

use Closure;
use mon\env\Config;
use mon\http\Response;
use support\auth\SignatureService;
use mon\http\interfaces\RequestInterface;
use mon\http\interfaces\Middlewareinterface;

/**
 * signature权限校验中间件
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class SignatureMiddleware implements Middlewareinterface
{
    /**
     * 配置信息
     *
     * @var array
     */
    protected $config = [];

    /**
     * 构造方法
     */
    public function __construct()
    {
        $this->config = array_merge($this->config, Config::instance()->get('auth.signature.middleware', []));
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
        // 验证签名
        $check = $this->getService()->checkToken($request->post());
        if (!$check) {
            // 错误码
            $code = $this->getService()->getErrorCode();
            // 错误信息
            $msg = $this->getService()->getError();
            return $this->getHandler()->checkError($code, $msg);
        }

        return $callback($request);
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
     * @return SignatureService
     */
    public function getService(): SignatureService
    {
        return SignatureService::instance();
    }
}

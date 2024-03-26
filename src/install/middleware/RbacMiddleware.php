<?php

declare(strict_types=1);

namespace support\auth\middleware;

use Closure;
use mon\env\Config;
use mon\http\Response;
use support\auth\RbacService;
use mon\auth\ErrorHandlerInterface;
use mon\http\interfaces\RequestInterface;
use mon\http\interfaces\Middlewareinterface;

/**
 * RBAC权限校验中间件
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class RbacMiddleware implements Middlewareinterface
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
        $this->config = array_merge($this->config, Config::instance()->get('auth.rbac.middleware', []));
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
        // 用户ID键名
        $uid = $config['uid'];
        // 验证登录
        if (!$request->{$uid}) {
            // 不存在用户ID，未登录
            return $this->getHandler()->notFound();
        }

        // 验证权限
        $check = $this->getService()->check($this->getPath($request), $request->{$uid});
        // 权限验证不通过
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
     * 获取验证路径
     *
     * @param RequestInterface $request
     * @return string
     */
    public function getPath(RequestInterface $request): string
    {
        $path = $request->path();

        $root = $this->getConfig()['root_path'];
        if (!empty($root)) {
            $path = $root . $path;
        }

        return $path;
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
     * @return RbacService
     */
    public function getService(): RbacService
    {
        return RbacService::instance();
    }
}

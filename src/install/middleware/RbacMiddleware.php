<?php

declare(strict_types=1);

namespace support\auth\middleware;

use Closure;
use mon\http\Response;
use support\auth\RbacService;
use mon\auth\exception\RbacException;
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
     * 中间件实现接口
     *
     * @param RequestInterface $request  请求实例
     * @param Closure $callback 执行下一个中间件回调方法
     * @return Response
     */
    public function process(RequestInterface $request, Closure $callback): Response
    {
        // 验证登录
        if (!$request->uid) {
            // 不存在用户ID，未登录
            throw new RbacException('请先登录');
        }

        // 验证权限
        $check = $this->getService()->check($this->getPath($request), $request->uid);
        // 权限验证不通过
        if (!$check) {
            throw new RbacException('抱歉，您暂无权限');
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
        return $request->path();
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

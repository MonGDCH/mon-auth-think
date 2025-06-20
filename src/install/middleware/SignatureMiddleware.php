<?php

declare(strict_types=1);

namespace support\auth\middleware;

use Closure;
use mon\http\Response;
use support\auth\SignatureService;
use mon\auth\exception\APIException;
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
            throw new APIException('无效的App signature数据');
        }

        return $callback($request);
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

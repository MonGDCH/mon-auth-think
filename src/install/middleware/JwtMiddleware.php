<?php

declare(strict_types=1);

namespace support\auth\middleware;

use Closure;
use mon\http\Response;
use support\auth\JwtService;
use mon\auth\exception\JwtException;
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
     * 中间件实现接口
     *
     * @param RequestInterface $request  请求实例
     * @param Closure $callback 执行下一个中间件回调方法
     * @return Response
     */
    public function process(RequestInterface $request, Closure $callback): Response
    {
        // 获取Token，优先的请求头中获取，不存在则从cookie中获取
        $token = $this->getToken($request);
        if (!$token) {
            throw new JwtException('请先登录');
        }

        // 验证获取Token数据
        $data = JwtService::instance()->getTokenData($token);
        // 记录用户ID
        $request->uid = $data['aud'];
        // 记录Token数据
        $request->jwt = $data;

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
        $token = $request->header('X-Authorization', '');
        if (!$token) {
            // 请求头中没有token，从cookie中获取
            $token = $request->cookie('X-Authorization', '');
        }

        return $token;
    }
}

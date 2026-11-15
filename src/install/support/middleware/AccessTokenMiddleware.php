<?php

declare(strict_types=1);

namespace support\auth\middleware;

use Closure;
use mon\http\Response;
use mon\auth\exception\ApiException;
use support\auth\AccessTokenService;
use mon\http\interfaces\RequestInterface;
use mon\http\interfaces\Middlewareinterface;

/**
 * AccessToken权限校验中间件
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class AccessTokenMiddleware implements Middlewareinterface
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
        // 应用ID
        $appid = $request->post('app_id', null);
        // Token
        $token = $request->post('access_token', null);
        // 验证参数（严格判断，避免"0"值被empty误判为空）
        if ($token === null || $token === '' || $appid === null || $appid === '') {
            // 不存在APPID或Token
            throw new ApiException('无效的App Access Token数据');
        }

        // 验证签名，获取Token中的数据
        $data = AccessTokenService::instance()->getTokenData($token, $appid, $request->ip());
        $request->access_token = $data;

        return $callback($request);
    }
}

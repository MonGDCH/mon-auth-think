<?php

declare(strict_types=1);

namespace support\auth\middleware;

use Closure;
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
     * 中间件实现接口
     *
     * @param RequestInterface $request  请求实例
     * @param Closure $callback 执行下一个中间件回调方法
     * @return Response
     */
    public function process(RequestInterface $request, Closure $callback): Response
    {
        // 验证签名：签名数据取自POST表单（如需支持JSON body或GET，请自行组装数据后调用 SignatureService::checkToken）
        // checkToken内部已区分参数缺失(APPID_PARAMS_FAILD)、签名错误(SIGN_VERIFY_FAIL)等异常，校验失败直接抛出
        SignatureService::instance()->checkToken($request->post());

        return $callback($request);
    }
}

<?php

declare(strict_types=1);

namespace mon\auth;

use mon\http\Response;

/**
 * 权限验证未通过处理回调接口
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
interface ErrorHandlerInterface
{
    /**
     * 构造方法
     *
     * @param array $config 配置信息
     */
    public function __construct(array $config);

    /**
     * 不存在Token
     *
     * @return Response
     */
    public function notFound(): Response;

    /**
     * 验证失败
     *
     * @param integer $code 错误码
     * @param string $msg   错误信息
     * @return Response
     */
    public function checkError(int $code, string $msg): Response;
}

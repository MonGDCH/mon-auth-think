<?php

declare(strict_types=1);

namespace support\auth\middleware\handler;

use mon\http\Jump;
use mon\http\Response;
use mon\auth\ErrorHandlerInterface;

/**
 * 权限错误处理
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class ErrorHandler implements ErrorHandlerInterface
{
    /**
     * 配置信息
     *
     * @var array
     */
    protected $config = [];

    /**
     * 构造方法
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 不存在Token
     *
     * @return Response
     */
    public function notFound(): Response
    {
        return Jump::instance()->result(401, 'Token params invalid!', [], [], 'json', 401);
    }

    /**
     * 验证失败
     *
     * @param integer $code 错误码
     * @param string $msg   错误信息
     * @return Response
     */
    public function checkError(int $code, string $msg): Response
    {
        return Jump::instance()->result($code, $msg, [], [], 'json', 403);
    }
}

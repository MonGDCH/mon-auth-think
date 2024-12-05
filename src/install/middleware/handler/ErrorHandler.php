<?php

declare(strict_types=1);

namespace support\auth\middleware\handler;

use mon\http\Response;
use mon\auth\ErrorHandlerInterface;
use mon\http\exception\BusinessException;

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
        throw new BusinessException('Not found token!', 401, [], 401);
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
        throw new BusinessException($msg, $code, [], 403);
    }
}

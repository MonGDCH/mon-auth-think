<?php

declare(strict_types=1);

namespace mon\auth\api\concern;

use mon\auth\exception\ApiException;

/**
 * 加密盐校验
 *
 * 统一拦截「未配置」与「配置占位符未替换」两种失效状态：
 * install/config 下的模板使用 env('XXX', '%s') + Install::publish() 的 sprintf 替换，
 * 若运维手工复制模板或 publish 未成功执行，盐会残留为公开字面量 '%s'，
 * 此时 Token / 签名可被任意伪造，必须拒绝启动而不是带着弱密钥运行。
 *
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
final class SaltGuard
{
    /**
     * 配置模板占位符
     *
     * 由 Install::publish() 的 sprintf 替换，残留说明配置未正确生成
     */
    const PLACEHOLDER = '%s';

    /**
     * 校验加密盐
     *
     * @param string $salt 加密盐
     * @param string $name 业务名称，用于错误提示（如 AccessToken、Signature）
     * @throws ApiException
     * @return void
     */
    public static function assert(string $salt, string $name): void
    {
        if ($salt === '' || $salt === self::PLACEHOLDER) {
            throw new ApiException('未配置有效的' . $name . '加密盐', ApiException::AUTH_INIT_ERROR);
        }
    }
}

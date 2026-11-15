<?php

declare(strict_types=1);

/**
 * 向后兼容加载器
 *
 * 自 v1.3.1 起异常类统一命名为 ApiException。由于 PHP 类名不区分大小写，
 * 运行时旧类名 APIException 本身即可解析到 ApiException 类（class_alias 无法
 * 注册仅大小写不同的别名，会触发 Cannot redeclare class）。
 *
 * 唯一的缺口在于大小写敏感文件系统（如 Linux 生产环境）下，旧代码引用
 * APIException 时 Composer 的 PSR-4 查找会因文件名大小写不一致而失败。
 * 此处注册一个前置自动加载器处理该场景，保证旧代码零改动兼容。
 *
 * 注意：本文件依赖 composer 的 files 自动加载机制，若使用自定义自动加载请自行引入。
 */

if (!class_exists('mon\auth\exception\ApiException', false)) {
    spl_autoload_register(function (string $class): void {
        if (strcasecmp($class, 'mon\auth\exception\APIException') === 0) {
            // 触发 PSR-4 加载规范命名的 ApiException.php
            class_exists('mon\auth\exception\ApiException');
        }
    }, true, true);
}

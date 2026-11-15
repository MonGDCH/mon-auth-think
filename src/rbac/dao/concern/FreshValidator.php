<?php

declare(strict_types=1);

namespace mon\auth\rbac\dao\concern;

/**
 * 每次返回独立的验证器实例
 *
 * Dao::validate() 默认从容器获取单例，常驻进程（Workerman/Swoole）内同一实例会残留
 * 上一次请求的数据、场景与错误信息；验证器构造开销很小，按次创建可彻底消除跨请求状态残留。
 *
 * 返回类型使用完全限定名，避免 trait 方法中的类名按使用方命名空间解析造成歧义。
 *
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
trait FreshValidator
{
    /**
     * 获取验证器实例
     *
     * @return \mon\util\Validate
     */
    public function validate(): \mon\util\Validate
    {
        $class = $this->validate;

        return new $class();
    }
}

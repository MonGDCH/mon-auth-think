<?php

declare(strict_types=1);

namespace mon\auth\tests\rbac;

use PHPUnit\Framework\TestCase;
use mon\auth\rbac\Auth;

/**
 * RBAC 配置与缓存隔离（常驻进程下配置变更必须即时生效）
 */
class ConfigIsolationTest extends TestCase
{
    /**
     * 配置变更后必须丢弃已缓存的Dao，否则会继续读写旧表
     *
     * @return void
     */
    public function testInitInvalidatesCachedDao(): void
    {
        $auth = new Auth(['auth_role_access' => 'auth_access', 'auth_role' => 'auth_role']);

        // 先触发缓存
        $this->assertSame('auth_access', $auth->getAccessDao()->getTable());
        $this->assertSame('auth_role', $auth->getRoleDao()->getTable());

        $auth->init(['auth_role_access' => 'tenant2_access', 'auth_role' => 'tenant2_role']);

        $this->assertSame('tenant2_access', $auth->getAccessDao()->getTable());
        $this->assertSame('tenant2_role', $auth->getRoleDao()->getTable());
    }

    /**
     * setConfig 同样需要失效缓存
     *
     * @return void
     */
    public function testSetConfigInvalidatesCachedDao(): void
    {
        $auth = new Auth(['auth_rule' => 'auth_rule']);
        $this->assertSame('auth_rule', $auth->getRuleDao()->getTable());

        $auth->setConfig(['auth_rule' => 'tenant_rule']);
        $this->assertSame('tenant_rule', $auth->getRuleDao()->getTable());
    }

    /**
     * init() 以默认配置为基准，不累积上一次的配置
     *
     * @return void
     */
    public function testInitDoesNotAccumulateConfig(): void
    {
        $auth = new Auth(['admin_mark' => '#']);
        $this->assertSame('#', $auth->getConfig('admin_mark'));

        $auth->init([]);
        $this->assertSame('*', $auth->getConfig('admin_mark'));
    }

    /**
     * 验证器不得复用容器单例，避免常驻进程内残留上一次请求的数据与错误信息
     *
     * @return void
     */
    public function testValidatorIsFreshInstance(): void
    {
        $auth = new Auth();
        $dao = $auth->getAccessDao();

        $this->assertNotSame($dao->validate(), $dao->validate());
    }
}

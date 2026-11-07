<?php

declare(strict_types=1);

namespace mon\auth\tests\rbac;

use PHPUnit\Framework\TestCase;
use mon\auth\rbac\Auth;
use mon\auth\rbac\dao\Role;

/**
 * RBAC Auth 测试：配置访问与权限比对核心逻辑（无DB依赖）
 */
class AuthTest extends TestCase
{
    protected function auth(): Auth
    {
        return new Auth(['admin_mark' => '*']);
    }

    public function testGetConfigMissingKeyReturnsNull(): void
    {
        $this->assertNull($this->auth()->getConfig('not_exist_key'));
    }

    public function testGetConfigReturnsKnownKey(): void
    {
        $this->assertSame('*', $this->auth()->getConfig('admin_mark'));
    }

    public function testGetConfigFullReturnsArray(): void
    {
        $config = $this->auth()->getConfig();
        $this->assertIsArray($config);
        $this->assertArrayHasKey('auth_role', $config);
    }

    /**
     * P0回归：子角色权限不允许超出父级权限范围
     */
    public function testIsUltraViresDetectsEscalation(): void
    {
        $role = new Role($this->auth());
        $method = new \ReflectionMethod(Role::class, 'isUltraVires');
        $method->setAccessible(true);

        $parentRules = ['1', '2', '3'];

        // 子集不越权
        $this->assertFalse($method->invoke($role, $parentRules, ['1', '2']));
        // 相等不越权
        $this->assertFalse($method->invoke($role, $parentRules, ['3', '1', '2']));
        // 越出父级范围的属于越权
        $this->assertTrue($method->invoke($role, $parentRules, ['1', '99']));
        // 空权限不越权
        $this->assertFalse($method->invoke($role, $parentRules, []));
    }

    /**
     * P0回归：获取被移除权限的计算
     */
    public function testGetRemovedPermissions(): void
    {
        $role = new Role($this->auth());
        $method = new \ReflectionMethod(Role::class, 'getRemovedPermissions');
        $method->setAccessible(true);

        // 旧权限3被移除
        $this->assertSame(['3'], $method->invoke($role, ['1', '2', '3'], ['1', '2']));
        // 未移除任何权限
        $this->assertSame([], $method->invoke($role, ['1', '2'], ['1', '2', '3']));
        // 顺序无关
        $this->assertSame(['2', '5'], $method->invoke($role, ['1', '2', '5'], ['3', '1']));
    }
}

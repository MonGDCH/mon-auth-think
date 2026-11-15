<?php

declare(strict_types=1);

namespace mon\auth\tests\jwt;

use PHPUnit\Framework\TestCase;
use mon\auth\jwt\Auth;
use mon\auth\exception\JwtException;

/**
 * JWT 配置重建与占位符密钥回归
 */
class ConfigIsolationTest extends TestCase
{
    /**
     * init() 以默认配置为基准重建，不累积上一次的配置
     *
     * @return void
     */
    public function testInitDoesNotAccumulateConfig(): void
    {
        $auth = new Auth(['key' => 'first_key', 'exp' => 60]);
        $this->assertSame(60, $auth->getConfig('exp'));

        $auth->init(['key' => 'second_key']);
        $this->assertSame('second_key', $auth->getConfig('key'));
        $this->assertSame(3600, $auth->getConfig('exp'));
    }

    /**
     * setConfig() 为增量设置
     *
     * @return void
     */
    public function testSetConfigMergesIncrementally(): void
    {
        $auth = new Auth(['key' => 'first_key']);
        $auth->setConfig(['exp' => 120]);

        $this->assertSame('first_key', $auth->getConfig('key'));
        $this->assertSame(120, $auth->getConfig('exp'));
    }

    /**
     * 配置模板占位符 key 必须被拒绝
     *
     * @return void
     */
    public function testPlaceholderKeyRejected(): void
    {
        $this->expectException(JwtException::class);
        $this->expectExceptionCode(JwtException::JWT_CONFIG_NOT_FOUND);
        new Auth(['key' => '%s']);
    }
}

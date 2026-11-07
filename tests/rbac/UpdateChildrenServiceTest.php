<?php

declare(strict_types=1);

namespace mon\auth\tests\rbac;

use PHPUnit\Framework\TestCase;
use mon\auth\rbac\UpdateChildrenService;

/**
 * UpdateChildrenService::batchUpdateSql 纯函数测试：SQL生成、去重、转义
 */
class UpdateChildrenServiceTest extends TestCase
{
    protected function service(string $table = 'roles'): UpdateChildrenService
    {
        return new UpdateChildrenService($table);
    }

    public function testEmptyDataReturnsEmptyString(): void
    {
        $this->assertSame('', $this->service()->batchUpdateSql([]));
    }

    public function testSingleRowGeneratesCaseWhen(): void
    {
        $sql = $this->service()->batchUpdateSql([
            ['id' => 5, 'pids' => '0,2', 'status' => 1],
        ]);

        $this->assertStringStartsWith('UPDATE `roles` SET ', $sql);
        $this->assertStringContainsString('`pids` = CASE `id` WHEN 5 THEN \'0,2\' ', $sql);
        $this->assertStringContainsString('`status` = CASE `id` WHEN 5 THEN 1 ', $sql);
        $this->assertStringContainsString('WHERE `id` IN (5)', $sql);
    }

    public function testDuplicateIdKeepsLastRecord(): void
    {
        $sql = $this->service()->batchUpdateSql([
            ['id' => 5, 'pids' => 'first'],
            ['id' => 5, 'pids' => 'last'],
        ]);

        $this->assertStringContainsString("'last'", $sql);
        $this->assertStringNotContainsString("'first'", $sql);
    }

    public function testStringValuesAreEscaped(): void
    {
        $sql = $this->service()->batchUpdateSql([
            ['id' => 1, 'pids' => "0,1' OR '1'='1"],
        ]);

        // 所有单引号被addslashes转义，无法逃逸出字符串字面量
        $this->assertStringContainsString("0,1\\' OR \\'1\\'=\\'1", $sql);
    }

    public function testMultipleIdsInWhereClause(): void
    {
        $sql = $this->service()->batchUpdateSql([
            ['id' => 1, 'pids' => '0'],
            ['id' => 2, 'pids' => '0,1'],
            ['id' => 3, 'pids' => '0,1'],
        ]);

        $this->assertStringContainsString('WHEN 1 THEN', $sql);
        $this->assertStringContainsString('WHEN 2 THEN', $sql);
        $this->assertStringContainsString('WHEN 3 THEN', $sql);
        $this->assertStringContainsString('WHERE `id` IN (1,2,3)', $sql);
    }

    public function testFieldMissingInSomeRows(): void
    {
        // 部分行缺少字段时，仅对存在该字段的行生成WHEN分支
        $sql = $this->service()->batchUpdateSql([
            ['id' => 1, 'pids' => '0'],
            ['id' => 2, 'status' => 0],
        ]);

        $this->assertStringContainsString('`pids` = CASE `id` WHEN 1 THEN', $sql);
        $this->assertStringNotContainsString('WHEN 2 THEN \'', $sql);
    }
}

<?php

namespace Qd\SchemaBundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use Qd\SchemaBundle\Service\SchemaDiff;

class SchemaDiffTest extends TestCase
{
    private SchemaDiff $schemaDiff;

    protected function setUp(): void
    {
        $this->schemaDiff = new SchemaDiff();
    }

    public function testIsEmptyReturnsTrueForNullDiff(): void
    {
        $this->assertTrue($this->schemaDiff->isEmpty(null));
    }

    public function testIsEmptyReturnsTrueForEmptyDiff(): void
    {
        $diff = [
            'fields_added' => [],
            'fields_removed' => [],
            'fields_changed' => [],
            'rels_added' => [],
            'rels_removed' => [],
            'rels_changed' => [],
        ];

        $this->assertTrue($this->schemaDiff->isEmpty($diff));
    }

    public function testIsEmptyReturnsFalseWhenFieldsAdded(): void
    {
        $diff = [
            'fields_added' => [['name' => 'newField']],
            'fields_removed' => [],
            'fields_changed' => [],
            'rels_added' => [],
            'rels_removed' => [],
            'rels_changed' => [],
        ];

        $this->assertFalse($this->schemaDiff->isEmpty($diff));
    }

    public function testDiffDetectsAddedFields(): void
    {
        $old = [
            'fields' => ['id' => ['type' => 'integer']],
            'rels' => [],
        ];

        $new = [
            'fields' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
            ],
            'rels' => [],
        ];

        $diff = $this->schemaDiff->diff($old, $new);

        $this->assertCount(1, $diff['fields_added']);
        $this->assertEquals('name', $diff['fields_added'][0]['name']);
    }

    public function testDiffDetectsRemovedFields(): void
    {
        $old = [
            'fields' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
            ],
            'rels' => [],
        ];

        $new = [
            'fields' => ['id' => ['type' => 'integer']],
            'rels' => [],
        ];

        $diff = $this->schemaDiff->diff($old, $new);

        $this->assertCount(1, $diff['fields_removed']);
        $this->assertEquals('name', $diff['fields_removed'][0]['name']);
    }

    public function testDiffDetectsChangedFields(): void
    {
        $old = [
            'fields' => ['name' => ['type' => 'string', 'length' => 100]],
            'rels' => [],
        ];

        $new = [
            'fields' => ['name' => ['type' => 'string', 'length' => 255]],
            'rels' => [],
        ];

        $diff = $this->schemaDiff->diff($old, $new);

        $this->assertCount(1, $diff['fields_changed']);
        $this->assertEquals('name', $diff['fields_changed'][0]['name']);
    }

    public function testToSystemCommentGeneratesMessagesForAddedFields(): void
    {
        $diff = [
            'fields_added' => [
                ['name' => 'email', 'spec' => ['type' => 'string']],
            ],
            'fields_removed' => [],
            'fields_changed' => [],
            'rels_added' => [],
            'rels_removed' => [],
            'rels_changed' => [],
        ];

        $messages = $this->schemaDiff->toSystemComment($diff);

        $this->assertNotEmpty($messages);
        $this->assertStringContainsString('email', $messages[0]);
    }
}

<?php

namespace Tests\Unit;

use App\Services\Templates\PlaceholderExtractor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PlaceholderExtractorTest extends TestCase
{
    private PlaceholderExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new PlaceholderExtractor;
    }

    #[Test]
    public function it_splits_system_and_user_placeholders(): void
    {
        $result = $this->extractor->split([
            'org.inn',
            'doc.number',
            'client_name',
            'total_amount',
        ]);

        $this->assertSame(['org.inn', 'doc.number'], $result['system']);
        $this->assertSame(['client_name', 'total_amount'], $result['user']);
    }

    #[Test]
    public function it_treats_org_and_doc_prefixes_as_system(): void
    {
        $this->assertTrue($this->extractor->isSystem('org.bank_account'));
        $this->assertTrue($this->extractor->isSystem('doc.date'));

        // Похожее имя без точки системным не считается
        $this->assertFalse($this->extractor->isSystem('organization_name'));
        $this->assertFalse($this->extractor->isSystem('client_name'));
    }

    #[Test]
    public function it_builds_readable_labels(): void
    {
        // Известное поле берётся из словаря
        $this->assertSame('ИНН клиента', $this->extractor->humanize('client_inn'));

        // Незнакомое собирается из самого ключа
        $this->assertSame('Warehouse code', $this->extractor->humanize('warehouse_code'));
    }
}

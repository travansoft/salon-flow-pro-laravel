<?php

namespace Tests\Unit\Billing;

use App\Services\XlsxWriter;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class XlsxWriterTest extends TestCase
{
    public function test_build_creates_a_valid_xlsx_package_with_headings_and_rows(): void
    {
        $path = (new XlsxWriter)->build('Bills', ['Bill #', 'Total'], [['B-1', 120.5]]);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertNotFalse($zip->getFromName('xl/workbook.xml'));
        $zip->close();
        unlink($path);

        $this->assertStringContainsString('Bill #', $sheet);
        $this->assertStringContainsString('B-1', $sheet);
        $this->assertStringContainsString('<v>120.5</v>', $sheet);
    }

    public function test_build_escapes_xml_special_characters(): void
    {
        $path = (new XlsxWriter)->build('Bills', ['Client'], [['A & <B>']]);

        $zip = new ZipArchive;
        $zip->open($path);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);

        $this->assertStringContainsString('A &amp; &lt;B&gt;', $sheet);
    }

    public function test_columns_beyond_z_use_two_letter_references(): void
    {
        $path = (new XlsxWriter)->build('Wide', array_fill(0, 28, 'h'), []);

        $zip = new ZipArchive;
        $zip->open($path);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);

        $this->assertStringContainsString('r="AB1"', $sheet);
    }
}

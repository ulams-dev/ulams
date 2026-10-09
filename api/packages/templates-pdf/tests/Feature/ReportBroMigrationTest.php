<?php

namespace Ulams\TemplatesPdf\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Templates\Events\ManuallyTriggeredEvent;
use Ulams\Templates\Models\Template;
use Ulams\Templates\Models\TemplateSection;
use Ulams\TemplatesPdf\Core\PdfChannel;
use Ulams\TemplatesPdf\Models\FabricPDF;
use Ulams\TemplatesPdf\Pdfme\CertificateTemplates;
use Ulams\TemplatesPdf\Pdfme\PdfmeTemplate;
use Ulams\TemplatesPdf\Pdfme\ReportBroConverter;
use Ulams\TemplatesPdf\Pdfme\ReportBroMigrator;
use Ulams\TemplatesPdf\Tests\TestCase;

class ReportBroMigrationTest extends TestCase
{
    use CreatesUsers, DatabaseTransactions;

    private function reportBro(): array
    {
        return [
            'docElements' => [
                ['elementType' => 'text', 'id' => 1, 'containerId' => '0_content', 'x' => 111, 'y' => 191, 'width' => 620, 'height' => 40, 'content' => 'CERTIFICATE', 'horizontalAlignment' => 'center', 'textColor' => '#e2c07e', 'font' => 'times', 'fontSize' => '22'],
                ['elementType' => 'text', 'id' => 2, 'containerId' => '0_content', 'x' => 111, 'y' => 316, 'width' => 620, 'height' => 90, 'content' => '${VarUserName}', 'horizontalAlignment' => 'center', 'font' => 'helvetica', 'fontSize' => '60'],
                ['elementType' => 'text', 'id' => 3, 'containerId' => '0_content', 'x' => 111, 'y' => 237, 'width' => 620, 'height' => 40, 'content' => 'Course: ${VarCourseTitle}', 'font' => 'helvetica', 'fontSize' => 12, 'bold' => true],
                ['elementType' => 'line', 'id' => 4, 'containerId' => '0_content', 'x' => 80, 'y' => 283, 'width' => 680, 'height' => 1, 'color' => '#000000'],
                ['elementType' => 'image', 'id' => 5, 'containerId' => '0_content', 'x' => 0, 'y' => 0, 'width' => 10, 'height' => 10, 'image' => 'data:image/png;base64,iVBORw0KGgo='],
                ['elementType' => 'image', 'id' => 6, 'containerId' => '0_content', 'x' => 0, 'y' => 0, 'width' => 10, 'height' => 10, 'source' => '${GlobalSettingsLogo}', 'image' => ''],
                ['elementType' => 'table', 'id' => 7, 'containerId' => '0_content', 'x' => 0, 'y' => 0, 'width' => 10, 'height' => 10],
            ],
            'parameters' => [],
            'styles' => [],
            'version' => 4,
            'documentProperties' => ['pageFormat' => 'A4', 'unit' => 'mm', 'orientation' => 'landscape', 'header' => false, 'footer' => false, 'marginLeft' => '', 'marginTop' => ''],
        ];
    }

    public function testConvertsSimpleElements(): void
    {
        $result = (new ReportBroConverter())->convert($this->reportBro());
        $fields = collect($result['template']['schemas'][0])->keyBy('name');

        $this->assertEqualsWithDelta(297, $result['template']['basePdf']['width'], 0.5);
        $this->assertEqualsWithDelta(210, $result['template']['basePdf']['height'], 0.5);
        $this->assertSame(5, $result['converted']);
        $this->assertSame(['image #6 (not an embedded PNG/JPEG)', 'table #7'], $result['dropped']);

        // "${VarUserName}" alone becomes an editable field named after the variable
        $this->assertArrayNotHasKey('readOnly', $fields['@VarUserName']);
        $this->assertSame(60.0, $fields['@VarUserName']['fontSize']);
        // pt -> mm, offset by the default 20pt margins
        $this->assertEqualsWithDelta((111 + 20) * 0.352778, $fields['@VarUserName']['position']['x'], 0.01);
        $this->assertEqualsWithDelta((316 + 20) * 0.352778, $fields['@VarUserName']['position']['y'], 0.01);

        $this->assertTrue($fields['text1']['readOnly']);
        $this->assertSame('PlayfairDisplay-Bold', $fields['text1']['fontName']);
        $this->assertSame('#E2C07E', $fields['text1']['fontColor']);
        $this->assertSame('Course: ${VarCourseTitle}', $fields['text3']['content']);
        $this->assertSame('NotoSans-Bold', $fields['text3']['fontName']);
        $this->assertSame('line', $fields['line4']['type']);
        $this->assertSame('image', $fields['image5']['type']);

        // the converted template renders the variables
        $prepared = PdfmeTemplate::prepare($result['template'], ['@VarUserName' => 'Jan', '@VarCourseTitle' => 'BHP']);
        $this->assertSame('Jan', $prepared['inputs'][0]['@VarUserName']);
        $this->assertSame('Course: BHP', collect($prepared['template']['schemas'][0])->firstWhere('name', 'text3')['content']);
    }

    public function testCommandConvertsTemplatesAndIssuedPdfsAndKeepsBackups(): void
    {
        File::deleteDirectory(storage_path('app/' . ReportBroMigrator::BACKUP_DIRECTORY));

        $custom = Template::factory()->create(['name' => 'Our certificate', 'channel' => PdfChannel::class, 'event' => ManuallyTriggeredEvent::class]);
        TemplateSection::factory()->create(['template_id' => $custom->getKey(), 'key' => 'title', 'content' => 'Certificate']);
        TemplateSection::factory()->create(['template_id' => $custom->getKey(), 'key' => 'content', 'content' => json_encode($this->reportBro())]);

        $shipped = Template::factory()->create(['name' => 'Default template for event ManuallyTriggeredEvent on PdfChannel channel', 'channel' => PdfChannel::class, 'event' => ManuallyTriggeredEvent::class]);
        TemplateSection::factory()->create(['template_id' => $shipped->getKey(), 'key' => 'content', 'content' => json_encode($this->reportBro())]);

        $pdfme = Template::factory()->create(['name' => 'Already pdfme', 'channel' => PdfChannel::class, 'event' => ManuallyTriggeredEvent::class]);
        TemplateSection::factory()->create(['template_id' => $pdfme->getKey(), 'key' => 'content', 'content' => CertificateTemplates::content()]);

        $issued = FabricPDF::factory()->create(['template_id' => $custom->getKey(), 'content' => json_encode($this->reportBro()), 'path' => 'old.pdf']);

        $this->artisan('templates-pdf:migrate-reportbro', ['--dry-run' => true])
            ->expectsOutputToContain('converted (5 fields); dropped: image #6 (not an embedded PNG/JPEG), table #7')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();
        $this->assertSame('Our certificate', $custom->refresh()->name);

        $this->artisan('templates-pdf:migrate-reportbro')->assertSuccessful();

        $custom->refresh();
        $this->assertSame(ReportBroMigrator::LEGACY_PREFIX . 'Our certificate', $custom->name);
        $converted = PdfmeTemplate::decode($custom->sections->firstWhere('key', 'content')->content);
        $this->assertTrue(PdfmeTemplate::isPdfme($converted));
        $this->assertContains('@VarUserName', PdfmeTemplate::fieldNames($converted));

        // auto-created defaults get the new default content of their event
        $this->assertSame(
            \Ulams\TemplatesPdf\Core\UserVariables::defaultSectionsContent()['content'],
            $shipped->refresh()->sections->firstWhere('key', 'content')->content
        );

        $this->assertSame('Already pdfme', $pdfme->refresh()->name);

        $issued->refresh();
        $this->assertTrue(PdfmeTemplate::isPdfme(PdfmeTemplate::decode($issued->content)));
        $this->assertNull($issued->path);

        $backups = File::files(storage_path('app/' . ReportBroMigrator::BACKUP_DIRECTORY));
        $this->assertCount(1, $backups);
        $lines = array_filter(explode("\n", File::get($backups[0]->getPathname())));
        $this->assertCount(3, $lines);
        $this->assertStringContainsString('docElements', $lines[0]);

        // running again changes nothing
        $this->artisan('templates-pdf:migrate-reportbro')->expectsOutput('No ReportBro templates or PDFs found.')->assertSuccessful();

        File::deleteDirectory(storage_path('app/' . ReportBroMigrator::BACKUP_DIRECTORY));
    }
}

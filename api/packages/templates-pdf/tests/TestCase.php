<?php

namespace Ulams\TemplatesPdf\Tests;

use Ulams\Categories\UlamsCategoriesServiceProvider;
use Ulams\Core\Models\User;
use Ulams\TemplatesPdf\UlamsTemplatesPdfServiceProvider;
use Ulams\Templates\Database\Seeders\PermissionTableSeeder as TemplatesPermissionTableSeeder;
use Ulams\TemplatesPdf\Database\Seeders\PermissionTableSeeder as TemplatesPdfPermissionTableSeeder;

use Ulams\Templates\UlamsTemplatesServiceProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Ulams\Courses\UlamsCourseServiceProvider;

class TestCase extends \Ulams\Core\Tests\TestCase
{
    use DatabaseTransactions;

    public const PDF_SERVICE = 'http://pdf.test';
    public const PDF_TOKEN = 'test-pdf-token';

    protected function setUp(): void
    {
        parent::setUp();
        // never reach a real renderer from tests
        Http::preventStrayRequests();
        $this->seed(TemplatesPermissionTableSeeder::class);
        $this->seed(TemplatesPdfPermissionTableSeeder::class);
    }

    protected function getPackageProviders($app): array
    {
        $providers = [
            ...parent::getPackageProviders($app),
            PassportServiceProvider::class,
            PermissionServiceProvider::class,
            UlamsCourseServiceProvider::class,
            UlamsTemplatesServiceProvider::class,
            UlamsCategoriesServiceProvider::class,
            UlamsTemplatesPdfServiceProvider::class,
        ];

        if (class_exists(\Ulams\Auth\UlamsAuthServiceProvider::class)) {
            $providers[] = \Ulams\Auth\UlamsAuthServiceProvider::class;
        }

        return $providers;
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('passport.client_uuids', true);
        $app['config']->set('ulams_templates_pdf.pdf.service_url', self::PDF_SERVICE);
        $app['config']->set('ulams_templates_pdf.pdf.internal_token', self::PDF_TOKEN);
    }

    /**
     * Fakes the PDF renderer: /render answers with a tiny PDF that contains the
     * learner name sent in the inputs, so tests can check what was rendered.
     */
    protected int $pdfServiceStatus = 200;
    private bool $pdfServiceFaked = false;

    protected function fakePdfService(int $status = 200): void
    {
        // later Http::fake() stubs never override earlier ones: register once, switch the status
        $this->pdfServiceStatus = $status;
        if ($this->pdfServiceFaked) {
            return;
        }
        $this->pdfServiceFaked = true;
        Http::fake([
            self::PDF_SERVICE . '/render' => function (Request $request) {
                $status = $this->pdfServiceStatus;
                if ($status !== 200) {
                    return Http::response(['error' => 'render_failed', 'message' => 'boom'], $status);
                }
                $name = $request->data()['inputs'][0]['@VarUserName'] ?? '';
                return Http::response('%PDF-1.7 fake ' . $name . ' %%EOF', 200, ['Content-Type' => 'application/pdf']);
            },
            self::PDF_SERVICE . '/fonts' => Http::response(['data' => [['name' => 'NotoSans-Regular', 'file' => 'NotoSans-Regular.ttf', 'fallback' => true]]]),
            self::PDF_SERVICE . '/fonts/*' => Http::response('TTF-BYTES', 200, ['Content-Type' => 'font/ttf']),
        ]);
    }
}

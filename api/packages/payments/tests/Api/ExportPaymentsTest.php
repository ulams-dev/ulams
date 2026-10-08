<?php

namespace Ulams\Payments\Tests\Api;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\Payments\Database\Seeders\PaymentsPermissionsSeeder;
use Ulams\Payments\Enums\ExportFormatEnum;
use Ulams\Payments\Enums\PaymentStatus;
use Ulams\Payments\Exports\PaymentsExport;
use Ulams\Payments\Models\Payment;
use Ulams\Payments\Tests\TestCase;
use Maatwebsite\Excel\Facades\Excel;

class ExportPaymentsTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PaymentsPermissionsSeeder::class);
        $this->user = $this->makeAdmin();
        Excel::fake();
    }

    public function testExportPaymentUnauthorized(): void
    {
        $this->json('GET', '/api/admin/payments/export')->assertUnauthorized();
    }

    public function testExportPayment(): void
    {
        Payment::factory()->count(10)->create();

        $this->actingAs($this->user, 'api')->json('GET', '/api/admin/payments/export')->assertOk();

        Excel::assertDownloaded('payments.csv', function (PaymentsExport $paymentsExport) {
            $this->assertCount(10, $paymentsExport->collection());

            return true;
        });
    }

    public function testExportPaymentsToExcel(): void
    {
        Payment::factory()->count(10)->create();

        $this->actingAs($this->user, 'api')->json('GET', '/api/admin/payments/export', ['format' => ExportFormatEnum::XLSX])->assertOk();

        Excel::assertDownloaded('payments.xlsx', function (PaymentsExport $paymentsExport) {
            $this->assertCount(10, $paymentsExport->collection());

            return true;
        });

        $this->actingAs($this->user, 'api')->json('GET', '/api/admin/payments/export', ['format' => ExportFormatEnum::XLS])->assertOk();

        Excel::assertDownloaded('payments.xls', function (PaymentsExport $paymentsExport) {
            $this->assertCount(10, $paymentsExport->collection());

            return true;
        });
    }

    public function testExportPaymentsWithCriteriaStatus(): void
    {
        Payment::factory()->count(10)->create();
        Payment::factory()->count(5)->create(['status' => PaymentStatus::PAID]);

        $this->actingAs($this->user, 'api')->json('GET', '/api/admin/payments/export', [
            'status' => PaymentStatus::PAID,
        ])->assertOk();

        Excel::assertDownloaded('payments.csv', function (PaymentsExport $paymentsExport) {
            $this->assertCount(5, $paymentsExport->collection());

            return true;
        });
    }

    public function testExportPaymentsWithCriteriaUser(): void
    {
        $student = $this->makeStudent();
        Payment::factory()->count(10)->create([
            'user_id' => $student->getKey(),
        ]);
        Payment::factory()->count(5)->create([
            'status' => PaymentStatus::PAID,
            'user_id' => $student->getKey(),
        ]);

        $this->actingAs($this->user, 'api')->json('GET', '/api/admin/payments/export', [
            'user_id' => $student->getKey(),
            'status' => PaymentStatus::PAID,
        ])->assertOk();

        Excel::assertDownloaded('payments.csv', function (PaymentsExport $paymentsExport) {
            $this->assertCount(5, $paymentsExport->collection());

            return true;
        });
    }
}

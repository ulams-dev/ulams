<?php

namespace EscolaLms\Payments\Tests\Api;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Payments\Enums\PaymentStatus;
use EscolaLms\Payments\Http\Resources\PaymentResource;
use EscolaLms\Payments\Models\Payment;
use EscolaLms\Payments\Tests\Traits\CreatesBillable;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

class PaymentListTest extends \EscolaLms\Payments\Tests\TestCase
{
    use CreatesBillable;
    use CreatesUsers;

    public function testStudentCanListRegisteredPayments()
    {
        $billable = $this->createBillableStudent();
        $payments = Payment::factory()->count(20)->create([
            'user_id' => $billable->getKey(),
        ]);
        $billable2 = $this->createBillableStudent();
        $payments2 = Payment::factory()->count(20)->create([
            'user_id' => $billable2->getKey(),
        ]);

        /** @var TestResponse $response */
        $response = $this->actingAs($billable)->json('GET', 'api/payments/', [
            'per_page' => 20
        ]);
        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $payments[0]->getKey(),
        ]);

        $response->assertJsonCount(20, 'data');
        // $this->assertCountWithOrWithoutWrapper($response, 20, PaymentResource::$wrap);
    }

    public function testAdminCanListAllRegisteredPayments()
    {
        if (Payment::count() > 0) {
            Payment::truncate();
        }

        $billable = $this->createBillableStudent();
        $payments = Payment::factory()->count(10)->create([
            'user_id' => $billable->getKey(),
        ]);
        $billable2 = $this->createBillableStudent();
        $payments2 = Payment::factory()->count(10)->create([
            'user_id' => $billable2->getKey(),
        ]);

        $admin = $this->makeAdmin();
        $admin->save();

        /** @var TestResponse $response */
        $response = $this->actingAs($admin, 'api')->json('GET', 'api/admin/payments/', [
            'per_page' => 20
        ]);
        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $payments[0]->getKey()
        ]);
        $response->assertJsonFragment([
            'id' => $payments2[0]->getKey()
        ]);

        $response->assertJsonCount(20, 'data');


        // $this->assertCountWithOrWithoutWrapper($response, 20, PaymentResource::$wrap);
    }

    public function testAdminCanListAllRegisteredPaymentsWithFilter()
    {
        if (Payment::count() > 0) {
            Payment::truncate();
        }

        $billable = $this->createBillableStudent();
        $paymentsNew = Payment::factory()->count(5)->create([
            'user_id' => $billable->getKey(),
            'status' => PaymentStatus::NEW,
        ]);
        $paymentsPaid = Payment::factory()->count(5)->create([
            'user_id' => $billable->getKey(),
            'status' => PaymentStatus::PAID,
        ]);

        $admin = $this->makeAdmin();
        $admin->save();

        /** @var TestResponse $response */
        $response = $this->actingAs($admin, 'api')->json('GET', 'api/admin/payments/');
        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $paymentsNew[0]->getKey()
        ]);
        $response->assertJsonFragment([
            'id' => $paymentsPaid[0]->getKey()
        ]);

        $response->assertJsonCount(10, 'data');
        // $this->assertCountWithOrWithoutWrapper($response, 10, PaymentResource::$wrap);

        /** @var TestResponse $response */
        $response = $this->actingAs($admin, 'api')->json('GET', 'api/admin/payments/', [
            'status' => PaymentStatus::PAID,
        ]);
        $response->assertOk();
        $response->assertJsonMissing([
            'id' => $paymentsNew[0]->getKey()
        ]);
        $response->assertJsonFragment([
            'id' => $paymentsPaid[0]->getKey()
        ]);
        $response->json('data');

        $response->assertJsonCount(5, 'data');

        // $this->assertCountWithOrWithoutWrapper($response, 5, PaymentResource::$wrap);

        /** @var TestResponse */
        $response = $this->actingAs($admin, 'api')->json('GET', 'api/admin/payments/', [
            'date_from' => Carbon::now()->addDay()->toIso8601String(),
        ]);
        $response->assertOk();

        $response->assertJsonCount(0, 'data');
        // $this->assertCountWithOrWithoutWrapper($response, 0, PaymentResource::$wrap);

        /** @var TestResponse */
        $response = $this->actingAs($admin, 'api')->json('GET', 'api/admin/payments/', [
            'date_to' => Carbon::now()->subDay()->toIso8601String(),
        ]);
        $response->assertOk();
        $response->assertJsonCount(0, 'data');

        // $this->assertCountWithOrWithoutWrapper($response, 0, PaymentResource::$wrap);

        /** @var TestResponse */
        $response = $this->actingAs($admin, 'api')->json('GET', 'api/admin/payments/', [
            'date_from' => Carbon::now()->subDay()->toIso8601String(),
            'date_to' => Carbon::now()->addDay()->toIso8601String(),
        ]);
        $response->assertOk();

        $response->assertJsonCount(10, 'data');
        // $this->assertCountWithOrWithoutWrapper($response, 10, PaymentResource::$wrap);

        /** @var TestResponse $response */
        $response = $this->actingAs($admin, 'api')->json('GET', 'api/admin/payments/', [
            'order_id' => $paymentsNew[0]->order_id,
        ]);
        $response->assertOk();

        $response->assertJsonFragment([
            'id' => $paymentsNew[0]->getKey()
        ]);
        $response->assertJsonMissing([
            'id' => $paymentsPaid[0]->getKey()
        ]);
        $response->json('data');

        $response->assertJsonCount(1, 'data');
        // $this->assertCountWithOrWithoutWrapper($response, 1, PaymentResource::$wrap);
    }

    /*
    private function assertCountWithOrWithoutWrapper(TestResponse $response, int $count, ?string $wrapper = null)
    {
        $data = null;
        if (!is_null($wrapper)) {
            $data = $response->json($wrapper);
        }
        if (!is_null($data)) { // key $wrapper exists in response json
            $response->assertJsonCount($count, $wrapper);
        } else {
            $response->assertJsonCount($count);
        }
    }
    */
}

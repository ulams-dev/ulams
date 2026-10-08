<?php

namespace EscolaLms\Vouchers\Tests\Api;

use EscolaLms\Cart\Models\Product;
use EscolaLms\Core\Enums\UserRole;
use EscolaLms\Vouchers\Database\Seeders\VoucherPermissionsSeeder;
use EscolaLms\Vouchers\Http\Resources\CouponResource;
use EscolaLms\Vouchers\Models\CartItem;
use EscolaLms\Vouchers\Models\Category;
use EscolaLms\Vouchers\Models\Coupon;
use EscolaLms\Vouchers\Models\User;
use EscolaLms\Vouchers\Services\Contracts\CouponServiceContract;
use EscolaLms\Vouchers\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;

class AdminVoucherTest extends TestCase
{
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();

        $this->seed(VoucherPermissionsSeeder::class);

        $this->user = User::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole(UserRole::ADMIN);
    }

    public function testCreateCoupon()
    {
        $coupon = Coupon::factory()->make();
        $data = $coupon->toArray();

        $this->response = $this->actingAs($this->user, 'api')->json('POST', '/api/admin/vouchers/', $data);
        $this->response->assertCreated();

        $id = $this->response->json('data.id');
        $couponDb = Coupon::find($id);

        $this->response->assertJsonFragment([
            'data' => CouponResource::make($couponDb)->toArray(null)
        ]);
    }

    public function testCreateCouponWith100Percent()
    {
        $coupon = Coupon::factory()->make();
        $data = $coupon->toArray();
        $data['amount'] = 100;

        $this->response = $this->actingAs($this->user, 'api')->json('POST', '/api/admin/vouchers/', $data);
        $this->response->assertCreated();

        $id = $this->response->json('data.id');
        $couponDb = Coupon::find($id);

        $this->response->assertJsonFragment([
            'data' => CouponResource::make($couponDb)->toArray(null)
        ]);
    }

    public function testCantCreatePercentCouponWithAbove100Percent()
    {
        $coupon = Coupon::factory()->make();
        $data = $coupon->toArray();
        $data['amount'] = 101;

        /** @var TestResponse $response */
        $response = $this->actingAs($this->user, 'api')->json('POST', '/api/admin/vouchers/', $data);
        $response->assertStatus(422)->assertJsonValidationErrorFor('amount');
    }

    public function testCreateCouponWithProductsAndUsers()
    {
        $coupon = Coupon::factory()->make();
        $data = $coupon->toArray();

        $product = Product::factory()->create();
        $product2 = Product::factory()->create();

        $data['excluded_products'] = [
            $product->getKey(),
        ];
        $data['included_products'] = [
            $product2->getKey(),
        ];
        $data['users'] = [
            $this->user->getKey(),
        ];

        $this->response = $this->actingAs($this->user, 'api')->json('POST', '/api/admin/vouchers/', $data);
        $this->response->assertCreated();

        $id = $this->response->json('data.id');
        /** @var Coupon $couponDb */
        $couponDb = Coupon::find($id);

        $this->response->assertJsonFragment([
            'data' => json_decode(CouponResource::make($couponDb)->toJson(), true)
        ]);

        $this->assertTrue($couponDb->excludedProducts->contains(fn (Product $eProduct) => $eProduct->getKey() === $product->getKey()));
        $this->assertTrue($couponDb->includedProducts->contains(fn (Product $iProduct) => $iProduct->getKey() === $product2->getKey()));
        $this->assertTrue($couponDb->users->contains(fn (User $user) => $user->getKey() === $this->user->getKey()));
    }

    public function testCreateCouponWithProductsAndCategories()
    {
        $coupon = Coupon::factory()->make();
        $data = $coupon->toArray();

        $category = Category::factory()->create();
        $category2 = Category::factory()->create();

        $product = Product::factory()->create();
        $product2 = Product::factory()->create();
        $product3 = Product::factory()->create();
        $product4 = Product::factory()->create();

        $product3->categories()->sync([$category->getKey()]);
        $product4->categories()->sync([$category2->getKey()]);

        $data['included_products'] = [
            $product->getKey(),
        ];
        $data['excluded_products'] = [
            $product2->getKey(),
        ];
        $data['included_categories'] = [
            $category->getKey(),
        ];
        $data['excluded_categories'] = [
            $category2->getKey()
        ];

        $this->response = $this->actingAs($this->user, 'api')->json('POST', '/api/admin/vouchers/', $data);
        $this->response->assertCreated();

        $id = $this->response->json('data.id');
        /** @var Coupon $couponDb */
        $couponDb = Coupon::find($id);

        $this->response->assertJsonFragment([
            'data' => json_decode(CouponResource::make($couponDb)->toJson(), true)
        ]);

        $this->assertTrue($couponDb->includedProducts->contains(fn (Product $iProduct) => $iProduct->getKey() === $product->getKey()));
        $this->assertTrue($couponDb->excludedProducts->contains(fn (Product $eProduct) => $eProduct->getKey() === $product2->getKey()));
        $this->assertTrue($couponDb->includedCategories->contains(fn (Category $iCategory) => $iCategory->getKey() === $category->getKey()));
        $this->assertTrue($couponDb->excludedCategories->contains(fn (Category $eCategory) => $eCategory->getkey() === $category2->getKey()));

        $cartItem = new CartItem([
            'buyable_type' => $product->getMorphClass(),
            'buyable_id' => $product->getKey()
        ]);
        $cartItem2 = new CartItem([
            'buyable_type' => $product2->getMorphClass(),
            'buyable_id' => $product2->getKey()
        ]);
        $cartItem3 = new CartItem([
            'buyable_type' => $product3->getMorphClass(),
            'buyable_id' => $product3->getKey()
        ]);
        $cartItem4 = new CartItem([
            'buyable_type' => $product4->getMorphClass(),
            'buyable_id' => $product4->getKey()
        ]);

        $this->assertTrue(app(CouponServiceContract::class)->cartItemIsIncludedInCoupon($couponDb, $cartItem));
        $this->assertTrue(app(CouponServiceContract::class)->cartItemIsIncludedInCoupon($couponDb, $cartItem3));
        $this->assertTrue(app(CouponServiceContract::class)->cartItemIsExcludedFromCoupon($couponDb, $cartItem2));
        $this->assertTrue(app(CouponServiceContract::class)->cartItemIsExcludedFromCoupon($couponDb, $cartItem4));
    }

    public function testUpdateCoupon()
    {
        $product = Product::factory()->create();
        $product2 = Product::factory()->create();
        $product3 = Product::factory()->create();
        $product4 = Product::factory()->create();

        /** @var Coupon $coupon */
        $coupon = Coupon::factory()->create([
            'name' => 'first',
        ]);
        $coupon->products()->sync([$product->getKey() => ['excluded' => false], $product2->getKey() => ['excluded' => false]]);

        $coupon->refresh();
        $this->assertEquals([$product->getKey(), $product2->getKey()], $coupon->includedProducts->pluck('id')->toArray());

        $coupon2 = Coupon::factory()->make([
            'name' => 'second',
            'code' => 'SOMECODE'
        ]);

        $data = $coupon2->toArray();
        $data['included_products'] = [$product3->getKey(), $product4->getKey()];

        $url =  '/api/admin/vouchers/' . $coupon->getKey();
        $this->response = $this->actingAs($this->user, 'api')->json('PATCH', $url, $data);
        $this->response->assertOk();

        $coupon->refresh();
        $this->assertEquals('second', $coupon->name);
        $this->assertEquals('SOMECODE', $coupon->code);
        $this->assertEquals([$product3->getKey(), $product4->getKey()], $coupon->includedProducts->pluck('id')->toArray());
    }

    public function testListCoupons()
    {
        $coupon = Coupon::factory()->create([
            'name' => 'first',
        ]);
        $coupon2 = Coupon::factory()->create([
            'name' => 'second',
        ]);

        $this->response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/vouchers');
        $this->response->assertOk();

        $this->response->assertJsonCount(2, 'data');
        $this->response->assertJsonFragment([
            'data' => CouponResource::collection([$coupon, $coupon2])->toArray(null)
        ]);
    }

    public function testListCouponsOrderByAmount()
    {
        $coupon = Coupon::factory()->create([
            'name' => 'first',
            'amount' => 30,
        ]);
        $coupon2 = Coupon::factory()->create([
            'name' => 'second',
            'amount' => 10,
        ]);
        $coupon3 = Coupon::factory()->create([
            'name' => 'second',
            'amount' => 20,
        ]);

        $this->response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/vouchers?order_by=amount&order=ASC');

        $this->assertTrue($this->response->json('data.0.amount') === $coupon2->amount);
        $this->assertTrue($this->response->json('data.1.amount') === $coupon3->amount);
        $this->assertTrue($this->response->json('data.2.amount') === $coupon->amount);

        $this->response->assertOk();
        $this->response->assertJsonCount(3, 'data');
        $this->response->assertJsonFragment([
            'data' => CouponResource::collection([$coupon, $coupon2, $coupon3])->toArray(null)
        ]);
    }

    public function testReadCoupons()
    {
        $coupon = Coupon::factory()->create([
            'name' => 'first',
        ]);

        $this->response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/vouchers/' . $coupon->getKey());
        $this->response->assertOk();

        $this->response->assertJsonFragment([
            'data' => CouponResource::make($coupon)->toArray(null)
        ]);
    }

    public function testDeleteCoupon()
    {
        $coupon = Coupon::factory()->create([
            'name' => 'first',
        ]);
        $coupon2 = Coupon::factory()->create([
            'name' => 'second',
        ]);

        $this->response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/vouchers');
        $this->response->assertOk();

        $this->response->assertJsonCount(2, 'data');
        $this->response->assertJsonFragment([
            'data' => CouponResource::collection([$coupon, $coupon2])->toArray(null)
        ]);

        $this->response = $this->actingAs($this->user, 'api')->json('DELETE', '/api/admin/vouchers/' . $coupon2->getKey());
        $this->response->assertOk();

        $this->response = $this->actingAs($this->user, 'api')->json('GET', '/api/admin/vouchers');
        $this->response->assertOk();

        $this->response->assertJsonCount(1, 'data');
        $this->response->assertJsonFragment([
            'data' => CouponResource::collection([$coupon])->toArray(null)
        ]);
    }
}

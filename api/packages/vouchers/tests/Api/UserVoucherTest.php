<?php

namespace EscolaLms\Vouchers\Tests\Api;

use EscolaLms\Cart\Database\Seeders\CartPermissionSeeder;
use EscolaLms\Cart\Models\Product;
use EscolaLms\Core\Enums\UserRole;
use EscolaLms\Payments\Facades\PaymentGateway;
use EscolaLms\Payments\Models\Payment;
use EscolaLms\Vouchers\Database\Seeders\VoucherPermissionsSeeder;
use EscolaLms\Vouchers\Models\Cart;
use EscolaLms\Vouchers\Models\Category;
use EscolaLms\Vouchers\Models\Coupon;
use EscolaLms\Vouchers\Models\CouponCategory;
use EscolaLms\Vouchers\Models\CouponProduct;
use EscolaLms\Vouchers\Models\User;
use EscolaLms\Vouchers\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class UserVoucherTest extends TestCase
{
    use DatabaseTransactions;

    private $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->seed(CartPermissionSeeder::class);
        $this->seed(VoucherPermissionsSeeder::class);

        $this->user = User::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole(UserRole::STUDENT);
    }

    public function testApplyCartPercentVoucherAndPurchase()
    {
        PaymentGateway::fake();
        Notification::fake();

        /** @var Product $product */
        $product = Product::factory()->create([
            'price' => 1000,
            'tax_rate' => 0,
        ]);
        $product2 = Product::factory()->create([
            'price' => 500,
            'tax_rate' => 0,
        ]);

        $user = $this->user;

        $this->response = $this->actingAs($user, 'api')->json('POST', '/api/cart/products/', [
            'id' => $product->getKey(),
        ]);
        $this->response->assertStatus(200);
        $this->response = $this->actingAs($user, 'api')->json('POST', '/api/cart/products/', [
            'id' => $product2->getKey(),
        ]);
        $this->response->assertStatus(200);

        $this->response = $this->actingAs($user, 'api')->json('GET', '/api/cart');
        $this->response->assertOk();

        $cartDataApi = $this->response->json()['data'];

        $this->assertEquals('1500', $cartDataApi['total']);
        $this->assertEquals('0', $cartDataApi['additional_discount']);
        $this->assertEquals('1500', $cartDataApi['total_prediscount']);
        $this->assertNull($cartDataApi['coupon']);

        $coupon = Coupon::factory()->cart_percent()->create();

        $this->response = $this->actingAs($user, 'api')->json('POST', '/api/cart/voucher', ['code' => $coupon->code]);
        $this->response->assertOk();
        $this->response = $this->actingAs($user, 'api')->json('GET', '/api/cart');
        $this->response->assertOk();

        $cartDataApi = $this->response->json()['data'];

        $this->assertEquals('1350', $cartDataApi['total']);
        $this->assertEquals('150', $cartDataApi['additional_discount']);
        $this->assertEquals('1350', $cartDataApi['total_prediscount']);
        $this->assertEquals($coupon->code, $cartDataApi['coupon']);

        $this->response = $this->actingAs($user, 'api')->json('POST', '/api/cart/pay');
        $this->response->assertCreated();
        $this->response = $this->actingAs($user, 'api')->json('GET', '/api/orders');
        $this->response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    [
                        'status' => 'PAID',
                        'total' => 1350,
                        'subtotal' => 1350,
                        'tax' => 0
                    ]
                ]
            ])
            ->assertJsonCount(4)
            ->assertJsonCount(2, 'data.0.items');

        $user->refresh();
        $product->refresh();
        $this->assertTrue($product->getOwnedByUserAttribute($user));

        $order_id = $this->response->json('data.0.id');
        $payment = Payment::where('payable_id', $order_id)->first();
        $this->assertEquals(1350, $payment->amount);
    }

    public function testApplyCartFixedVoucherAndPurchase()
    {
        PaymentGateway::fake();
        Notification::fake();

        $product = Product::factory()->create([
            'price' => 1000
        ]);
        $product2 = Product::factory()->create([
            'price' => 500
        ]);

        $user = $this->user;

        $this->response = $this->actingAs($user, 'api')->json('POST', '/api/cart/products/', [
            'id' => $product->getKey(),
        ]);
        $this->response->assertStatus(200);
        $this->response = $this->actingAs($user, 'api')->json('POST', '/api/cart/products/', [
            'id' => $product2->getKey(),
        ]);
        $this->response->assertStatus(200);

        $this->response = $this->actingAs($user, 'api')->json('GET', '/api/cart');
        $this->response->assertOk();

        $cartDataApi = $this->response->json()['data'];

        $this->assertEquals('1500', $cartDataApi['total']);
        $this->assertEquals('0', $cartDataApi['additional_discount']);
        $this->assertEquals('1500', $cartDataApi['total_prediscount']);
        $this->assertNull($cartDataApi['coupon']);

        $coupon = Coupon::factory()->cart_fixed()->create();

        $this->response = $this->actingAs($user, 'api')->json('POST', '/api/cart/voucher', ['code' => $coupon->code]);
        $this->response->assertOk();

        $this->response = $this->actingAs($user, 'api')->json('GET', '/api/cart');
        $this->response->assertOk();

        $cartDataApi = $this->response->json()['data'];

        $this->assertEquals('500', $cartDataApi['total']);
        $this->assertEquals('1000', $cartDataApi['additional_discount']);
        $this->assertEquals('500', $cartDataApi['total_prediscount']);
        $this->assertEquals($coupon->code, $cartDataApi['coupon']);

        $this->response = $this->actingAs($user, 'api')->json('POST', '/api/cart/pay');
        $this->response->assertCreated();
        $this->response = $this->actingAs($user, 'api')->json('GET', '/api/orders');
        $this->response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    [
                        'status' => 'PAID',
                        'total' => 500,
                        'subtotal' => 500,
                        'tax' => 0
                    ]
                ]
            ])
            ->assertJsonCount(4)
            ->assertJsonCount(2, 'data.0.items');

        $user->refresh();
        $product->refresh();
        $this->assertTrue($product->getOwnedByUserAttribute($user));

        $order_id = $this->response->json('data.0.id');
        $payment = Payment::where('payable_id', $order_id)->first();
        $this->assertEquals(500, $payment->amount);
    }

    public function testApplyProductFixedVoucherAndPurchase()
    {
        PaymentGateway::fake();
        Notification::fake();

        $product = Product::factory()->create([
            'price' => 1000
        ]);
        $product2 = Product::factory()->create([
            'price' => 500
        ]);

        $user = $this->user;

        $this->response = $this->actingAs($user, 'api')->json('POST', '/api/cart/products/', [
            'id' => $product->getKey(),
        ]);
        $this->response->assertStatus(200);
        $this->response = $this->actingAs($user, 'api')->json('POST', '/api/cart/products/', [
            'id' => $product2->getKey(),
        ]);
        $this->response->assertStatus(200);

        $this->response = $this->actingAs($user, 'api')->json('GET', '/api/cart');
        $this->response->assertOk();

        $cartDataApi = $this->response->json()['data'];

        $this->assertEquals('1500', $cartDataApi['total']);
        $this->assertEquals('0', $cartDataApi['additional_discount']);
        $this->assertEquals('1500', $cartDataApi['total_prediscount']);
        $this->assertNull($cartDataApi['coupon']);

        /** @var Coupon $coupon */
        $coupon = Coupon::factory()->product_fixed()->create();
        CouponProduct::create([
            'coupon_id' => $coupon->getKey(),
            'product_id' => $product->getKey(),
            'excluded' => false,
        ]);

        $this->response = $this->actingAs($user, 'api')->json('POST', '/api/cart/voucher', ['code' => $coupon->code]);
        $this->response->assertOk();

        $this->response = $this->actingAs($user, 'api')->json('GET', '/api/cart');
        $this->response->assertOk();

        $cartDataApi = $this->response->json()['data'];

        $this->assertEquals('500', $cartDataApi['total']);
        $this->assertEquals('0', $cartDataApi['additional_discount']);
        $this->assertEquals('500', $cartDataApi['total_prediscount']);
        $this->assertEquals($coupon->code, $cartDataApi['coupon']);

        $this->response = $this->actingAs($user, 'api')->json('POST', '/api/cart/pay');
        $this->response->assertCreated();
        $this->response = $this->actingAs($user, 'api')->json('GET', '/api/orders');
        $this->response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    [
                        'status' => 'PAID',
                        'total' => 500,
                        'subtotal' => 500,
                        'tax' => 0
                    ]
                ]
            ])
            ->assertJsonCount(4)
            ->assertJsonCount(2, 'data.0.items');

        $user->refresh();
        $product->refresh();
        $this->assertTrue($product->getOwnedByUserAttribute($user));

        $order_id = $this->response->json('data.0.id');
        $payment = Payment::where('payable_id', $order_id)->first();
        $this->assertEquals(500, $payment->amount);
    }

    public function testApplyProductPercentVoucherAndPurchase()
    {
        PaymentGateway::fake();
        Notification::fake();

        $product = Product::factory()->create([
            'price' => 1000
        ]);
        $product2 = Product::factory()->create([
            'price' => 500
        ]);

        $user = $this->user;

        $this->response = $this->actingAs($user, 'api')->json('POST', '/api/cart/products/', [
            'id' => $product->getKey(),
        ]);
        $this->response->assertStatus(200);
        $this->response = $this->actingAs($user, 'api')->json('POST', '/api/cart/products/', [
            'id' => $product2->getKey(),
        ]);
        $this->response->assertStatus(200);

        $this->response = $this->actingAs($user, 'api')->json('GET', '/api/cart');
        $this->response->assertOk();

        $cartDataApi = $this->response->json()['data'];

        $this->assertEquals('1500', $cartDataApi['total']);
        $this->assertEquals('0', $cartDataApi['additional_discount']);
        $this->assertEquals('1500', $cartDataApi['total_prediscount']);
        $this->assertNull($cartDataApi['coupon']);

        /** @var Coupon $coupon */
        $coupon = Coupon::factory()->product_percent()->create();
        CouponProduct::create([
            'coupon_id' => $coupon->getKey(),
            'product_id' => $product->getKey(),
            'excluded' => false,
        ]);

        $this->response = $this->actingAs($user, 'api')->json('POST', '/api/cart/voucher', ['code' => $coupon->code]);
        $this->response->assertOk();

        $this->response = $this->actingAs($user, 'api')->json('GET', '/api/cart');
        $this->response->assertOk();

        $cartDataApi = $this->response->json()['data'];

        $this->assertEquals('1400', $cartDataApi['total']);
        $this->assertEquals('0', $cartDataApi['additional_discount']);
        $this->assertEquals('1400', $cartDataApi['total_prediscount']);
        $this->assertEquals($coupon->code, $cartDataApi['coupon']);

        $this->response = $this->actingAs($user, 'api')->json('POST', '/api/cart/pay');
        $this->response->assertCreated();
        $this->response = $this->actingAs($user, 'api')->json('GET', '/api/orders');
        $this->response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    [
                        'status' => 'PAID',
                        'total' => 1400,
                        'subtotal' => 1400,
                        'tax' => 0
                    ]
                ]
            ])
            ->assertJsonCount(4)
            ->assertJsonCount(2, 'data.0.items');

        $user->refresh();
        $product->refresh();
        $this->assertTrue($product->getOwnedByUserAttribute($user));

        $order_id = $this->response->json('data.0.id');
        $payment = Payment::where('payable_id', $order_id)->first();
        $this->assertEquals(1400, $payment->amount);
    }

    public function testApplyInactiveCoupon()
    {
        Notification::fake();

        $product = Product::factory()->create([
            'price' => 1000
        ]);

        $user = $this->user;

        $this->addProductToUserCart($user, $product);

        /** @var Coupon $coupon */
        $coupon = Coupon::factory()->product_percent()->create(['active_to' => Carbon::now()->subDay(),]);
        CouponProduct::create([
            'coupon_id' => $coupon->getKey(),
            'product_id' => $product->getKey(),
            'excluded' => false,
        ]);

        $this->response = $this->actingAs($user, 'api')->json('POST', '/api/cart/voucher', ['code' => $coupon->code]);
        $this->response->assertStatus(400);
        $this->response->assertJsonFragment([
            'message' => __('Coupon is no longer active'),
            'data' => [
                'code' => $coupon->code,
            ],
        ]);
    }

    public function testApplyWrongCoupon()
    {
        Notification::fake();

        $product = Product::factory()->create([
            'price' => 1000
        ]);

        $user = $this->user;

        $this->addProductToUserCart($user, $product);

        /** @var Coupon $coupon */
        $coupon = Coupon::factory()->product_percent()->create();
        CouponProduct::create([
            'coupon_id' => $coupon->getKey(),
            'product_id' => $product->getKey(),
            'excluded' => true,
        ]);

        $this->response = $this->actingAs($user, 'api')->json('POST', '/api/cart/voucher', ['code' => $coupon->code]);
        $this->response->assertStatus(400);
        $this->response->assertJsonFragment([
            'message' => __('Coupon can not be applied to this Cart'),
            'data' => [
                'code' => $coupon->code
            ],
        ]);
    }


    public function testUnapplyVoucher()
    {
        PaymentGateway::fake();
        Notification::fake();

        /** @var Product $product */
        $product = Product::factory()->create([
            'price' => 1000,
            'tax_rate' => 0,
        ]);
        $product2 = Product::factory()->create([
            'price' => 500,
            'tax_rate' => 0,
        ]);

        $user = $this->user;

        $this->response = $this->actingAs($user, 'api')->json('POST', '/api/cart/products/', [
            'id' => $product->getKey(),
        ]);
        $this->response->assertStatus(200);
        $this->response = $this->actingAs($user, 'api')->json('POST', '/api/cart/products/', [
            'id' => $product2->getKey(),
        ]);
        $this->response->assertStatus(200);

        $this->response = $this->actingAs($user, 'api')->json('GET', '/api/cart');
        $this->response->assertOk();

        $cartDataApi = $this->response->json()['data'];

        $this->assertEquals('1500', $cartDataApi['total']);
        $this->assertEquals('0', $cartDataApi['additional_discount']);
        $this->assertEquals('1500', $cartDataApi['total_prediscount']);
        $this->assertNull($cartDataApi['coupon']);

        $coupon = Coupon::factory()->cart_percent()->create();

        $this->response = $this->actingAs($user, 'api')->json('POST', '/api/cart/voucher', ['code' => $coupon->code]);
        $this->response->assertOk();
        $this->response = $this->actingAs($user, 'api')->json('GET', '/api/cart');
        $this->response->assertOk();

        $cartDataApi = $this->response->json()['data'];

        $this->assertEquals('1350', $cartDataApi['total']);
        $this->assertEquals('150', $cartDataApi['additional_discount']);
        $this->assertEquals('1350', $cartDataApi['total_prediscount']);
        $this->assertEquals($coupon->code, $cartDataApi['coupon']);

        $this->response = $this->actingAs($user, 'api')->json('DELETE', '/api/cart/voucher');
        $this->response->assertOk();
        $this->response = $this->actingAs($user, 'api')->json('GET', '/api/cart');
        $this->response->assertOk();

        $cartDataApi = $this->response->json()['data'];

        $this->assertEquals('1500', $cartDataApi['total']);
        $this->assertEquals('0', $cartDataApi['additional_discount']);
        $this->assertEquals('1500', $cartDataApi['total_prediscount']);
        $this->assertNull($cartDataApi['coupon']);
    }

    public function testApplyCouponProductExcludePromotionsTrue(): void
    {
        Notification::fake();

        $product = Product::factory()->create([
            'price' => 1000,
            'price_old' => 1500,
        ]);

        $user = $this->user;

        $this->addProductToUserCart($user, $product);

        /** @var Coupon $coupon */
        $coupon = Coupon::factory()->product_percent()->create([
            'exclude_promotions' => true,
        ]);
        CouponProduct::create([
            'coupon_id' => $coupon->getKey(),
            'product_id' => $product->getKey(),
            'excluded' => false,
        ]);

        $this->response = $this->actingAs($user, 'api')
            ->json('POST', '/api/cart/voucher', ['code' => $coupon->code]);
        $this->response->assertStatus(400);
        $this->response->assertJsonFragment([
            'message' => __('Coupon can not be applied to this Cart'),
            'data' => [
                'code' => $coupon->code
            ],
        ]);
    }

    public function testApplyCouponProductExcludePromotionsFalse(): void
    {
        Notification::fake();

        $product = Product::factory()->create([
            'price' => 1000,
            'price_old' => 1500,
        ]);

        $user = $this->user;

        $this->addProductToUserCart($user, $product);

        /** @var Coupon $coupon */
        $coupon = Coupon::factory()->product_percent()->create([
            'exclude_promotions' => false,
        ]);
        CouponProduct::create([
            'coupon_id' => $coupon->getKey(),
            'product_id' => $product->getKey(),
            'excluded' => false,
        ]);

        $this->response = $this->actingAs($user, 'api')
            ->json('POST', '/api/cart/voucher', ['code' => $coupon->code]);
        $this->response->assertOk();
    }

    public function testApplyCouponCartExcludePromotionsTrue(): void
    {
        Notification::fake();

        $product = Product::factory()->create([
            'price' => 1000,
            'price_old' => 1500,
        ]);

        $user = $this->user;

        $this->addProductToUserCart($user, $product);

        /** @var Coupon $coupon */
        $coupon = Coupon::factory()->cart_percent()->create([
            'exclude_promotions' => true,
        ]);

        $this->response = $this->actingAs($user, 'api')
            ->json('POST', '/api/cart/voucher', ['code' => $coupon->code]);
        $this->response->assertStatus(400);
        $this->response->assertJsonFragment([
            'message' => __('Coupon can not be applied to this Cart'),
            'data' => [
                'code' => $coupon->code
            ],
        ]);
    }

    public function testApplyCouponCartExcludePromotionsTrueExcludedProduct(): void
    {
        Notification::fake();

        $product = Product::factory()->create([
            'price' => 1000,
        ]);

        $user = $this->user;

        $this->addProductToUserCart($user, $product);

        /** @var Coupon $coupon */
        $coupon = Coupon::factory()->cart_percent()->create([
            'exclude_promotions' => true,
        ]);

        CouponProduct::create([
            'coupon_id' => $coupon->getKey(),
            'product_id' => $product->getKey(),
            'excluded' => true,
        ]);

        $this->response = $this->actingAs($user, 'api')
            ->json('POST', '/api/cart/voucher', ['code' => $coupon->code]);
        $this->response->assertStatus(400);
        $this->response->assertJsonFragment([
            'message' => __('Coupon can not be applied to this Cart'),
            'data' => [
                'code' => $coupon->code
            ],
        ]);
    }

    public function testApplyCouponCartExcludePromotionsTrueExcludedProductOnPromotion(): void
    {
        Notification::fake();

        $product = Product::factory()->create([
            'price' => 1000,
            'price_old' => 1500,
        ]);

        $user = $this->user;

        $this->addProductToUserCart($user, $product);

        /** @var Coupon $coupon */
        $coupon = Coupon::factory()->cart_percent()->create([
            'exclude_promotions' => true,
        ]);

        CouponProduct::create([
            'coupon_id' => $coupon->getKey(),
            'product_id' => $product->getKey(),
            'excluded' => true,
        ]);

        $this->response = $this->actingAs($user, 'api')
            ->json('POST', '/api/cart/voucher', ['code' => $coupon->code]);
        $this->response->assertStatus(400);
        $this->response->assertJsonFragment([
            'message' => __('Coupon can not be applied to this Cart'),
            'data' => [
                'code' => $coupon->code
            ],
        ]);
    }

    public function testApplyCouponCartExcludePromotionsFalse(): void
    {
        Notification::fake();

        $product = Product::factory()->create([
            'price' => 1000,
            'price_old' => 1500,
        ]);

        $user = $this->user;

        $this->addProductToUserCart($user, $product);

        /** @var Coupon $coupon */
        $coupon = Coupon::factory()->cart_percent()->create([
            'exclude_promotions' => false,
        ]);

        $this->response = $this->actingAs($user, 'api')
            ->json('POST', '/api/cart/voucher', ['code' => $coupon->code]);
        $this->response->assertOk();
    }

    public function testApplyCouponProduct100Percent(): void
    {
        Notification::fake();

        $product = Product::factory()->create([
            'price' => 1000,
        ]);

        $user = $this->user;

        $this->addProductToUserCart($user, $product);

        /** @var Coupon $coupon */
        $coupon = Coupon::factory()->product_percent()->create([
            'exclude_promotions' => true,
            'amount' => 100,
        ]);

        CouponProduct::create([
            'coupon_id' => $coupon->getKey(),
            'product_id' => $product->getKey(),
            'excluded' => false,
        ]);

        $this->response = $this->actingAs($user, 'api')
            ->json('POST', '/api/cart/voucher', ['code' => $coupon->code]);
        $this->response->assertOk();

        $this->response = $this->actingAs($user, 'api')->json('GET', '/api/cart');
        $this->response->assertOk();

        $cartDataApi = $this->response->json()['data'];

        $this->assertEquals('0', $cartDataApi['total']);
        $this->assertEquals('0', $cartDataApi['additional_discount']);
        $this->assertEquals('0', $cartDataApi['total_prediscount']);
        $this->assertEquals('0', $cartDataApi['items'][0]['price']);
        $this->assertEquals('0', $cartDataApi['items'][0]['total']);
        $this->assertEquals('1000', $cartDataApi['items'][0]['discount']);
    }

    public function testApplyCouponCart100Percent(): void
    {
        Notification::fake();

        $product = Product::factory()->create([
            'price' => 1000,
        ]);

        $user = $this->user;

        $this->addProductToUserCart($user, $product);

        /** @var Coupon $coupon */
        $coupon = Coupon::factory()->cart_percent()->create([
            'exclude_promotions' => false,
            'amount' => 100,
        ]);

        $this->response = $this->actingAs($user, 'api')
            ->json('POST', '/api/cart/voucher', ['code' => $coupon->code]);
        $this->response->assertOk();

        $this->response = $this->actingAs($user, 'api')->json('GET', '/api/cart');
        $this->response->assertOk();

        $cartDataApi = $this->response->json()['data'];

        $this->assertEquals('0', $cartDataApi['total']);
        $this->assertEquals('1000', $cartDataApi['additional_discount']);
        $this->assertEquals('0', $cartDataApi['total_prediscount']);
        $this->assertEquals('0', $cartDataApi['items'][0]['price']);
        $this->assertEquals('0', $cartDataApi['items'][0]['total']);
        $this->assertEquals('1000', $cartDataApi['items'][0]['discount']);
    }

    public function testApplyCouponGreaterAmountThanProductPrice(): void
    {
        Notification::fake();

        $product = Product::factory()->create([
            'price' => 1000,
        ]);

        $user = $this->user;

        $this->addProductToUserCart($user, $product);

        /** @var Coupon $coupon */
        $coupon = Coupon::factory()->product_fixed()->create([
            'exclude_promotions' => false,
            'amount' => 1500,
        ]);
        CouponProduct::create([
            'coupon_id' => $coupon->getKey(),
            'product_id' => $product->getKey(),
            'excluded' => false,
        ]);

        $this->response = $this->actingAs($user, 'api')
            ->json('POST', '/api/cart/voucher', ['code' => $coupon->code]);
        $this->response->assertOk();

        $this->response = $this->actingAs($user, 'api')->json('GET', '/api/cart');
        $this->response->assertOk();

        $cartDataApi = $this->response->json()['data'];

        $this->assertEquals('0', $cartDataApi['total']);
        $this->assertEquals('0', $cartDataApi['additional_discount']);
        $this->assertEquals('0', $cartDataApi['total_prediscount']);
        $this->assertEquals('0', $cartDataApi['items'][0]['price']);
        $this->assertEquals('0', $cartDataApi['items'][0]['total']);
        $this->assertEquals('1000', $cartDataApi['items'][0]['discount']);
    }

    public function testApplyCouponGreaterAmountThanCartTotal(): void
    {
        Notification::fake();

        $product = Product::factory()->create([
            'price' => 1000,
        ]);

        $user = $this->user;

        $this->addProductToUserCart($user, $product);

        /** @var Coupon $coupon */
        $coupon = Coupon::factory()->cart_fixed()->create([
            'exclude_promotions' => false,
            'amount' => 1500,
        ]);

        $this->response = $this->actingAs($user, 'api')
            ->json('POST', '/api/cart/voucher', ['code' => $coupon->code]);
        $this->response->assertOk();

        $this->response = $this->actingAs($user, 'api')->json('GET', '/api/cart');
        $this->response->assertOk();

        $cartDataApi = $this->response->json()['data'];

        $this->assertEquals('0', $cartDataApi['total']);
        $this->assertEquals('1000', $cartDataApi['additional_discount']);
        $this->assertEquals('0', $cartDataApi['total_prediscount']);
        $this->assertEquals('0', $cartDataApi['items'][0]['price']);
        $this->assertEquals('0', $cartDataApi['items'][0]['total']);
        $this->assertEquals('1000', $cartDataApi['items'][0]['discount']);
    }

    public function testApplyCouponGreaterAmountThanCartTotalWithTax(): void
    {
        Notification::fake();

        $product = Product::factory()->create([
            'price' => 1000,
            'tax_rate' => 23.0,
        ]);

        $user = $this->user;

        $this->addProductToUserCart($user, $product);

        /** @var Coupon $coupon */
        $coupon = Coupon::factory()->cart_fixed()->create([
            'exclude_promotions' => false,
            'amount' => 1500,
        ]);

        $this->response = $this->actingAs($user, 'api')
            ->json('POST', '/api/cart/voucher', ['code' => $coupon->code]);
        $this->response->assertOk();

        $this->response = $this->actingAs($user, 'api')->json('GET', '/api/cart');
        $this->response->assertOk();

        $cartDataApi = $this->response->json()['data'];

        $this->assertEquals('0', $cartDataApi['total']);
        $this->assertEquals('1000', $cartDataApi['additional_discount']);
        $this->assertEquals('0', $cartDataApi['total_prediscount']);
        $this->assertEquals('0', $cartDataApi['items'][0]['price']);
        $this->assertEquals('0', $cartDataApi['items'][0]['total']);
        $this->assertEquals('1000', $cartDataApi['items'][0]['discount']);
    }

    public function testApplyCouponProductFixedWithTax(): void
    {
        Notification::fake();

        $product = Product::factory()->create([
            'price' => 1000,
            'tax_rate' => 23.0,
        ]);

        $user = $this->user;

        $this->addProductToUserCart($user, $product);

        /** @var Coupon $coupon */
        $coupon = Coupon::factory()->product_fixed()->create([
            'exclude_promotions' => false,
            'amount' => 100,
        ]);

        CouponProduct::create([
            'coupon_id' => $coupon->getKey(),
            'product_id' => $product->getKey(),
            'excluded' => false,
        ]);

        $this->response = $this->actingAs($user, 'api')
            ->json('POST', '/api/cart/voucher', ['code' => $coupon->code]);
        $this->response->assertOk();

        $this->response = $this->actingAs($user, 'api')->json('GET', '/api/cart');
        $this->response->assertOk();

        $cartDataApi = $this->response->json()['data'];

        $this->assertEquals('919', $cartDataApi['total']);
        $this->assertEquals('211', $cartDataApi['tax']);
        $this->assertEquals('0', $cartDataApi['additional_discount']);
        $this->assertEquals('919', $cartDataApi['total_prediscount']);
        $this->assertEquals('919', $cartDataApi['items'][0]['price']);
        $this->assertEquals('919', $cartDataApi['items'][0]['total']);
        $this->assertEquals('1130', $cartDataApi['items'][0]['total_with_tax']);
        $this->assertEquals('81', $cartDataApi['items'][0]['discount']);
    }

    public function testApplyCouponCartFixedWithTax(): void
    {
        Notification::fake();

        $product = Product::factory()->create([
            'price' => 1000,
            'tax_rate' => 23.0,
        ]);

        $user = $this->user;

        $this->addProductToUserCart($user, $product);

        /** @var Coupon $coupon */
        $coupon = Coupon::factory()->cart_fixed()->create([
            'exclude_promotions' => false,
            'amount' => 100,
        ]);

        $this->response = $this->actingAs($user, 'api')
            ->json('POST', '/api/cart/voucher', ['code' => $coupon->code]);
        $this->response->assertOk();

        $this->response = $this->actingAs($user, 'api')->json('GET', '/api/cart');
        $this->response->assertOk();

        $cartDataApi = $this->response->json()['data'];

        $this->assertEquals('919', $cartDataApi['total']);
        $this->assertEquals('211', $cartDataApi['tax']);
        $this->assertEquals('81', $cartDataApi['additional_discount']);
        $this->assertEquals('919', $cartDataApi['total_prediscount']);
        $this->assertEquals('919', $cartDataApi['items'][0]['price']);
        $this->assertEquals('919', $cartDataApi['items'][0]['total']);
        $this->assertEquals('1130', $cartDataApi['items'][0]['total_with_tax']);
        $this->assertEquals('81', $cartDataApi['items'][0]['discount']);
    }

    public function testApplyCouponProductNotInCategory(): void
    {
        Notification::fake();

        $product = Product::factory()->create([
            'price' => 1000,
        ]);

        $category = Category::factory()->create();

        $user = $this->user;

        $this->addProductToUserCart($user, $product);

        /** @var Coupon $coupon */
        $coupon = Coupon::factory()->cart_fixed()->create([
            'exclude_promotions' => true,
        ]);
        CouponCategory::create([
            'coupon_id' => $coupon->getKey(),
            'category_id' => $category->getKey(),
            'excluded' => false,
        ]);

        $this->response = $this->actingAs($user, 'api')
            ->json('POST', '/api/cart/voucher', ['code' => $coupon->code]);
        $this->response->assertJsonFragment([
            'message' => __('Coupon can not be applied to this Cart'),
            'data' => [
                'code' => $coupon->code
            ],
        ]);
    }

    public function testApplyCouponProductNotInCategoryAndInPromotion(): void
    {
        Notification::fake();

        $product = Product::factory()->create([
            'price' => 1000,
            'price_old' => 1500,
        ]);

        $category = Category::factory()->create();

        $user = $this->user;

        $this->addProductToUserCart($user, $product);

        /** @var Coupon $coupon */
        $coupon = Coupon::factory()->cart_fixed()->create([
            'exclude_promotions' => true,
        ]);
        CouponCategory::create([
            'coupon_id' => $coupon->getKey(),
            'category_id' => $category->getKey(),
            'excluded' => false,
        ]);

        $this->response = $this->actingAs($user, 'api')
            ->json('POST', '/api/cart/voucher', ['code' => $coupon->code]);
        $this->response->assertStatus(400);
        $this->response->assertJsonFragment([
            'message' => __('Coupon can not be applied to this Cart'),
            'data' => [
                'code' => $coupon->code
            ],
        ]);
    }

    public function testApplyCouponProductNotInExcludedCategory(): void
    {
        Notification::fake();

        $product = Product::factory()->create([
            'price' => 1000,
        ]);

        $category = Category::factory()->create();

        $user = $this->user;

        $this->addProductToUserCart($user, $product);

        /** @var Coupon $coupon */
        $coupon = Coupon::factory()->cart_fixed()->create([
            'exclude_promotions' => true,
        ]);
        CouponCategory::create([
            'coupon_id' => $coupon->getKey(),
            'category_id' => $category->getKey(),
            'excluded' => true,
        ]);

        $this->response = $this->actingAs($user, 'api')
            ->json('POST', '/api/cart/voucher', ['code' => $coupon->code]);
        $this->response->assertOk();
    }

    public function testApplyCouponProductNotInExcludedCategoryAndInPromotion(): void
    {
        Notification::fake();

        $product = Product::factory()->create([
            'price' => 1000,
            'price_old' => 1500,
        ]);

        $category = Category::factory()->create();

        $user = $this->user;

        $this->addProductToUserCart($user, $product);

        /** @var Coupon $coupon */
        $coupon = Coupon::factory()->cart_fixed()->create([
            'exclude_promotions' => true,
        ]);
        CouponCategory::create([
            'coupon_id' => $coupon->getKey(),
            'category_id' => $category->getKey(),
            'excluded' => true,
        ]);

        $this->response = $this->actingAs($user, 'api')
            ->json('POST', '/api/cart/voucher', ['code' => $coupon->code]);
        $this->response->assertStatus(400);
        $this->response->assertJsonFragment([
            'message' => __('Coupon can not be applied to this Cart'),
            'data' => [
                'code' => $coupon->code
            ],
        ]);
    }

    public function testApplyCouponCartTotalGreaterThanMaxPrice(): void
    {
        Notification::fake();

        $product = Product::factory()->create([
            'price' => 1000,
            'price_old' => 1500,
        ]);

        $user = $this->user;

        $this->addProductToUserCart($user, $product);

        /** @var Coupon $coupon */
        $coupon = Coupon::factory()->cart_fixed()->create([
            'exclude_promotions' => false,
            'max_cart_price' => 100,
        ]);

        $this->response = $this->actingAs($user, 'api')
            ->json('POST', '/api/cart/voucher', ['code' => $coupon->code]);
        $this->response->assertStatus(400);
        $this->response->assertJsonFragment([
            'message' => __('Coupon can not be applied to this Cart'),
            'data' => [
                'code' => $coupon->code
            ],
        ]);
    }

    public function testApplyCouponCartTotalWithOnlyFreeProduct(): void
    {
        Notification::fake();

        $product = Product::factory()->create([
            'price' => 0,
        ]);

        $user = $this->user;

        $this->addProductToUserCart($user, $product);

        /** @var Coupon $coupon */
        $coupon = Coupon::factory()->cart_fixed()->create();

        $this->response = $this->actingAs($user, 'api')
            ->json('POST', '/api/cart/voucher', ['code' => $coupon->code]);
        $this->response->assertOk();

        $this->response = $this->actingAs($user, 'api')->json('GET', '/api/cart');
        $this->response->assertOk();
    }

    public function testApplyCouponCartTotalMaxPrice(): void
    {
        Notification::fake();

        $product = Product::factory()->create([
            'price' => 1000,
            'price_old' => 1500,
        ]);

        $user = $this->user;

        $this->addProductToUserCart($user, $product);

        /** @var Coupon $coupon */
        $coupon = Coupon::factory()->cart_fixed()->create([
            'exclude_promotions' => false,
            'max_cart_price' => 1000,
        ]);

        $this->response = $this->actingAs($user, 'api')
            ->json('POST', '/api/cart/voucher', ['code' => $coupon->code]);
        $this->response->assertOk();
    }

    private function addProductToUserCart($user, $product): void
    {
        $this->response = $this->actingAs($user, 'api')->json('POST', '/api/cart/products/', [
            'id' => $product->getKey(),
        ]);
        $this->response->assertStatus(200);

        $this->response = $this->actingAs($user, 'api')->json('GET', '/api/cart');
        $this->response->assertOk();
    }
}

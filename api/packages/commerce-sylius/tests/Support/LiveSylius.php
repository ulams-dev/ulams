<?php

namespace Ulams\CommerceSylius\Tests\Support;

use Illuminate\Support\Facades\Http;
use Ulams\CommerceSylius\Http\SyliusClient;

/**
 * A real Sylius 2.x service for tests (compose profile `commerce`, or any instance). Set `COMMERCE_SYLIUS_LIVE_URL`,
 * `COMMERCE_SYLIUS_LIVE_ADMIN_EMAIL` and `COMMERCE_SYLIUS_LIVE_ADMIN_PASSWORD` to run the live suites; without them
 * they are skipped. Each test run works in a channel of its own (code `lt<random>`) so runs never see each other's data,
 * and it only ever creates data: nothing is deleted, so point it at a throwaway database.
 *
 * Buying goes through the Shop API exactly as the storefront does (cart, items, billing address, payment, complete);
 * paying and refunding go through the Admin API payment endpoints, as staff do.
 */
final class LiveSylius
{
    private static ?self $instance = null;

    public readonly SyliusClient $admin;
    public string $channel;
    private int $counter = 0;

    private function __construct(public readonly string $url, string $email, string $password)
    {
        $this->admin = new SyliusClient($url, $email, $password);
        $this->channel = 'lt' . bin2hex(random_bytes(4));
    }

    public static function available(): bool
    {
        return (string) getenv('COMMERCE_SYLIUS_LIVE_URL') !== '' && (string) getenv('COMMERCE_SYLIUS_LIVE_ADMIN_PASSWORD') !== '';
    }

    public static function get(): self
    {
        return self::$instance ??= tap(new self((string) getenv('COMMERCE_SYLIUS_LIVE_URL'), (string) getenv('COMMERCE_SYLIUS_LIVE_ADMIN_EMAIL'), (string) getenv('COMMERCE_SYLIUS_LIVE_ADMIN_PASSWORD')), fn (self $s) => $s->provisionChannel());
    }

    /** @return array<string,string> config for the adapter under test */
    public function config(): array
    {
        return [
            'commerce-sylius.url' => $this->url,
            'commerce-sylius.admin.email' => (string) getenv('COMMERCE_SYLIUS_LIVE_ADMIN_EMAIL'),
            'commerce-sylius.admin.password' => (string) getenv('COMMERCE_SYLIUS_LIVE_ADMIN_PASSWORD'),
            'commerce.channel' => $this->channel,
        ];
    }

    private function provisionChannel(): void
    {
        $a = '/api/v2/admin';
        foreach ([['/currencies', ['code' => 'EUR']], ['/locales', ['code' => 'en_US']], ['/countries', ['code' => 'PL', 'enabled' => true]]] as [$path, $body]) {
            $code = $body['code'];
            if ($this->admin->get("{$a}{$path}/{$code}") === null) {
                $this->admin->post("{$a}{$path}", $body);
            }
        }
        $this->admin->post("{$a}/channels", [
            'code' => $this->channel, 'name' => "Live test {$this->channel}", 'enabled' => true, 'hostname' => "{$this->channel}.localhost",
            'baseCurrency' => "{$a}/currencies/EUR", 'currencies' => ["{$a}/currencies/EUR"],
            'defaultLocale' => "{$a}/locales/en_US", 'locales' => ["{$a}/locales/en_US"], 'countries' => ["{$a}/countries/PL"],
            'taxCalculationStrategy' => 'order_items_based', 'skippingShippingStepAllowed' => true, 'shippingAddressInCheckoutRequired' => false,
        ]);
        $method = "offline-{$this->channel}";
        $this->admin->post("{$a}/payment-methods", [
            'code' => $method, 'enabled' => true, 'position' => 0, 'channels' => ["{$a}/channels/{$this->channel}"],
            'gatewayConfig' => ['factoryName' => 'offline', 'gatewayName' => $method, 'config' => new \stdClass()],
            'translations' => ['en_US' => ['name' => 'Offline (tests)']],
        ]);
    }

    /** A customer of the channel linked to an LMS user the way the LMS links them (`<channel>:<id>`), able to sign in to the shop. @return array{id:string,email:string,password:string} */
    public function customer(int $lmsUserId, bool $linked = true): array
    {
        $email = "buyer{$lmsUserId}-" . ++$this->counter . "-{$this->channel}@example.test";
        $password = bin2hex(random_bytes(10));
        $customer = $this->admin->post('/api/v2/admin/customers', [
            'email' => $email, 'firstName' => 'Test', 'lastName' => "Buyer {$lmsUserId}",
            'user' => ['plainPassword' => $password, 'enabled' => true, 'verified' => true],
        ] + ($linked ? ['ulamsUserRef' => "{$this->channel}:{$lmsUserId}"] : []));

        return ['id' => (string) $customer['id'], 'email' => $email, 'password' => $password];
    }

    /** Buys one unit of a variant through the Shop API and completes the checkout. @return array{token:string,payment:string} */
    public function buy(array $customer, string $variantCode): array
    {
        $shop = fn () => Http::baseUrl($this->url)->withHeaders(['Host' => "{$this->channel}.localhost"])->acceptJson()->timeout(30);
        $token = $shop()->post('/api/v2/shop/customers/token', ['email' => $customer['email'], 'password' => $customer['password']])->throw()->json('token');
        $as = fn () => $shop()->withToken($token)->accept('application/ld+json');
        $order = $as()->withHeaders(['Content-Type' => 'application/ld+json'])->post('/api/v2/shop/orders', ['localeCode' => 'en_US'])->throw()->json('tokenValue');
        $as()->withHeaders(['Content-Type' => 'application/ld+json'])->post("/api/v2/shop/orders/{$order}/items", ['productVariant' => "/api/v2/shop/product-variants/{$variantCode}", 'quantity' => 1])->throw();
        $as()->withHeaders(['Content-Type' => 'application/ld+json'])->put("/api/v2/shop/orders/{$order}", ['billingAddress' => [
            'firstName' => 'Test', 'lastName' => 'Buyer', 'street' => 'Main 1', 'city' => 'Warsaw', 'postcode' => '00-001', 'countryCode' => 'PL',
        ]])->throw();
        $cart = $as()->get("/api/v2/shop/orders/{$order}")->throw()->json();
        $payment = (string) $cart['payments'][0]['id'];
        $as()->withHeaders(['Content-Type' => 'application/merge-patch+json'])->patch("/api/v2/shop/orders/{$order}/payments/{$payment}", ['paymentMethod' => '/api/v2/shop/payment-methods/offline-' . $this->channel])->throw();
        $as()->withHeaders(['Content-Type' => 'application/merge-patch+json'])->patch("/api/v2/shop/orders/{$order}/complete", new \stdClass())->throw();

        return ['token' => $order, 'payment' => $payment];
    }

    public function pay(string $paymentId): void
    {
        $this->admin->patch("/api/v2/admin/payments/{$paymentId}/complete");
    }

    public function refund(string $paymentId): void
    {
        $this->admin->patch("/api/v2/admin/payments/{$paymentId}/refund");
    }

    /** Buys and pays in one go. @return array{token:string,payment:string} */
    public function paidOrder(int $lmsUserId, string $variantCode, bool $linked = true): array
    {
        $order = $this->buy($this->customer($lmsUserId, $linked), $variantCode);
        $this->pay($order['payment']);

        return $order;
    }
}

<?php

namespace EscolaLms\TemplatesEmail\Tests\Api;

use EscolaLms\Cart\Database\Seeders\CartPermissionSeeder;
use EscolaLms\Cart\Events\ProductAttached;
use EscolaLms\Cart\Facades\Shop;
use EscolaLms\Cart\Models\Product;
use EscolaLms\Cart\Models\ProductProductable;
use EscolaLms\Cart\Models\User;
use EscolaLms\Cart\Tests\Mocks\ExampleProductable;
use EscolaLms\Cart\Tests\Mocks\ExampleProductableMigration;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Templates\Listeners\TemplateEventListener;
use EscolaLms\TemplatesEmail\Core\EmailMailable;
use EscolaLms\TemplatesEmail\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

class CartTest extends TestCase
{
    use CreatesUsers, WithoutMiddleware, DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();

        if (!class_exists(\EscolaLms\Cart\EscolaLmsCartServiceProvider::class)) {
            $this->markTestSkipped('Courses package not installed');
        }

        $this->seed(CartPermissionSeeder::class);
        $this->admin = $this->makeAdmin();
        Shop::registerProductableClass(ExampleProductable::class);
    }

    public function testProductAttachedNotification(): void
    {
        Event::fake([ProductAttached::class]);
        Mail::fake();

        $product = Product::factory()->create();
        $productable = ExampleProductable::factory()->create();
        $product->productables()->save(new ProductProductable([
            'productable_type' => $productable->getMorphClass(),
            'productable_id' => $productable->getKey()
        ]));

        $student = $this->makeStudent();
        $this->actingAs($this->admin, 'api')->postJson("api/admin/products/{$product->getKey()}/attach", [
            'user_id' => $student->getKey(),
        ])->assertOk();

        Event::assertDispatched(ProductAttached::class, function (ProductAttached $event) use ($student, $product) {
            $this->assertEquals($student->getKey(), $event->getUser()->getKey());
            $this->assertEquals($product->getKey(), $event->getProduct()->getKey());

            return true;
        });

        $listener = app(TemplateEventListener::class);
        $listener->handle(new ProductAttached($product, $student));

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($student, $product, $productable) {
            $this->assertEquals(__('You have been assigned to :product_name', ['product_name' => $product->name]), $mailable->subject);
            $this->assertStringContainsString($productable->name, $mailable->getHtml());
            $this->assertTrue($mailable->hasTo($student->email));

            return true;
        });
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('auth.providers.users.model', User::class);
        ExampleProductableMigration::run();
    }
}

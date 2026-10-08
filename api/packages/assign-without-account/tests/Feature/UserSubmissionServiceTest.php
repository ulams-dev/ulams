<?php

namespace EscolaLms\AssignWithoutAccount\Tests\Feature;

use EscolaLms\AssignWithoutAccount\Dto\UserSubmissionDto;
use EscolaLms\AssignWithoutAccount\Enums\UserSubmissionStatusEnum;
use EscolaLms\AssignWithoutAccount\Events\AssignToProduct;
use EscolaLms\AssignWithoutAccount\Events\AssignToProductable;
use EscolaLms\AssignWithoutAccount\Services\Contracts\UserSubmissionServiceContract;
use EscolaLms\AssignWithoutAccount\Tests\TestCase;
use EscolaLms\Cart\Facades\Shop;
use EscolaLms\Cart\Models\Product;
use EscolaLms\Cart\Tests\Mocks\ExampleProductable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

class UserSubmissionServiceTest extends TestCase
{
    use DatabaseTransactions;

    private UserSubmissionServiceContract $userSubmissionService;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([AssignToProductable::class, AssignToProduct::class]);
        $this->userSubmissionService = app(UserSubmissionServiceContract::class);
    }

    public function testAssignToProductable(): void
    {
        Shop::registerProductableClass(ExampleProductable::class);

        $email = 'test@test.pl';
        $exampleProductable = ExampleProductable::factory()->create();
        $dto = new UserSubmissionDto(
            $email,
            $exampleProductable->getKey(),
            ExampleProductable::class,
        );

        $this->userSubmissionService->create($dto);

        $this->assertDatabaseHas('user_submissions', [
            'email' => $email,
            'morphable_id' => $exampleProductable->getKey(),
            'morphable_type' => ExampleProductable::class,
            'status' => UserSubmissionStatusEnum::SENT
        ]);

        Event::assertDispatched(
            AssignToProductable::class,
            fn(AssignToProductable $event) =>
                $event->getUser()->email === $email
                && $event->getProductable()->getKey() === $exampleProductable->getKey()
        );
        Event::assertNotDispatched(AssignToProduct::class);
    }

    public function testAssignToNotRegisteredProductable(): void
    {
        $email = 'test@test.pl';
        $exampleProductable = ExampleProductable::factory()->create();
        $dto = new UserSubmissionDto(
            $email,
            $exampleProductable->getKey(),
            ExampleProductable::class,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Productable class is not registered');

        $this->userSubmissionService->create($dto);

        Event::assertNotDispatched(AssignToProductable::class);
        Event::assertNotDispatched(AssignToProduct::class);
    }

    public function testAssignToNotExistingProductable(): void
    {
        Shop::registerProductableClass(ExampleProductable::class);

        $email = 'test@test.pl';
        $dto = new UserSubmissionDto(
            $email,
            -1,
            ExampleProductable::class,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Model not found');

        $this->userSubmissionService->create($dto);

        Event::assertNotDispatched(AssignToProduct::class);
        Event::assertNotDispatched(AssignToProductable::class);
    }

    public function testAssignToProduct(): void
    {
        $product = Product::factory()->create();

        $email = 'test@test.pl';
        $dto = new UserSubmissionDto(
            $email,
            $product->getKey(),
            Product::class,
        );

        $this->userSubmissionService->create($dto);

        $this->assertDatabaseHas('user_submissions', [
            'email' => $email,
            'morphable_id' => $product->getKey(),
            'morphable_type' => Product::class,
            'status' => UserSubmissionStatusEnum::SENT
        ]);

        Event::assertDispatched(
            AssignToProduct::class,
            fn(AssignToProduct $event) =>
                $event->getUser()->email === $email
                && $event->getProduct()->getKey() === $product->getKey()
        );
        Event::assertNotDispatched(AssignToProductable::class);
    }

    public function testAssignToNotExistingProduct(): void
    {
        $email = 'test@test.pl';
        $dto = new UserSubmissionDto(
            $email,
            1,
            Product::class,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Model not found');

        $this->userSubmissionService->create($dto);

        Event::assertNotDispatched(AssignToProduct::class);
        Event::assertNotDispatched(AssignToProductable::class);
    }
}

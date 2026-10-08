<?php

namespace EscolaLms\Reports\Tests\Traits;

use EscolaLms\Cart\Enums\ProductType;
use EscolaLms\Cart\Models\Order;
use EscolaLms\Cart\Models\OrderItem;
use EscolaLms\Cart\Models\Product;
use EscolaLms\Cart\Models\ProductProductable;
use EscolaLms\Cart\Services\Contracts\ProductServiceContract;
use EscolaLms\Core\Models\User;
use EscolaLms\Courses\Enum\CourseStatusEnum;
use EscolaLms\Courses\Enum\ProgressStatus;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\Courses\Repositories\Contracts\CourseProgressRepositoryContract;
use EscolaLms\Courses\Repositories\CourseProgressRepository;
use EscolaLms\Courses\Services\Contracts\ProgressServiceContract;
use EscolaLms\Courses\Services\ProgressService;
use EscolaLms\Payments\Models\Payment;

trait CoursesTestingTrait
{
    private function createCourseWithLessonAndTopic(int $topic_count = 1): Course
    {
        return Course::factory()
            ->has(
                Lesson::factory(['active' => true])
                    ->has(
                        Topic::factory(['active' => true])
                            ->count($topic_count)
                    )
            )->create([
                'status' => CourseStatusEnum::PUBLISHED,
            ]);
    }

    private function progressUserInCourse(User $user, Course $course, int $seconds = 60, string $status = ProgressStatus::IN_PROGRESS)
    {
        /** @var ProgressService $progressService */
        $progressService = app(ProgressServiceContract::class);

        $progresses = $progressService->getByUser($user);

        /** @var Course $course */
        foreach ($course->topics as $topic) {
            $this->progressUserInTopic($user, $topic, $seconds, $status);
        }

        $progressService->update($course, $user, []);
    }

    private function progressUserInTopic(User $user, Topic $topic, int $seconds = 60, string $status = ProgressStatus::IN_PROGRESS): void
    {
        /** @var CourseProgressRepository $progressRepository */
        $progressRepository = app(CourseProgressRepositoryContract::class);

        $progressRepository->updateInTopic($topic, $user, $status, $seconds);
    }

    private function makePaidOrder(User $user, Course $course, ?Course $bundledCourse = null): Order
    {
        if ($bundledCourse) {
            $product = Product::factory()->create([
                'name' => 'Product for courses ' . $course->getKey() . ' & ' . $bundledCourse->getKey(),
                'price' => 1000,
                'type' => ProductType::BUNDLE
            ]);
            $product->productables()->save(new ProductProductable([
                'productable_type' => $course->getMorphClass(),
                'productable_id' => $course->getKey()
            ]));
            $product->productables()->save(new ProductProductable([
                'productable_type' => $bundledCourse->getMorphClass(),
                'productable_id' => $bundledCourse->getKey()
            ]));
        } else {
            $productable = app(ProductServiceContract::class)->findProductable($course->getMorphClass(), $course->getKey());
            $product = app(ProductServiceContract::class)->findSingleProductForProductable($productable);
            if (is_null($product)) {
                $product = Product::factory()->create([
                    'name' => 'Product for course' . $course->getKey(),
                    'price' => 1000,
                ]);
                $product->productables()->save(new ProductProductable([
                    'productable_type' => $course->getMorphClass(),
                    'productable_id' => $course->getKey()
                ]));
            }
        }

        return Order::factory()->has(Payment::factory()->state([
            'amount' => $product->price,
            'user_id' => $user->getKey(),
            'billable_type' => get_class($user),
        ]))->afterCreating(
            fn (Order $order) => $order->items()->save(new OrderItem([
                'price' => $product->price,
                'quantity' => 1,
                'buyable_id' => $product->getKey(),
                'buyable_type' => get_class($product),
            ]))
        )->create([
            'user_id' => $user->getKey(),
            'total' => $product->price,
            'subtotal' => $product->price,
        ]);
    }
}

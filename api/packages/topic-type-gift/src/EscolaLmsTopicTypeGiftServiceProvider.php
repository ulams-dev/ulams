<?php

namespace EscolaLms\TopicTypeGift;

use EscolaLms\Categories\EscolaLmsCategoriesServiceProvider;
use EscolaLms\Courses\EscolaLmsCourseServiceProvider;
use EscolaLms\Courses\Facades\Topic;
use EscolaLms\TopicTypeGift\Http\Resources\TopicType\Admin\GiftQuizResource as AdminGiftQuizResource;
use EscolaLms\TopicTypeGift\Http\Resources\TopicType\Client\GiftQuizResource as ClientGiftQuizResource;
use EscolaLms\TopicTypeGift\Http\Resources\TopicType\Export\GiftQuizResource as ExportGiftQuizResource;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use EscolaLms\TopicTypeGift\Providers\AuthServiceProvider;
use EscolaLms\TopicTypeGift\Providers\SettingsServiceProvider;
use EscolaLms\TopicTypeGift\Repositories\AttemptAnswerRepository;
use EscolaLms\TopicTypeGift\Repositories\Contracts\AttemptAnswerRepositoryContract;
use EscolaLms\TopicTypeGift\Repositories\Contracts\GiftQuestionRepositoryContract;
use EscolaLms\TopicTypeGift\Repositories\Contracts\QuizAttemptRepositoryContract;
use EscolaLms\TopicTypeGift\Repositories\Contracts\GiftQuizRepositoryContract;
use EscolaLms\TopicTypeGift\Repositories\GiftQuestionRepository;
use EscolaLms\TopicTypeGift\Repositories\QuizAttemptRepository;
use EscolaLms\TopicTypeGift\Repositories\GiftQuizRepository;
use EscolaLms\TopicTypeGift\Services\AttemptAnswerService;
use EscolaLms\TopicTypeGift\Services\Contracts\AttemptAnswerServiceContract;
use EscolaLms\TopicTypeGift\Services\Contracts\GiftQuestionServiceContract;
use EscolaLms\TopicTypeGift\Services\Contracts\GiftQuizServiceContract;
use EscolaLms\TopicTypeGift\Services\Contracts\QuizAttemptServiceContract;
use EscolaLms\TopicTypeGift\Services\GiftQuestionService;
use EscolaLms\TopicTypeGift\Services\GiftQuizService;
use EscolaLms\TopicTypeGift\Services\QuizAttemptService;
use EscolaLms\TopicTypes\EscolaLmsTopicTypesServiceProvider;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class EscolaLmsTopicTypeGiftServiceProvider extends ServiceProvider
{
    public const SERVICES = [
        GiftQuestionServiceContract::class => GiftQuestionService::class,
        QuizAttemptServiceContract::class => QuizAttemptService::class,
        AttemptAnswerServiceContract::class => AttemptAnswerService::class,
        GiftQuizServiceContract::class => GiftQuizService::class,
    ];

    public const REPOSITORIES = [
        GiftQuestionRepositoryContract::class => GiftQuestionRepository::class,
        QuizAttemptRepositoryContract::class => QuizAttemptRepository::class,
        AttemptAnswerRepositoryContract::class => AttemptAnswerRepository::class,
        GiftQuizRepositoryContract::class => GiftQuizRepository::class,
    ];

    public $singletons = self::SERVICES + self::REPOSITORIES;

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        Topic::registerContentClass(GiftQuiz::class);
        Topic::registerResourceClasses(GiftQuiz::class, [
            'client' => ClientGiftQuizResource::class,
            'admin' => AdminGiftQuizResource::class,
            'export' => ExportGiftQuizResource::class,
        ]);
    }

    public function register(): void
    {
        $this->app->register(AuthServiceProvider::class);
        $this->app->register(EscolaLmsTopicTypesServiceProvider::class);
        $this->app->register(EscolaLmsCourseServiceProvider::class);
        $this->app->register(SettingsServiceProvider::class);
        $this->app->register(EscolaLmsCategoriesServiceProvider::class);
    }
}

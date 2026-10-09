<?php

namespace Ulams\TopicTypeGift;

use Ulams\Categories\UlamsCategoriesServiceProvider;
use Ulams\Courses\UlamsCourseServiceProvider;
use Ulams\Courses\Facades\Topic;
use Ulams\TopicTypeGift\Http\Resources\TopicType\Admin\GiftQuizResource as AdminGiftQuizResource;
use Ulams\TopicTypeGift\Http\Resources\TopicType\Client\GiftQuizResource as ClientGiftQuizResource;
use Ulams\TopicTypeGift\Http\Resources\TopicType\Export\GiftQuizResource as ExportGiftQuizResource;
use Ulams\TopicTypeGift\Models\GiftQuiz;
use Ulams\TopicTypeGift\Providers\AuthServiceProvider;
use Ulams\TopicTypeGift\Providers\SettingsServiceProvider;
use Ulams\TopicTypeGift\Repositories\AttemptAnswerRepository;
use Ulams\TopicTypeGift\Repositories\Contracts\AttemptAnswerRepositoryContract;
use Ulams\TopicTypeGift\Repositories\Contracts\GiftQuestionRepositoryContract;
use Ulams\TopicTypeGift\Repositories\Contracts\QuizAttemptRepositoryContract;
use Ulams\TopicTypeGift\Repositories\Contracts\GiftQuizRepositoryContract;
use Ulams\TopicTypeGift\Repositories\GiftQuestionRepository;
use Ulams\TopicTypeGift\Repositories\QuizAttemptRepository;
use Ulams\TopicTypeGift\Repositories\GiftQuizRepository;
use Ulams\TopicTypeGift\Services\AttemptAnswerService;
use Ulams\TopicTypeGift\Services\Contracts\AttemptAnswerServiceContract;
use Ulams\TopicTypeGift\Services\Contracts\GiftQuestionServiceContract;
use Ulams\TopicTypeGift\Services\Contracts\GiftQuizServiceContract;
use Ulams\TopicTypeGift\Services\Contracts\QuizAttemptAllowanceContract;
use Ulams\TopicTypeGift\Services\Contracts\QuizAttemptServiceContract;
use Ulams\TopicTypeGift\Services\NoExtraAttempts;
use Ulams\TopicTypeGift\Console\SnapshotMaxScoresCommand;
use Ulams\TopicTypeGift\Services\GiftQuestionService;
use Ulams\TopicTypeGift\Services\GiftQuizService;
use Ulams\TopicTypeGift\Services\QuizAttemptService;
use Ulams\TopicTypes\UlamsTopicTypesServiceProvider;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class UlamsTopicTypeGiftServiceProvider extends ServiceProvider
{
    public const SERVICES = [
        GiftQuestionServiceContract::class => GiftQuestionService::class,
        QuizAttemptServiceContract::class => QuizAttemptService::class,
        AttemptAnswerServiceContract::class => AttemptAnswerService::class,
        GiftQuizServiceContract::class => GiftQuizService::class,
        QuizAttemptAllowanceContract::class => NoExtraAttempts::class,
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

        if ($this->app->runningInConsole()) {
            $this->commands([SnapshotMaxScoresCommand::class]);
        }

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
        $this->app->register(UlamsTopicTypesServiceProvider::class);
        $this->app->register(UlamsCourseServiceProvider::class);
        $this->app->register(SettingsServiceProvider::class);
        $this->app->register(UlamsCategoriesServiceProvider::class);
    }
}

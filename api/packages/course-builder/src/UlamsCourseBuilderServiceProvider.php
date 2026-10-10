<?php

namespace Ulams\CourseBuilder;

use Illuminate\Support\ServiceProvider;
use Ulams\Ai\Fake\FakeResponders;
use Ulams\Ai\Prompts\PromptRegistry;
use Ulams\Ai\UlamsAiServiceProvider;
use Ulams\CourseBuilder\Apply\BlueprintApplier;
use Ulams\CourseBuilder\Apply\CourseCommerce;
use Ulams\CourseBuilder\Apply\DeleteEverything;
use Ulams\CourseBuilder\Apply\SiteTheme;
use Ulams\CourseBuilder\Apply\TopicWriter;
use Ulams\CourseBuilder\ContentTypes\ContentTypeRegistry;
use Ulams\CourseBuilder\ContentTypes\H5pLibraries;
use Ulams\CourseBuilder\ContentTypes\H5pType;
use Ulams\CourseBuilder\ContentTypes\InteractiveType;
use Ulams\CourseBuilder\ContentTypes\LiaScriptType;
use Ulams\CourseBuilder\ContentTypes\RichTextType;
use Ulams\CourseBuilder\Contracts\FragmentArchive;
use Ulams\CourseBuilder\Contracts\RemovalPolicy;
use Ulams\CourseBuilder\Ingestion\NoFragmentArchive;
use Ulams\CourseBuilder\Blueprint\SchemaRegistry;
use Ulams\CourseBuilder\Console\EvalCommand;
use Ulams\CourseBuilder\Console\PruneEventsCommand;
use Ulams\CourseBuilder\Console\SessionExportCommand;
use Ulams\CourseBuilder\Console\SessionImportCommand;
use Ulams\CourseBuilder\Events\EventLog;
use Ulams\CourseBuilder\Fake\SyntheticResponders;
use Ulams\CourseBuilder\Ingestion\SourceIngestor;
use Ulams\CourseBuilder\Pipeline\BriefService;
use Ulams\CourseBuilder\Pipeline\GenerationService;
use Ulams\CourseBuilder\Pipeline\InterviewService;
use Ulams\CourseBuilder\Pipeline\Llm;
use Ulams\CourseBuilder\Pipeline\OutlineEditor;
use Ulams\CourseBuilder\Pipeline\OutlineService;
use Ulams\CourseBuilder\Pipeline\PatchService;
use Ulams\CourseBuilder\Pipeline\PriceService;
use Ulams\CourseBuilder\Publish\LandingValidator;
use Ulams\CourseBuilder\Publish\PublishCheck;
use Ulams\CourseBuilder\Site\NewSite;
use Ulams\CourseBuilder\Transfer\SessionArchive;
use Ulams\CourseBuilder\Pipeline\PromptContext;
use Ulams\CourseBuilder\Services\RunService;
use Ulams\CourseBuilder\Services\VersionService;
use Ulams\CourseBuilder\Ui\Surfaces;
use Ulams\CourseBuilder\Ui\UiCatalogue;
use Ulams\Commerce\UlamsCommerceServiceProvider;
use Ulams\Courses\UlamsCourseServiceProvider;
use Ulams\H5P\UlamsH5PServiceProvider;
use Ulams\Interactive\UlamsInteractiveServiceProvider;
use Ulams\LiaScript\UlamsLiaScriptServiceProvider;
use Ulams\Pages\UlamsPagesServiceProvider;
use Ulams\Settings\UlamsSettingsServiceProvider;
use Ulams\TopicTypeGift\UlamsTopicTypeGiftServiceProvider;
use Ulams\TopicTypes\UlamsTopicTypesServiceProvider;
use Ulams\Uploads\UlamsUploadsServiceProvider;

/**
 * AI Course Builder (ADR 0010, 0011): sources and fragments, interview and Course Brief, Course
 * Blueprint versions, the queued pipeline, the applier through domain services, element chat,
 * the AG-UI event log and SSE stream, and the eval command.
 */
class UlamsCourseBuilderServiceProvider extends ServiceProvider
{
    public $singletons = [
        EventLog::class => EventLog::class,
        SchemaRegistry::class => SchemaRegistry::class,
        UiCatalogue::class => UiCatalogue::class,
        Surfaces::class => Surfaces::class,
        PromptContext::class => PromptContext::class,
        BriefService::class => BriefService::class,
        Llm::class => Llm::class,
        VersionService::class => VersionService::class,
        SourceIngestor::class => SourceIngestor::class,
        InterviewService::class => InterviewService::class,
        OutlineService::class => OutlineService::class,
        OutlineEditor::class => OutlineEditor::class,
        GenerationService::class => GenerationService::class,
        PatchService::class => PatchService::class,
        BlueprintApplier::class => BlueprintApplier::class,
        SiteTheme::class => SiteTheme::class,
        TopicWriter::class => TopicWriter::class,
        ContentTypeRegistry::class => ContentTypeRegistry::class,
        RichTextType::class => RichTextType::class,
        LiaScriptType::class => LiaScriptType::class,
        H5pType::class => H5pType::class,
        H5pLibraries::class => H5pLibraries::class,
        InteractiveType::class => InteractiveType::class,
        CourseCommerce::class => CourseCommerce::class,
        PriceService::class => PriceService::class,
        PublishCheck::class => PublishCheck::class,
        NewSite::class => NewSite::class,
        SessionArchive::class => SessionArchive::class,
        LandingValidator::class => LandingValidator::class,
        RunService::class => RunService::class,
        RemovalPolicy::class => DeleteEverything::class,
        FragmentArchive::class => NoFragmentArchive::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/course_builder.php', 'course_builder');
        foreach ([UlamsAiServiceProvider::class, UlamsUploadsServiceProvider::class, UlamsCourseServiceProvider::class, UlamsTopicTypesServiceProvider::class, UlamsTopicTypeGiftServiceProvider::class, UlamsPagesServiceProvider::class, UlamsSettingsServiceProvider::class, UlamsCommerceServiceProvider::class, UlamsLiaScriptServiceProvider::class, UlamsH5PServiceProvider::class, UlamsInteractiveServiceProvider::class] as $provider) {
            $this->app->register($provider);
        }
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // uploads-package policy for builder sources (extension + sniffed MIME + size + scan)
        if (config('ulams_uploads.policies.' . SourceIngestor::UPLOAD_KIND) === null) {
            config(['ulams_uploads.policies.' . SourceIngestor::UPLOAD_KIND => SourceIngestor::policy()]);
        }
        // private disk for uploaded sources (never served)
        if (config('filesystems.disks.course_builder_private') === null) {
            config(['filesystems.disks.course_builder_private' => ['driver' => 'local', 'root' => config('course_builder.private_root'), 'visibility' => 'private', 'throw' => true]]);
        }

        $this->app->make(PromptRegistry::class)->addPath(Llm::PROMPTS, __DIR__ . '/../resources/prompts');
        SyntheticResponders::register($this->app->make(FakeResponders::class));

        if ($this->app->runningInConsole()) {
            $this->commands([EvalCommand::class, PruneEventsCommand::class, SessionExportCommand::class, SessionImportCommand::class]);
        }
    }
}

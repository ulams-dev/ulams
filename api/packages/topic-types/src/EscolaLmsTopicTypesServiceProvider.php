<?php

namespace EscolaLms\TopicTypes;

use EscolaLms\Cmi5\EscolaLmsCmi5ServiceProvider;
use EscolaLms\Courses\Facades\Topic;
use EscolaLms\HeadlessH5P\Http\Resources\ContentIndexResource;
use EscolaLms\HeadlessH5P\Repositories\H5PContentRepository;
use EscolaLms\TopicTypes\Commands\FillTopicTypeMetadataCommand;
use EscolaLms\TopicTypes\Commands\FixAssetPathsCommand;
use EscolaLms\TopicTypes\Commands\FixTopicTypeColumnName;
use EscolaLms\TopicTypes\Helpers\Markdown;
use EscolaLms\TopicTypes\Helpers\Path;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Admin\AudioResource as AdminAudioResource;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Admin\H5PResource as AdminH5PResource;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Admin\ImageResource as AdminImageResource;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Admin\OEmbedResource as AdminOEmbedResource;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Admin\PDFResource as AdminPDFResource;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Admin\RichTextResource as AdminRichTextResource;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Admin\VideoResource as AdminVideoResource;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Admin\ScormScoResource as AdminScormScoResource;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Admin\Cmi5AuResource as AdminCmi5AuResource;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Client\AudioResource as ClientAudioResource;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Client\H5PResource as ClientH5PResource;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Client\ImageResource as ClientImageResource;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Client\OEmbedResource as ClientOEmbedResource;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Client\PDFResource as ClientPDFResource;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Client\RichTextResource as ClientRichTextResource;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Client\VideoResource as ClientVideoResource;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Client\ScormScoResource as ClientScormScoResource;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Client\Cmi5AuResource as ClientCmi5AuResource;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Export\AudioResource as ExportAudioResource;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Export\H5PResource as ExportH5PResource;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Export\ImageResource as ExportImageResource;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Export\OEmbedResource as ExportOEmbedResource;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Export\PDFResource as ExportPDFResource;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Export\RichTextResource as ExportRichTextResource;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Export\VideoResource as ExportVideoResource;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Export\ScormScoResource as ExportScormScoResource;
use EscolaLms\TopicTypes\Http\Resources\TopicType\Export\Cmi5AuResource as ExportCmi5AuResource;
use EscolaLms\TopicTypes\Models\TopicContent\Audio;
use EscolaLms\TopicTypes\Models\TopicContent\Cmi5Au;
use EscolaLms\TopicTypes\Models\TopicContent\H5P;
use EscolaLms\TopicTypes\Models\TopicContent\Image;
use EscolaLms\TopicTypes\Models\TopicContent\OEmbed;
use EscolaLms\TopicTypes\Models\TopicContent\PDF;
use EscolaLms\TopicTypes\Models\TopicContent\RichText;
use EscolaLms\TopicTypes\Models\TopicContent\ScormSco;
use EscolaLms\TopicTypes\Models\TopicContent\Video;
use EscolaLms\TopicTypes\Services\Contracts\TopicTypeServiceContract;
use EscolaLms\TopicTypes\Services\TopicTypeService;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class EscolaLmsTopicTypesServiceProvider extends ServiceProvider
{
    public $singletons = [
        TopicTypeServiceContract::class => TopicTypeService::class,
    ];

    public $bindings = [
        'markdown-helper' => Markdown::class,
        'export-path' => Path::class,
    ];

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->mergeConfigFrom(__DIR__ . '/../config/topic-h5p.php', 'topic-h5p');
        if ($this->app->runningInConsole()) {
            $this->commands([
                FixTopicTypeColumnName::class,
                FixAssetPathsCommand::class,
                FillTopicTypeMetadataCommand::class,
            ]);
        }
        Topic::registerContentClasses([
            Audio::class,
            Video::class,
            Image::class,
            RichText::class,
            H5P::class,
            OEmbed::class,
            PDF::class,
            ScormSco::class,
        ]);
        Topic::registerResourceClasses(Audio::class, [
            'client' => ClientAudioResource::class,
            'admin' => AdminAudioResource::class,
            'export' => ExportAudioResource::class,
        ]);
        Topic::registerResourceClasses(H5P::class, [
            'client' => ClientH5PResource::class,
            'admin' => AdminH5PResource::class,
            'export' => ExportH5PResource::class,
        ]);
        Topic::registerResourceClasses(Image::class, [
            'client' => ClientImageResource::class,
            'admin' => AdminImageResource::class,
            'export' => ExportImageResource::class,
        ]);
        Topic::registerResourceClasses(OEmbed::class, [
            'client' => ClientOEmbedResource::class,
            'admin' => AdminOEmbedResource::class,
            'export' => ExportOEmbedResource::class,
        ]);
        Topic::registerResourceClasses(PDF::class, [
            'client' => ClientPDFResource::class,
            'admin' => AdminPDFResource::class,
            'export' => ExportPDFResource::class,
        ]);
        Topic::registerResourceClasses(RichText::class, [
            'client' => ClientRichTextResource::class,
            'admin' => AdminRichTextResource::class,
            'export' => ExportRichTextResource::class,
        ]);
        Topic::registerResourceClasses(Video::class, [
            'client' => ClientVideoResource::class,
            'admin' => AdminVideoResource::class,
            'export' => ExportVideoResource::class,
        ]);
        Topic::registerResourceClasses(ScormSco::class, [
            'client' => ClientScormScoResource::class,
            'admin' => AdminScormScoResource::class,
            'export' => ExportScormScoResource::class,
        ]);
        if (class_exists(EscolaLmsCmi5ServiceProvider::class)) {
            Topic::registerContentClasses([
                Cmi5Au::class
            ]);
            Topic::registerResourceClasses(Cmi5Au::class, [
                'client' => ClientCmi5AuResource::class,
                'admin' => AdminCmi5AuResource::class,
                'export' => ExportCmi5AuResource::class,
            ]);
        }
    }

    public function register()
    {
        H5PContentRepository::extendQueryJoin(
            fn () => [['topic_h5ps.value', 'hh5p_contents.id']],
            'topic_h5ps'
        );
        H5PContentRepository::extendQuerySelect(
            fn () => DB::raw("COUNT(topic_h5ps.value) as count_h5p"),
            'topic_h5ps'
        );
        H5PContentRepository::extendQueryGroupBy(
            fn () => [
                'hh5p_contents.id',
                'hh5p_contents.uuid',
                'hh5p_contents.library_id',
                'hh5p_contents.user_id',
                'hh5p_contents.author',
                'hh5p_contents.created_at',
                DB::Connection() instanceof PostgresConnection ? 'hh5p_contents.parameters::jsonb' : 'hh5p_contents.parameters',
            ],
            'topic_h5ps'
        );
        ContentIndexResource::extend(fn ($thisObj) => [
            'count_h5p' => $thisObj->count_h5p,
        ]);
    }
}

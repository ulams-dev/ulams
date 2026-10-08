<?php

namespace Ulams\TopicTypes;

use Illuminate\Support\Facades\Event;
use Ulams\Cmi5\UlamsCmi5ServiceProvider;
use Ulams\Scorm\Events\ScormScoCompleted;
use Ulams\TopicTypes\Listeners\CompleteScormTopics;
use Ulams\Courses\Facades\Topic;
use Ulams\TopicTypes\Commands\FillTopicTypeMetadataCommand;
use Ulams\TopicTypes\Commands\FixAssetPathsCommand;
use Ulams\TopicTypes\Commands\FixTopicTypeColumnName;
use Ulams\TopicTypes\Helpers\Markdown;
use Ulams\TopicTypes\Helpers\Path;
use Ulams\TopicTypes\Http\Resources\TopicType\Admin\AudioResource as AdminAudioResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Admin\H5PResource as AdminH5PResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Admin\ImageResource as AdminImageResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Admin\OEmbedResource as AdminOEmbedResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Admin\PDFResource as AdminPDFResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Admin\RichTextResource as AdminRichTextResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Admin\VideoResource as AdminVideoResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Admin\ScormScoResource as AdminScormScoResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Admin\Cmi5AuResource as AdminCmi5AuResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Client\AudioResource as ClientAudioResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Client\H5PResource as ClientH5PResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Client\ImageResource as ClientImageResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Client\OEmbedResource as ClientOEmbedResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Client\PDFResource as ClientPDFResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Client\RichTextResource as ClientRichTextResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Client\VideoResource as ClientVideoResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Client\ScormScoResource as ClientScormScoResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Client\Cmi5AuResource as ClientCmi5AuResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Export\AudioResource as ExportAudioResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Export\H5PResource as ExportH5PResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Export\ImageResource as ExportImageResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Export\OEmbedResource as ExportOEmbedResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Export\PDFResource as ExportPDFResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Export\RichTextResource as ExportRichTextResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Export\VideoResource as ExportVideoResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Export\ScormScoResource as ExportScormScoResource;
use Ulams\TopicTypes\Http\Resources\TopicType\Export\Cmi5AuResource as ExportCmi5AuResource;
use Ulams\TopicTypes\Models\TopicContent\Audio;
use Ulams\TopicTypes\Models\TopicContent\Cmi5Au;
use Ulams\TopicTypes\Models\TopicContent\H5P;
use Ulams\TopicTypes\Models\TopicContent\Image;
use Ulams\TopicTypes\Models\TopicContent\OEmbed;
use Ulams\TopicTypes\Models\TopicContent\PDF;
use Ulams\TopicTypes\Models\TopicContent\RichText;
use Ulams\TopicTypes\Models\TopicContent\ScormSco;
use Ulams\TopicTypes\Models\TopicContent\Video;
use Ulams\TopicTypes\Services\Contracts\TopicTypeServiceContract;
use Ulams\TopicTypes\Services\TopicTypeService;
use Illuminate\Support\ServiceProvider;

class UlamsTopicTypesServiceProvider extends ServiceProvider
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
        Event::listen(ScormScoCompleted::class, CompleteScormTopics::class);
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
        if (class_exists(UlamsCmi5ServiceProvider::class)) {
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
}

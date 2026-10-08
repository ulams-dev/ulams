<?php

namespace EscolaLms\TemplatesEmail\Jobs;

use EscolaLms\Templates\Models\TemplateSection;
use EscolaLms\TemplatesEmail\Core\EmailChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CompleteGlobalVariableJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function handle(): void
    {
        $templateSections = TemplateSection::where('content', 'LIKE', '%' . $this->name . '%')->get();

        foreach ($templateSections as $section) {
            EmailChannel::processTemplateAfterSaving($section->template);
        }
    }
}

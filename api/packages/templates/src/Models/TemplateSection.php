<?php

namespace EscolaLms\Templates\Models;

use EscolaLms\Templates\Database\Factories\TemplateSectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateSection extends Model
{
    use HasFactory;

    protected $table = 'template_sections';

    protected $casts = [
        'id' => 'integer',
        'key' => 'string',
        'content' => 'string',
    ];

    protected $fillable = [
        'key',
        'content',
        'template_id',
    ];

    protected static function newFactory()
    {
        return TemplateSectionFactory::new();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}

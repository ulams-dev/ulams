<?php

namespace EscolaLms\Templates\Models;

use EscolaLms\Core\Models\User;
use EscolaLms\Templates\Database\Factories\TemplateFactory;
use EscolaLms\Templates\Facades\Template as FacadesTemplate;
use EscolaLms\Templates\Services\Contracts\TemplateServiceContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *      schema="Template",
 *      @OA\Property(
 *          property="id",
 *          description="template id",
 *          type="integer",
 *      ),
 *      @OA\Property(
 *          property="name",
 *          description="template name",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="event",
 *          description="event full classname",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="channel",
 *          description="channel full classname",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="default",
 *          description="this template is default template for given channel and event pair",
 *          type="bool"
 *      ),
 * )
 */
class Template extends Model
{
    use HasFactory;

    protected $table = 'templates';

    protected $casts = [
        'id' => 'integer',
        'name' => 'string',
        'channel' => 'string',
        'event' => 'string',
        'default' => 'bool',
    ];

    protected $guarded = [
        'id'
    ];

    protected static function newFactory()
    {
        return TemplateFactory::new();
    }

    public function sections(): HasMany
    {
        return $this->hasMany(TemplateSection::class);
    }

    public function templatables(): HasMany
    {
        return $this->hasMany(Templatable::class);
    }

    public function previewContent(?User $user = null): array
    {
        /** @var TemplateServiceContract $service */
        $service = app(TemplateServiceContract::class);
        return $service->previewContentUsingMockedVariables($this, $user);
    }

    public function generateContent(array $variables): array
    {
        /** @var TemplateServiceContract $service */
        $service = app(TemplateServiceContract::class);
        return $service->generateContentUsingVariables($this, $variables);
    }

    public function getIsValidAttribute(): bool
    {
        /** @var TemplateServiceContract $service */
        $service = app(TemplateServiceContract::class);
        return $service->isValid($this);
    }

    public function getIsAssignableAttribute(): bool
    {
        return !empty($this->assignable_class) && class_exists($this->assignable_class);
    }

    public function getAssignableClassAttribute(): ?string
    {
        return $this->variable_class ? $this->variable_class::assignableClass() : null;
    }

    public function getVariableClassAttribute(): ?string
    {
        return FacadesTemplate::getVariableClassName($this->event, $this->channel);
    }
}

<?php

namespace Ulams\LiaScript\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $title
 * @property int $current_version
 * @property ?int $author_id
 * @property-read LiaScriptVersion|null $current
 */
class LiaScriptDocument extends Model
{
    protected $table = 'liascript_documents';

    protected $fillable = ['title', 'current_version', 'author_id'];

    protected $casts = ['current_version' => 'integer'];

    public function versions(): HasMany
    {
        return $this->hasMany(LiaScriptVersion::class, 'liascript_document_id')->orderBy('version');
    }

    public function current(): HasOne
    {
        return $this->hasOne(LiaScriptVersion::class, 'liascript_document_id')
            ->whereColumn('liascript_versions.version', 'liascript_documents.current_version');
    }

    public function version(int $version): ?LiaScriptVersion
    {
        return $this->versions()->where('version', $version)->first();
    }
}

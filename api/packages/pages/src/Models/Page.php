<?php

namespace EscolaLms\Pages\Models;

use EscolaLms\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Page",
 *     required={"title","author_id","content"},
 *     @OA\Property(
 *          property="title",
 *          type="string",
 *          description="page title"
 *     ),
 *     @OA\Property(
 *         property="author_id",
 *         type="integer",
 *         description="identifier of the user object who owns a page"
 *     ),
 *     @OA\Property(
 *          property="content",
 *          type="string",
 *          description="page content"
 *     ),
 *     @OA\Property(
 *          property="active",
 *          type="boolean",
 *          description="page is active"
 *     ),
 * )
 *
 * @property integer $id
 * @property string $slug
 * @property string $title
 * @property integer $author_id
 * @property string $content
 * @property boolean $active
 */
class Page extends Model
{
    use HasFactory;

    public $table = 'pages';
    public $timestamps = false;

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'slug' => 'string',
        'title' => 'string',
        'author_id' => 'integer',
        'content' => 'string',
        'active' => 'boolean'
    ];

    public $fillable = [
        'slug',
        'title',
        'author_id',
        'content',
        'active'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     **/
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}

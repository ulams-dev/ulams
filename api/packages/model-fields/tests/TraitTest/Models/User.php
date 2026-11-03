<?php

namespace Ulams\ModelFields\Tests\TraitTest\Models;

use Ulams\Core\Models\Concerns\HasMorphAlias;
use Illuminate\Database\Eloquent\Model;
// use Ulams\ModelFields\Models\Model;
use Ulams\ModelFields\Traits\ModelFields;

class User extends Model
{
    use HasMorphAlias;

    use ModelFields;

    protected $table = 'users';

    protected $fillable = ['first_name', 'last_name', 'email'];

    protected $appends = ['foo'];

    public function getFooAttribute()
    {
        return 'bar';
    }
}

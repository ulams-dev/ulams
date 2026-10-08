<?php

namespace Ulams\ModelFields\Tests\Models;

//use Illuminate\Database\Eloquent\Model;
use Ulams\ModelFields\Models\Model;
//use Ulams\ModelFields\Traits\ModelFields;

class User extends Model
{

    protected $table = 'users';

    protected $fillable = ['first_name', 'last_name', 'email'];

    protected $appends = ['foo'];

    public function getFooAttribute()
    {
        return 'bar';
    }
}

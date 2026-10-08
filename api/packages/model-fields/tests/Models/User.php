<?php

namespace EscolaLms\ModelFields\Tests\Models;

//use Illuminate\Database\Eloquent\Model;
use EscolaLms\ModelFields\Models\Model;
//use EscolaLms\ModelFields\Traits\ModelFields;

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

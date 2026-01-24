<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Allergen extends Model
{
    protected $table = 'allergens';

    protected $fillable = [
        'code', 'name', 'status',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Explore extends Model
{
    //
    protected $fillable = [
      'title',
      'filter',
      'description',
      'image',
      'web_link'  
    ];

}

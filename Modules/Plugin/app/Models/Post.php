<?php

namespace Modules\Plugin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Plugin\Database\Factories\PostFactory;

class Post extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    // protected static function newFactory(): PostFactory
    // {
    //     // return PostFactory::new();
    // }
}

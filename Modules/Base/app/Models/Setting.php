<?php

namespace Modules\Base\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Base\Database\Factories\SettingFactory;

class Setting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    // protected static function newFactory(): SettingFactory
    // {
    //     // return SettingFactory::new();
    // }
}

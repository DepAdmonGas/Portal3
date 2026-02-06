<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideosLista extends Model
{
    protected $table = 'td_videos_lista';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'fecha'
    ];

    protected $casts = [
        'id' => 'int',
        'fecha' => 'date'
    ];
}

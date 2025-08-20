<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    protected $fillable = [
        'ip', 'request_time', 'url',
        'user_agent', 'browser', 'os', 'architecture'
    ];

    protected $casts = [
        'request_time' => 'datetime',
    ];
}

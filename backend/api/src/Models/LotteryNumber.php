<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LotteryNumber extends Model
{
    protected $table = 'lottery_numbers';

    protected $fillable = [
        'numbers',
        'draw_time',
    ];

    protected $casts = [
        'numbers' => 'json',
        'draw_time' => 'datetime',
    ];
}

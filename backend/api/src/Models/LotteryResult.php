<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LotteryResult extends Model
{
    protected $table = 'lottery_results';

    protected $casts = [
        'winning_numbers' => 'array',
        'zodiac_signs' => 'array',
        'colors' => 'array',
        'draw_time' => 'datetime',
    ];

    protected $fillable = [
        'lottery_type',
        'issue_number',
        'winning_numbers',
        'zodiac_signs',
        'colors',
        'number_colors_json',
        'draw_time',
    ];
}

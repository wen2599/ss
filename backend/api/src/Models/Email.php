<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\\Database\\Eloquent\\Model;

class Email extends Model
{
    protected $table = \'emails\';

    protected $fillable = [
        \'from_address\',
        \'to_address\',
        \'subject\',
        \'raw_content\',
        \'user_id\'
    ];

    protected $casts = [
        \'created_at\' => \'datetime\',
        \'updated_at\' => \'datetime\',
    ];

    public $timestamps = true;
}

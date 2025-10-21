<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Email extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'emails';

    /**
     * Indicates if the model should be timestamped.
     * We set this to false because the 'received_at' column is handled
     * by the database's DEFAULT CURRENT_TIMESTAMP mechanism.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'from_address',
        'to_address',
        'subject',
        'raw_content',
        'html_content',
        'parsed_data',
        'worker_secret_provided',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'parsed_data' => 'array',
        'worker_secret_provided' => 'boolean',
        'received_at' => 'datetime',
    ];
}

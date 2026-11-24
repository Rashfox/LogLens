<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'hit_count' => 'integer',
            'status_code' => 'integer',
            'is_scanner' => 'boolean',
            'first_seen' => 'datetime',
            'last_seen' => 'datetime',
        ];
    }
}

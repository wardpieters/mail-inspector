<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScanOutbox extends Model
{
    protected $table = 'scan_outbox';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['dispatched_at' => 'immutable_datetime'];
    }
}

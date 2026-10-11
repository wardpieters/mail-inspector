<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScanFinding extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['evidence' => 'array', 'duration_ms' => 'integer'];
    }
}

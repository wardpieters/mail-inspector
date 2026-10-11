<?php

namespace App\Models;

use App\Mail\Enums\EndpointStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScanEndpoint extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => EndpointStatus::class, 'family' => 'integer', 'preference' => 'integer', 'discovery_secure' => 'boolean', 'lease_expires_at' => 'immutable_datetime', 'transport' => 'array'];
    }

    public function scan()
    {
        return $this->belongsTo(Scan::class);
    }

    public function findings()
    {
        return $this->hasMany(ScanFinding::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OffenseType extends Model
{
    protected $table = 'offense_types';

    protected $fillable = [
        'name',
        'description',
        'severity',
    ];

    protected $casts = [
        'severity' => 'string',
    ];

    public function severityStampClass(): string
    {
        return match ($this->severity) {
            'minor' => 'stamp minor',
            'major' => 'stamp major',
            'critical' => 'stamp grave',
            default => 'stamp muted',
        };
    }
}

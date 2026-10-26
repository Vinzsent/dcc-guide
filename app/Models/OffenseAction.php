<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OffenseAction extends Model
{
    protected $table = 'offense_actions';

    protected $fillable = [
        'offense_id',
        'action_type',
        'notes',
        'recorded_by',
    ];

    public function offense(): BelongsTo
    {
        return $this->belongsTo(StudentOffense::class, 'offense_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}

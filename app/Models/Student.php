<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    protected $table = 'students';

    protected $fillable = [
        'student_number',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'gender',
        'education_level',
        'course_or_grade',
        'year_level',
        'section',
        'photo_path',
        'enrollment_status',
    ];

    protected $casts = [
        'enrollment_status' => 'string',
        'gender' => 'string',
    ];

    public function rfids(): HasMany
    {
        return $this->hasMany(StudentRfid::class);
    }

    public function activeRfid(): HasOne
    {
        return $this->hasOne(StudentRfid::class)->where('is_active', true);
    }

    public function offenses(): HasMany
    {
        return $this->hasMany(StudentOffense::class)->latest('incident_at');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * "Dela Cruz, Marco R." — ledger-style display name.
     */
    public function getFullNameAttribute(): string
    {
        $name = $this->last_name.', '.$this->first_name;

        if ($this->middle_name) {
            $name .= ' '.mb_substr($this->middle_name, 0, 1).'.';
        }

        if ($this->suffix) {
            $name .= ' '.$this->suffix;
        }

        return $name;
    }

    public function getProgramLabelAttribute(): string
    {
        $parts = array_filter([
            $this->course_or_grade,
            $this->year_level,
            $this->section ? 'Section '.$this->section : null,
        ]);

        return implode(' · ', $parts) ?: '—';
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1));
    }

    /**
     * Photo to display for this student: an uploaded photo when present,
     * otherwise the cartoon avatar matching the student's gender; when the
     * gender is not set, a stable default is assigned by name.
     */
    public function avatarUrl(): string
    {
        if ($this->photo_path) {
            return asset('storage/'.$this->photo_path);
        }

        $variant = match ($this->gender) {
            'female' => 'kid-girl',
            'male' => 'kid-boy',
            default => (crc32($this->full_name) % 2 === 0 ? 'kid-girl' : 'kid-boy'),
        };

        return asset('images/avatars/'.$variant.'.svg');
    }

    public function enrollmentStampClass(): string
    {
        return match ($this->enrollment_status) {
            'enrolled' => 'stamp ok',
            'pending_verification' => 'stamp warn',
            'graduated' => 'stamp info',
            'withdrawn' => 'stamp grave',
            default => 'stamp muted',
        };
    }

    public function enrollmentLabel(): string
    {
        return str_replace('_', ' ', $this->enrollment_status);
    }
}

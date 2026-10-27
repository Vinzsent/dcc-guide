<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class StudentOffense extends Model
{
    public const STATUSES = [
        'recorded',
        'pending_review',
        'under_investigation',
        'resolved',
        'closed',
        'dismissed',
    ];

    protected $table = 'student_offenses';

    protected $fillable = [
        'reference_no',
        'student_id',
        'offense_type_id',
        'incident_at',
        'location',
        'description',
        'witnesses',
        'status',
        'reported_by',
        'recorded_at',
    ];

    protected $casts = [
        'incident_at' => 'datetime',
        'recorded_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function offenseType(): BelongsTo
    {
        return $this->belongsTo(OffenseType::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(OffenseAction::class, 'offense_id')->latest('created_at');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['recorded', 'pending_review', 'under_investigation']);
    }

    /**
     * Cases belonging to College students — the office's current scope.
     */
    public function scopeForCollegeStudents(Builder $query): Builder
    {
        return $query->whereHas('student', fn ($student) => $student->where('education_level', 'College'));
    }

    /**
     * The school year cases are counted in — June to May, e.g. SY 2026–2027.
     *
     * @return array{start_year: int, label: string, starts_at: Carbon, ends_at: Carbon}
     */
    public static function currentSchoolYear(): array
    {
        $startYear = now()->month >= 6 ? now()->year : now()->year - 1;

        return [
            'start_year' => $startYear,
            'label' => 'SY '.$startYear.'–'.($startYear + 1),
            'starts_at' => Carbon::create($startYear, 6, 1)->startOfDay(),
            'ends_at' => Carbon::create($startYear + 1, 5, 31)->endOfDay(),
        ];
    }

    /**
     * Cases whose incident date falls within the given school year (June–May).
     */
    public function scopeForSchoolYear(Builder $query, ?int $startYear = null): Builder
    {
        $year = $startYear ?? static::currentSchoolYear()['start_year'];

        return $query->whereBetween('incident_at', [
            Carbon::create($year, 6, 1)->startOfDay(),
            Carbon::create($year + 1, 5, 31)->endOfDay(),
        ]);
    }

    public function statusLabel(): string
    {
        return str_replace('_', ' ', $this->status);
    }

    public function statusStampClass(): string
    {
        return match ($this->status) {
            'resolved' => 'stamp ok',
            'pending_review' => 'stamp warn',
            'under_investigation' => 'stamp serious',
            'recorded' => 'stamp info',
            default => 'stamp muted',
        };
    }

    /**
     * Next ledger reference number, e.g. OFF-2026-0042.
     */
    public static function nextReferenceNo(): string
    {
        $year = now()->format('Y');
        $count = static::query()->whereYear('created_at', $year)->count() + 1;

        do {
            $candidate = 'OFF-'.$year.'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
            $exists = static::query()->where('reference_no', $candidate)->exists();
            $count++;
        } while ($exists);

        return $candidate;
    }
}

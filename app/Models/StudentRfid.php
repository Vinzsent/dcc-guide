<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class StudentRfid extends Model
{
    protected $table = 'student_rfid';

    protected $fillable = [
        'student_id',
        'card_uid',
        'is_active',
        'assigned_by',
        'assigned_at',
        'deactivated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'assigned_at' => 'datetime',
        'deactivated_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Find the active card registration for a scanned card UID.
     */
    public static function activeForCard(string $cardUid): ?self
    {
        /** @var StudentRfid|null $card */
        $card = static::query()
            ->where('card_uid', mb_strtoupper(trim($cardUid)))
            ->where('is_active', true)
            ->first();

        return $card;
    }

    public const ASSIGN_CREATED = 'created';

    public const ASSIGN_REASSIGNED = 'reassigned';

    public const ASSIGN_UNCHANGED = 'unchanged';

    /**
     * Attach a card to a student, honoring the unique card_uid index.
     *
     * - Active card held by another student → ValidationException (unassign it there first).
     * - Retired registration of the same card → taken over (re-issued).
     * - Student's other active cards are retired first.
     *
     * @throws ValidationException
     */
    public static function assignTo(Student $student, string $cardUid, int $userId): string
    {
        $cardUid = mb_strtoupper(trim($cardUid));
        $existing = static::query()->where('card_uid', $cardUid)->first();

        if ($existing !== null) {
            if ($existing->student_id === $student->id && $existing->is_active) {
                return static::ASSIGN_UNCHANGED;
            }

            if ($existing->student_id !== $student->id && $existing->is_active) {
                throw ValidationException::withMessages([
                    'card_uid' => 'Card '.$cardUid.' is still registered to '.$existing->student->full_name.' — unassign it there first.',
                ]);
            }

            $existing->update([
                'student_id' => $student->id,
                'is_active' => true,
                'assigned_by' => $userId,
                'assigned_at' => now(),
                'deactivated_at' => null,
            ]);

            return static::ASSIGN_REASSIGNED;
        }

        static::query()
            ->where('student_id', $student->id)
            ->where('is_active', true)
            ->update(['is_active' => false, 'deactivated_at' => now()]);

        static::create([
            'student_id' => $student->id,
            'card_uid' => $cardUid,
            'is_active' => true,
            'assigned_by' => $userId,
            'assigned_at' => now(),
        ]);

        return static::ASSIGN_CREATED;
    }
}

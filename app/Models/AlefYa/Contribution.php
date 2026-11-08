<?php

namespace App\Models\AlefYa;

use App\Support\AlefYaLetters;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contribution extends Model
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    public const STATUSES = [
        self::PENDING => 'بانتظار المراجعة',
        self::APPROVED => 'مقبول',
        self::REJECTED => 'مرفوض',
    ];

    protected static function booted(): void
    {
        static::saving(function (Contribution $c) {
            $c->letter = AlefYaLetters::of($c->answer) ?? ($c->letter ?: '؟');
        });
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /** Adds it to the bank (with the contributor's name) and marks it approved. */
    public function approve(): Question
    {
        $question = $this->question ?? Question::create([
            'question' => $this->body,
            'answer' => $this->answer,
            'contributor' => $this->name ?: null,
            'category' => 'مساهمات',
        ]);

        if (! $question->options) {
            $question->options = $question->choices();
            $question->saveQuietly();
        }

        $this->forceFill(['status' => self::APPROVED, 'question_id' => $question->id])->save();

        return $question;
    }

    public function reject(): void
    {
        $this->forceFill(['status' => self::REJECTED])->save();
    }
}

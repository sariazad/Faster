<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Question extends Model
{
    use HasFactory;

    /** Uploaded files live on the public disk. */
    public const MEDIA = ['image', 'audio', 'answer_image', 'answer_audio'];

    protected $fillable = [
        'category_id',
        'points',
        'body',
        'image',
        'audio',
        'answer',
        'answer_image',
        'answer_audio',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Remove replaced or orphaned files from the public disk.
        static::updated(function (Question $question) {
            foreach (self::MEDIA as $field) {
                $old = $question->getOriginal($field);
                if ($old && $question->wasChanged($field)) {
                    Storage::disk('public')->delete($old);
                }
            }
        });

        static::deleted(function (Question $question) {
            Storage::disk('public')->delete(array_filter(array_map(fn ($f) => $question->{$f}, self::MEDIA)));
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function mediaUrl(string $field): ?string
    {
        $path = $this->getAttribute($field);

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public function imageUrl(): ?string
    {
        return $this->mediaUrl('image');
    }

    public function audioUrl(): ?string
    {
        return $this->mediaUrl('audio');
    }

    public function answerImageUrl(): ?string
    {
        return $this->mediaUrl('answer_image');
    }

    public function answerAudioUrl(): ?string
    {
        return $this->mediaUrl('answer_audio');
    }

    public function hasAnswerMedia(): bool
    {
        return (bool) ($this->answer_image || $this->answer_audio);
    }
}

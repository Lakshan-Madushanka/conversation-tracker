<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FeedbackType;
use App\Support\SearchText;
use Carbon\CarbonImmutable;
use Database\Factories\NotebookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A notebook the learner keeps: one board of vocabulary, phrases and grammar
 * notes under a shared topic. It is not a session — nothing here finishes,
 * archives, or counts as a finished practice.
 *
 * @property int $id
 * @property string $name
 * @property int $topic_id
 * @property string|null $description_html
 * @property string|null $description_text
 * @property string|null $search_text
 * @property int $version
 * @property string|null $client_key
 * @property string|null $last_save_key
 * @property CarbonImmutable|null $last_changed_at
 * @property Topic $topic
 * @property Collection<int, PracticeFeedbackEntry> $entries
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'topic_id', 'description_html', 'description_text', 'client_key'])]
final class Notebook extends Model
{
    /** @use HasFactory<NotebookFactory> */
    use HasFactory;

    /**
     * Mirror the database default so a newly created notebook already reports
     * version 1 to the editor, without a reload.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'version' => 1,
    ];

    protected static function booted(): void
    {
        self::saving(function (self $notebook): void {
            $notebook->search_text = SearchText::normalize(
                $notebook->name.' '.$notebook->description_text,
            );
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'last_changed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Topic, $this>
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    /**
     * The board's rows, grouped by box and kept in the order the learner
     * saved them, the same way a question's entries read.
     *
     * @return HasMany<PracticeFeedbackEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(PracticeFeedbackEntry::class)
            ->orderBy('type')
            ->orderBy('position')
            ->orderBy('id');
    }

    /**
     * How much the board holds, counted from its entries so the numbers stay
     * correct after later edits. Blank rows never count.
     *
     * @return array{words: int, phrases: int, grammar_notes: int}
     */
    public function counts(): array
    {
        $this->loadMissing('entries');

        $counts = ['words' => 0, 'phrases' => 0, 'grammar_notes' => 0];

        foreach ($this->entries as $entry) {
            if ($entry->isBlank()) {
                continue;
            }

            match ($entry->type) {
                FeedbackType::Vocabulary => $counts['words']++,
                FeedbackType::Phrase => $counts['phrases']++,
                FeedbackType::GrammarNote => $counts['grammar_notes']++,
                default => null,
            };
        }

        return $counts;
    }
}

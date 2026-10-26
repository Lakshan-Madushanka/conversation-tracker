<?php

declare(strict_types=1);

use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;
use App\Support\EntrySearch;

/**
 * @param  array<string, mixed>  $attributes
 */
function searchableEntry(FeedbackType $type, array $attributes = []): PracticeFeedbackEntry
{
    return new PracticeFeedbackEntry([
        'type' => $type,
        ...$attributes,
    ]);
}

it('searches a rich note by its words only', function (): void {
    $entry = searchableEntry(FeedbackType::GrammarNote, [
        'content_text' => 'Use the past tense.',
        'content_html' => '<p>Use the <strong>past tense</strong>.</p>',
        'correction_text' => 'ignored',
        'reason' => 'ignored',
    ]);

    expect(EntrySearch::fields($entry))->toBe(['content' => 'Use the past tense.'])
        ->and(EntrySearch::text($entry))->toBe('use the past tense.');
});

it('searches every column of a mistake row together', function (): void {
    $entry = searchableEntry(FeedbackType::Mistake, [
        'content_text' => 'I go',
        'correction_text' => 'I went',
        'reason' => 'past tense',
    ]);

    expect(EntrySearch::fields($entry))->toBe([
        'content' => 'I go',
        'correction' => 'I went',
        'reason' => 'past tense',
    ])->and(EntrySearch::text($entry))->toBe('i go i went past tense');
});

it('adds the example of a vocabulary row to its searchable text', function (): void {
    $entry = searchableEntry(FeedbackType::Vocabulary, [
        'content_text' => 'journey',
        'correction_text' => 'a trip',
        'example' => 'It was a long journey.',
    ]);

    expect(EntrySearch::fields($entry))->toBe([
        'content' => 'journey',
        'correction' => 'a trip',
        'example' => 'It was a long journey.',
    ]);
});

it('falls back to the example entries when the plain column is empty', function (): void {
    $entry = searchableEntry(FeedbackType::Phrase, [
        'content_text' => 'a long way',
        'correction_text' => 'far',
        'example' => '',
        'example_html' => [
            ['id' => 'e1', 'text' => 'It is a long way.', 'html' => '<p>It is a long way.</p>'],
            ['id' => 'e2', 'text' => 'We came a long way.', 'html' => '<p>We came a long way.</p>'],
        ],
    ]);

    expect(EntrySearch::fields($entry)['example'])->toBe('It is a long way. We came a long way.');
});

it('drops the columns that hold nothing', function (): void {
    $entry = searchableEntry(FeedbackType::Vocabulary, [
        'content_text' => 'journey',
        'correction_text' => '   ',
        'reason' => '',
    ]);

    expect(EntrySearch::fields($entry))->toBe(['content' => 'journey']);
});

it('picks the field that holds the most search terms', function (): void {
    $entry = searchableEntry(FeedbackType::Mistake, [
        'content_text' => 'I go',
        'correction_text' => 'I went to the beach',
        'reason' => 'past tense',
    ]);

    expect(EntrySearch::bestField($entry, ['went', 'beach']))->toBe([
        'field' => 'correction',
        'text' => 'I went to the beach',
    ]);
});

it('keeps the first field when two hold the same terms', function (): void {
    $entry = searchableEntry(FeedbackType::Mistake, [
        'content_text' => 'went',
        'correction_text' => 'went',
    ]);

    expect(EntrySearch::bestField($entry, ['went']))->toBe([
        'field' => 'content',
        'text' => 'went',
    ]);
});

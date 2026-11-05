<?php

declare(strict_types=1);

use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;
use App\Support\BlankExercise;

/**
 * An entry kept in memory, shaped the way the editor saves one.
 *
 * @param  array<string, mixed>  $attributes
 */
function blankExerciseEntry(array $attributes = []): PracticeFeedbackEntry
{
    return new PracticeFeedbackEntry(array_merge([
        'type' => FeedbackType::Vocabulary,
        'content_html' => '',
        'content_text' => '',
        'correction_html' => '',
        'correction_text' => '',
        'reason' => '',
        'example' => '',
        'example_html' => [],
        'position' => 0,
        'is_split' => true,
    ], $attributes));
}

/** The safe markup the prompt uses where the missing word was. */
function blankExerciseSpan(): string
{
    return '<span data-blank="" role="img" aria-label="missing word">_</span>';
}

describe('vocabulary examples', function (): void {
    it('blanks the vocabulary word and keeps the rest of the sentence', function (): void {
        $blank = BlankExercise::build(blankExerciseEntry([
            'content_text' => 'serendipity',
            'example' => 'A lovely serendipity moment.',
        ]));

        expect($blank)->not->toBeNull()
            ->and($blank['prompt_html'])->toBe('<p>A lovely '.blankExerciseSpan().' moment.</p>')
            ->and($blank['answer_html'])->toBe('<p>A lovely <mark>serendipity</mark> moment.</p>')
            ->and($blank['answer_text'])->toBe('A lovely serendipity moment.')
            ->and($blank['hint'])->toBeNull()
            ->and($blank['hint_label'])->toBeNull();
    });

    it('shows the missing word position as an underscore, never in the answer', function (): void {
        $blank = BlankExercise::build(blankExerciseEntry([
            'content_text' => 'serendipity',
            'example' => 'A lovely serendipity moment.',
        ]));

        expect($blank['prompt_html'])->toContain('aria-label="missing word">_</span>')
            ->and($blank['answer_html'])->not->toContain('_');
    });

    it('keeps the formatting saved in the example', function (): void {
        $blank = BlankExercise::build(blankExerciseEntry([
            'content_text' => 'serendipity',
            'example' => 'A lovely serendipity moment.',
            'example_html' => [
                ['id' => 'one', 'text' => 'A lovely serendipity moment.', 'html' => 'A <em>lovely</em> serendipity moment.'],
            ],
        ]));

        expect($blank['prompt_html'])
            ->toBe('<p>A <em>lovely</em> '.blankExerciseSpan().' moment.</p>')
            ->and($blank['answer_html'])->toBe('<p>A <em>lovely</em> <mark>serendipity</mark> moment.</p>');
    });

    it('skips a word with no example to put it in', function (): void {
        $blank = BlankExercise::build(blankExerciseEntry([
            'content_text' => 'serendipity',
        ]));

        expect($blank)->toBeNull();
    });

    it('skips a word that does not appear in its example', function (): void {
        $blank = BlankExercise::build(blankExerciseEntry([
            'content_text' => 'serendipity',
            'example' => 'A lovely moment.',
        ]));

        expect($blank)->toBeNull();
    });

    it('keeps non-English letters and special characters', function (): void {
        $blank = BlankExercise::build(blankExerciseEntry([
            'content_text' => 'café',
            'example' => 'I love café au lait.',
        ]));

        expect($blank['prompt_html'])->toBe('<p>I love '.blankExerciseSpan().' au lait.</p>')
            ->and($blank['answer_html'])->toBe('<p>I love <mark>café</mark> au lait.</p>')
            ->and($blank['answer_text'])->toBe('I love café au lait.');
    });
});

describe('phrases', function (): void {
    it('blanks a key word of the example, preferring a word from the phrase', function (): void {
        $blank = BlankExercise::build(blankExerciseEntry([
            'type' => FeedbackType::Phrase,
            'content_text' => 'break the ice',
            'example' => 'She broke the ice at the party.',
        ]));

        expect($blank)->not->toBeNull()
            ->and($blank['prompt_html'])->toBe('<p>She broke the '.blankExerciseSpan().' at the party.</p>')
            ->and($blank['answer_html'])->toBe('<p>She broke the <mark>ice</mark> at the party.</p>')
            ->and($blank['answer_text'])->toBe('She broke the ice at the party.')
            ->and($blank['hint'])->toBeNull()
            ->and($blank['hint_label'])->toBeNull();
    });

    it('blanks a key word of the phrase itself when there is no example', function (): void {
        $blank = BlankExercise::build(blankExerciseEntry([
            'type' => FeedbackType::Phrase,
            'content_text' => 'break the ice',
        ]));

        expect($blank['prompt_html'])->toBe('<p>'.blankExerciseSpan().' the ice</p>')
            ->and($blank['answer_html'])->toBe('<p><mark>break</mark> the ice</p>')
            ->and($blank['answer_text'])->toBe('break the ice');
    });

    it('does not use a one-word phrase, which has nothing left to show', function (): void {
        $blank = BlankExercise::build(blankExerciseEntry([
            'type' => FeedbackType::Phrase,
            'content_text' => 'hello',
        ]));

        expect($blank)->toBeNull();
    });
});

describe('mistake corrections', function (): void {
    it('blanks every correction word that changed and shows the reason as a hint', function (): void {
        $blank = BlankExercise::build(
            blankExerciseEntry([
                'type' => FeedbackType::Mistake,
                'content_text' => 'I has went there.',
                'correction_html' => 'I have gone there.',
                'correction_text' => 'I have gone there.',
                'reason' => 'Use the present perfect.',
            ]),
            reason: 'Use the present perfect.',
        );

        expect($blank)->not->toBeNull()
            ->and($blank['prompt_html'])
            ->toBe('<p>I '.blankExerciseSpan().' '.blankExerciseSpan().' there.</p>')
            ->and($blank['answer_html'])
            ->toBe('<p>I <mark>have</mark> <mark>gone</mark> there.</p>')
            ->and($blank['answer_text'])->toBe('I have gone there.')
            ->and($blank['hint'])->toBe('Use the present perfect.')
            ->and($blank['hint_label'])->toBe('Why');
    });

    it('falls back to the saved reason when the caller read none', function (): void {
        $blank = BlankExercise::build(blankExerciseEntry([
            'type' => FeedbackType::Mistake,
            'content_text' => 'I has went there.',
            'correction_html' => 'I have gone there.',
            'correction_text' => 'I have gone there.',
            'reason' => 'Use the present perfect.',
        ]));

        expect($blank['hint'])->toBe('Use the present perfect.')
            ->and($blank['hint_label'])->toBe('Why');
    });

    it('has no hint when the mistake carries no reason', function (): void {
        $blank = BlankExercise::build(blankExerciseEntry([
            'type' => FeedbackType::Mistake,
            'content_text' => 'I has went there.',
            'correction_html' => 'I have gone there.',
            'correction_text' => 'I have gone there.',
        ]));

        expect($blank['hint'])->toBeNull()
            ->and($blank['hint_label'])->toBeNull();
    });

    it('skips a correction with more than four changed words', function (): void {
        $blank = BlankExercise::build(blankExerciseEntry([
            'type' => FeedbackType::Mistake,
            'content_text' => 'one two three four five six keep',
            'correction_html' => 'uno dos tres cuatro cinco seis keep',
            'correction_text' => 'uno dos tres cuatro cinco seis keep',
        ]));

        expect($blank)->toBeNull();
    });

    it('skips a correction where nothing changed', function (): void {
        $blank = BlankExercise::build(blankExerciseEntry([
            'type' => FeedbackType::Mistake,
            'content_text' => 'I have gone there.',
            'correction_html' => 'I have gone there.',
            'correction_text' => 'I have gone there.',
        ]));

        expect($blank)->toBeNull();
    });

    it('skips a correction where every word changed', function (): void {
        $blank = BlankExercise::build(blankExerciseEntry([
            'type' => FeedbackType::Mistake,
            'content_text' => 'I has went there.',
            'correction_html' => 'We have gone home.',
            'correction_text' => 'We have gone home.',
        ]));

        expect($blank)->toBeNull();
    });
});

describe('grammar notes', function (): void {
    it('blanks a bold part of one word and names the session topic', function (): void {
        $blank = BlankExercise::build(blankExerciseEntry([
            'type' => FeedbackType::GrammarNote,
            'content_html' => '<p>Use the <strong>past</strong> tense here.</p>',
            'content_text' => 'Use the past tense here.',
        ]), 'Past perfect');

        expect($blank)->not->toBeNull()
            ->and($blank['prompt_html'])
            ->toBe('<p>Use the <strong>'.blankExerciseSpan().'</strong> tense here.</p>')
            ->and($blank['answer_html'])
            ->toBe('<p>Use the <mark><strong>past</strong></mark> tense here.</p>')
            ->and($blank['answer_text'])->toBe('Use the past tense here.')
            ->and($blank['hint'])->toBe('Past perfect')
            ->and($blank['hint_label'])->toBe('Topic');
    });

    it('accepts a bold part of exactly four words', function (): void {
        $blank = BlankExercise::build(blankExerciseEntry([
            'type' => FeedbackType::GrammarNote,
            'content_html' => '<p>Remember <strong>this is very long</strong> enough.</p>',
            'content_text' => 'Remember this is very long enough.',
        ]));

        expect($blank)->not->toBeNull()
            ->and($blank['prompt_html'])
            ->toBe('<p>Remember <strong>'.blankExerciseSpan().'</strong> enough.</p>')
            ->and($blank['answer_html'])
            ->toBe('<p>Remember <mark><strong>this is very long</strong></mark> enough.</p>');
    });

    it('uses the first qualifying bold part when a note has several', function (): void {
        $blank = BlankExercise::build(blankExerciseEntry([
            'type' => FeedbackType::GrammarNote,
            'content_html' => '<p><strong>one two three four five</strong> then <strong>first good</strong> and <strong>second good</strong>.</p>',
            'content_text' => 'one two three four five then first good and second good.',
        ]));

        expect($blank['prompt_html'])
            ->toBe('<p><strong>one two three four five</strong> then <strong>'.blankExerciseSpan().'</strong> and <strong>second good</strong>.</p>');
    });

    it('returns the same blank on every call', function (): void {
        $entry = blankExerciseEntry([
            'type' => FeedbackType::GrammarNote,
            'content_html' => '<p>Use the <strong>past tense</strong> here.</p>',
            'content_text' => 'Use the past tense here.',
        ]);

        expect(BlankExercise::build($entry, 'Past'))->toBe(BlankExercise::build($entry, 'Past'));
    });

    it('skips a note whose bold part is longer than four words', function (): void {
        $blank = BlankExercise::build(blankExerciseEntry([
            'type' => FeedbackType::GrammarNote,
            'content_html' => '<p>Some <strong>one two three four five</strong> text.</p>',
            'content_text' => 'Some one two three four five text.',
        ]));

        expect($blank)->toBeNull();
    });

    it('skips a note that is entirely bold', function (): void {
        $blank = BlankExercise::build(blankExerciseEntry([
            'type' => FeedbackType::GrammarNote,
            'content_html' => '<p><strong>All of this</strong></p>',
            'content_text' => 'All of this',
        ]));

        expect($blank)->toBeNull();
    });

    it('uses a quoted example when the note has no bold part', function (): void {
        $blank = BlankExercise::build(blankExerciseEntry([
            'type' => FeedbackType::GrammarNote,
            'content_html' => '<p>Remember: “practice makes perfect” every day.</p>',
            'content_text' => 'Remember: “practice makes perfect” every day.',
        ]), 'Practice');

        expect($blank)->not->toBeNull()
            ->and($blank['prompt_html'])
            ->toBe('<p>Remember: “'.blankExerciseSpan().' makes perfect” every day.</p>')
            ->and($blank['answer_html'])
            ->toBe('<p>Remember: “<mark>practice</mark> makes perfect” every day.</p>')
            ->and($blank['answer_text'])->toBe('Remember: “practice makes perfect” every day.')
            ->and($blank['hint'])->toBe('Practice')
            ->and($blank['hint_label'])->toBe('Topic');
    });

    it('skips a note with no bold part and no quoted example', function (): void {
        $blank = BlankExercise::build(blankExerciseEntry([
            'type' => FeedbackType::GrammarNote,
            'content_html' => '<p>Just a plain note about grammar.</p>',
            'content_text' => 'Just a plain note about grammar.',
        ]));

        expect($blank)->toBeNull();
    });

    it('shows only the list item that holds the blank during the exercise', function (): void {
        $blank = BlankExercise::build(blankExerciseEntry([
            'type' => FeedbackType::GrammarNote,
            'content_html' => '<ul><li>Use <strong>past</strong> here.</li><li>Another point to keep.</li></ul>',
            'content_text' => 'Use past here. Another point to keep.',
        ]));

        expect($blank['prompt_html'])
            ->toBe('<ul><li>Use <strong>'.blankExerciseSpan().'</strong> here.</li></ul>')
            ->and($blank['answer_html'])
            ->toBe('<ul><li>Use <mark><strong>past</strong></mark> here.</li><li>Another point to keep.</li></ul>');
    });

    it('shows the full note with the mark when checking', function (): void {
        $blank = BlankExercise::build(blankExerciseEntry([
            'type' => FeedbackType::GrammarNote,
            'content_html' => '<p>First paragraph.</p><p>Use the <strong>past</strong> here.</p>',
            'content_text' => 'First paragraph. Use the past here.',
        ]));

        expect($blank['answer_html'])
            ->toBe('<p>First paragraph.</p><p>Use the <mark><strong>past</strong></mark> here.</p>');
    });

    it('strips unsafe saved markup from the prompt and the answer', function (): void {
        $blank = BlankExercise::build(blankExerciseEntry([
            'type' => FeedbackType::GrammarNote,
            'content_html' => '<p onclick="alert(1)">Use <strong>past</strong> here.<script>alert(2)</script></p>',
            'content_text' => 'Use past here.',
        ]));

        expect($blank['prompt_html'])
            ->toBe('<p>Use <strong>'.blankExerciseSpan().'</strong> here.</p>')
            ->and($blank['answer_html'])
            ->toBe('<p>Use <mark><strong>past</strong></mark> here.</p>')
            ->and($blank['prompt_html'].$blank['answer_html'])
            ->not->toContain('script')
            ->not->toContain('onclick')
            ->not->toContain('alert');
    });

    it('has no topic hint when no topic is known', function (): void {
        $blank = BlankExercise::build(blankExerciseEntry([
            'type' => FeedbackType::GrammarNote,
            'content_html' => '<p>Use the <strong>past tense</strong> here.</p>',
            'content_text' => 'Use the past tense here.',
        ]));

        expect($blank['hint'])->toBeNull()
            ->and($blank['hint_label'])->toBeNull();
    });
});

describe('other saved kinds', function (): void {
    it('does not build a blank from a past question', function (): void {
        $blank = BlankExercise::build(blankExerciseEntry([
            'type' => FeedbackType::Question,
            'content_text' => 'What did you do last weekend?',
        ]));

        expect($blank)->toBeNull();
    });
});

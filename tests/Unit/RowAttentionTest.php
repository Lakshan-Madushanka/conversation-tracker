<?php

declare(strict_types=1);

use App\Enums\FeedbackType;
use App\Support\RowAttention;

/**
 * A subject no learner would split further, saved the way the splitter stores it.
 */
it('stays calm when the attention column holds anything', function (): void {
    expect(RowAttention::reason(
        FeedbackType::Vocabulary,
        'reluctant',
        'unwilling to do something',
        '',
    ))->toBeNull()
        ->and(RowAttention::reason(
            FeedbackType::Phrase,
            'catch up -> move fast to reach someone',
            '',
            '',
        ))->toBeNull()
        ->and(RowAttention::reason(
            FeedbackType::Mistake,
            'I go -> I went',
            'The past tense is "I went".',
            '',
        ))->toBeNull();
});

it('stays calm for a phrase whose meaning is simply missing', function (): void {
    expect(RowAttention::reason(FeedbackType::Phrase, 'catch up', '', ''))->toBeNull();
});

it('asks a vocabulary row to add its meaning', function (): void {
    expect(RowAttention::reason(FeedbackType::Vocabulary, 'relish', '', ''))
        ->toBe(RowAttention::ADD_MEANING);
});

it('asks a mistake row to add its correction', function (): void {
    expect(RowAttention::reason(FeedbackType::Mistake, 'I go', '', ''))
        ->toBe(RowAttention::ADD_CORRECTION);
});

it('names a line that held more than one separator', function (): void {
    $unsplittable = 'see -> watch -> observe';

    expect(RowAttention::reason(FeedbackType::Vocabulary, $unsplittable, '', ''))
        ->toBe(RowAttention::MORE_THAN_ONE)
        ->and(RowAttention::reason(FeedbackType::Phrase, $unsplittable, '', ''))
        ->toBe(RowAttention::MORE_THAN_ONE)
        ->and(RowAttention::reason(FeedbackType::Mistake, $unsplittable, '', ''))
        ->toBe(RowAttention::MORE_THAN_ONE);
});

it('lets a phrase with an example stay calm even when it could not be split', function (): void {
    expect(RowAttention::reason(FeedbackType::Phrase, 'see -> watch -> observe', '', 'I saw him go.'))
        ->toBeNull();
});

it('leaves a box it does not serve out of the rule', function (): void {
    expect(RowAttention::reason(FeedbackType::GrammarNote, 'relish', '', ''))
        ->toBeNull()
        ->and(RowAttention::reason(FeedbackType::NaturalVersion, 'I go', '', ''))
        ->toBeNull();
});

it('counts the separators of a cleaned line', function (string $line, int $expected): void {
    expect(RowAttention::countSeparators($line))->toBe($expected);
})->with([
    'no separator' => ['catch up', 0],
    'one arrow' => ['catch up -> reach someone', 1],
    'hyphenated words are not separators' => ['3-day stopover', 0],
    'one spaced dash' => ['reluctant - unwilling', 1],
    'two separators' => ['see -> watch -> observe', 2],
    'a leading bullet is not a separator' => ['- relish', 0],
]);

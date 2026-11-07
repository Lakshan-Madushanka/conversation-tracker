<?php

declare(strict_types=1);

use App\Enums\FeedbackType;
use App\Support\ReviewSectionBoard;

/**
 * One item as the board builds it.
 *
 * @return array{
 *     position: int,
 *     kind: FeedbackType,
 *     identity: string,
 *     exercise: string,
 *     card: array<string, mixed>,
 *     rating: string|null,
 *     skipped?: bool
 * }
 */
function stepFourBoardItem(
    string $exercise,
    int $position,
    ?string $rating = null,
    bool $skipped = false,
): array {
    return [
        'position' => $position,
        'kind' => FeedbackType::Vocabulary,
        'identity' => $exercise.'-'.$position,
        'exercise' => $exercise,
        'card' => ['front' => 'word '.$position],
        'rating' => $rating,
        'skipped' => $skipped,
    ];
}

it('leaves the items out of a step once it is only shown in the stepper', function (): void {
    $board = new ReviewSectionBoard;
    $sections = $board->build(
        [stepFourBoardItem('flashcard', 1)],
        fn (string $key, int $position): string => '/step/'.$key.'?position='.$position,
    );

    $summary = $board->summary($sections[0]);

    expect($summary)->not->toHaveKey('items')
        ->and($summary['key'])->toBe('flashcard')
        ->and($summary['total'])->toBe(1)
        ->and($summary['url'])->toBe('/step/flashcard?position=1');
});

it('points a finished step at the next unfinished one, wrapping around', function (): void {
    $board = new ReviewSectionBoard;
    $sections = $board->build([
        stepFourBoardItem('flashcard', 1, 'easy'),
        stepFourBoardItem('mistake', 1),
    ], fn (string $key, int $position): string => '/step/'.$key.'?position='.$position);

    expect($sections[0]['completed'])->toBeTrue()
        ->and($sections[0]['settled'])->toBe(1)
        ->and($sections[0]['rated'])->toBe(1)
        ->and($sections[1]['completed'])->toBeFalse();

    $completed = $board->completed($sections[0], $sections);

    expect($completed)->toBe([
        'key' => 'flashcard',
        'label' => 'Flashcards',
        'total' => 1,
        'counts' => ['hard' => 0, 'okay' => 0, 'easy' => 1],
        'next_url' => '/step/mistake?position=1',
    ]);

    // The last step is finished too, so there is nowhere left to go.
    $allDone = $board->build([
        stepFourBoardItem('flashcard', 1, 'easy'),
        stepFourBoardItem('mistake', 1, null, true),
    ], fn (string $key, int $position): string => '/step/'.$key.'?position='.$position);

    expect($board->completed($allDone[1], $allDone)['next_url'])->toBeNull()
        ->and($allDone[1]['completed'])->toBeTrue()
        ->and($allDone[1]['rated'])->toBe(0)
        ->and($allDone[1]['settled'])->toBe(1);
});

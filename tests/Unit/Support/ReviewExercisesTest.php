<?php

declare(strict_types=1);

use App\Enums\FeedbackType;
use App\Support\ExerciseKind;
use App\Support\ReviewExercises;

describe('the mix description', function (): void {
    it('describes flashcards with correct singular and plural wording', function (): void {
        expect(ReviewExercises::describe(['flashcard' => 8]))->toBe('8 flashcards')
            ->and(ReviewExercises::describe(['flashcard' => 1]))->toBe('1 flashcard')
            ->and(ReviewExercises::describe([]))->toBe('');
    });

    it('describes mistakes with correct singular and plural wording', function (): void {
        expect(ReviewExercises::describe(['mistake' => 3]))->toBe('3 mistakes to fix')
            ->and(ReviewExercises::describe(['mistake' => 1]))->toBe('1 mistake to fix');
    });

    it('grows automatically when a new exercise kind is added', function (): void {
        // A test double for the kinds Steps 4 and 5 will add.
        $kinds = [
            ...ReviewExercises::all(),
            new ExerciseKind('say_it_again', 'question to say again', 'questions to say again', 'Say it again', 'mic', 40),
        ];

        $description = ReviewExercises::describe(
            ['flashcard' => 3, 'mistake' => 3, 'say_it_again' => 2],
            $kinds,
        );

        expect($description)->toBe('3 flashcards, 3 mistakes to fix, 2 questions to say again');

        $description = ReviewExercises::describe(['flashcard' => 1, 'say_it_again' => 1], $kinds);

        expect($description)->toBe('1 flashcard, 1 question to say again');
    });

    it('describes a count it does not know by its key rather than dropping it', function (): void {
        expect(ReviewExercises::describe(['mystery' => 2]))->toBe('2 mystery');
    });
});

describe('the registry', function (): void {
    it('maps vocabulary and phrases to flashcards and mistakes to their own step', function (): void {
        expect(ReviewExercises::kindOf(FeedbackType::Vocabulary))->toBe('flashcard')
            ->and(ReviewExercises::kindOf(FeedbackType::Phrase))->toBe('flashcard')
            ->and(ReviewExercises::kindOf(FeedbackType::Mistake))->toBe('mistake');
    });

    it('finds each built kind by its key and nothing for an unbuilt one', function (): void {
        expect(ReviewExercises::find('flashcard')?->label)->toBe('Flashcards')
            ->and(ReviewExercises::find('mistake')?->label)->toBe('Fix the mistake')
            ->and(ReviewExercises::find('say_it_again'))->toBeNull();
    });
});

describe('the estimate', function (): void {
    it('is 35 seconds per flashcard, rounded up to whole minutes', function (): void {
        expect(ReviewExercises::minutes(['flashcard' => 8]))->toBe(5)
            ->and(ReviewExercises::minutes(['flashcard' => 2]))->toBe(2)
            ->and(ReviewExercises::minutes(['flashcard' => 1]))->toBe(1);
    });

    it('is a minute per mistake to fix, as mistakes take longer to read', function (): void {
        expect(ReviewExercises::minutes(['mistake' => 1]))->toBe(1)
            ->and(ReviewExercises::minutes(['mistake' => 3]))->toBe(3);
    });

    it('never says zero minutes, even for nothing', function (): void {
        expect(ReviewExercises::minutes([]))->toBe(1);
    });

    it('counts each kind at its own pace', function (): void {
        $kinds = [
            ...ReviewExercises::all(),
            new ExerciseKind('say_it_again', 'question to say again', 'questions to say again', 'Say it again', 'mic', 90),
        ];

        // 2 minutes for two flashcards (70s) and 3 minutes for two say-it-agains (180s):
        // 250 seconds in total, rounded up.
        expect(ReviewExercises::minutes(['flashcard' => 2, 'say_it_again' => 2], $kinds))->toBe(5);
    });
});

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
        // A test double for a kind the registry does not know yet.
        $kinds = [
            ...ReviewExercises::all(),
            new ExerciseKind('listen_and_repeat', 'listen and repeat', 'listens and repeats', 'Listen and repeat', 'headphones', 30),
        ];

        $description = ReviewExercises::describe(
            ['flashcard' => 3, 'mistake' => 3, 'listen_and_repeat' => 2],
            $kinds,
        );

        expect($description)->toBe('3 flashcards, 3 mistakes to fix, 2 listens and repeats');

        $description = ReviewExercises::describe(['flashcard' => 1, 'listen_and_repeat' => 1], $kinds);

        expect($description)->toBe('1 flashcard, 1 listen and repeat');
    });

    it('describes a count it does not know by its key rather than dropping it', function (): void {
        expect(ReviewExercises::describe(['mystery' => 2]))->toBe('2 mystery');
    });
});

describe('the registry', function (): void {
    it('maps vocabulary and phrases to flashcards, and the other kinds to their own steps', function (): void {
        expect(ReviewExercises::kindOf(FeedbackType::Vocabulary))->toBe('flashcard')
            ->and(ReviewExercises::kindOf(FeedbackType::Phrase))->toBe('flashcard')
            ->and(ReviewExercises::kindOf(FeedbackType::Mistake))->toBe('mistake')
            ->and(ReviewExercises::kindOf(FeedbackType::Question))->toBe('say_it_again')
            ->and(ReviewExercises::kindOf(FeedbackType::GrammarNote))->toBe('fill_in_the_blank');
    });

    it('finds each built kind by its key', function (): void {
        expect(ReviewExercises::find('flashcard')?->label)->toBe('Flashcards')
            ->and(ReviewExercises::find('mistake')?->label)->toBe('Fix the mistake')
            ->and(ReviewExercises::find('say_it_again')?->label)->toBe('Say it again')
            ->and(ReviewExercises::find('fill_in_the_blank')?->label)->toBe('Fill in the blank')
            ->and(ReviewExercises::find('fill_in_the_blank')?->icon)->toBe('text-cursor-input')
            ->and(ReviewExercises::find('fill_in_the_blank')?->seconds)->toBe(40)
            ->and(ReviewExercises::find('fill_in_the_blank')?->singular)->toBe('blank to fill in')
            ->and(ReviewExercises::find('fill_in_the_blank')?->plural)->toBe('blanks to fill in');
    });

    it('keeps the built kinds in the friendly stepper order', function (): void {
        expect(array_map(
            fn (ExerciseKind $kind): string => $kind->key,
            ReviewExercises::all(),
        ))->toBe(['flashcard', 'mistake', 'say_it_again', 'fill_in_the_blank']);
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
        // 2 minutes for two flashcards (70s) and 3 minutes for two questions
        // (150s): 220 seconds in total, rounded up.
        expect(ReviewExercises::minutes(['flashcard' => 2, 'say_it_again' => 2]))->toBe(4);
    });

    it('gives a single question its own honest estimate', function (): void {
        // 75 seconds rounds up to two minutes; a question takes longer to
        // answer out loud than a flashcard does.
        expect(ReviewExercises::minutes(['say_it_again' => 1]))->toBe(2)
            ->and(ReviewExercises::minutes(['say_it_again' => 4]))->toBe(5);
    });

    it('gives a blank its shorter writing time', function (): void {
        // 40 seconds rounds up to one minute; three blanks take 120 seconds,
        // which is two minutes.
        expect(ReviewExercises::minutes(['fill_in_the_blank' => 1]))->toBe(1)
            ->and(ReviewExercises::minutes(['fill_in_the_blank' => 3]))->toBe(2);
    });

    it('adds each kind at its own pace to the same estimate', function (): void {
        // 35 seconds for a flashcard and 40 for a blank: 75 seconds in total.
        expect(ReviewExercises::minutes(['flashcard' => 1, 'fill_in_the_blank' => 1]))->toBe(2)
            ->and(ReviewExercises::minutes(['mistake' => 1, 'fill_in_the_blank' => 1]))->toBe(2);
    });
});

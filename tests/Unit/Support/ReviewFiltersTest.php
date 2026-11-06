<?php

declare(strict_types=1);

use App\Enums\FeedbackType;
use App\Enums\ReviewCollection;
use App\Support\ReviewFilters;

describe('what the choices cover', function (): void {
    it('leaves every kind open when no collection is chosen', function (): void {
        $filters = ReviewFilters::custom();

        expect($filters->types())->toBeNull()
            ->and($filters->topicFilter())->toBeNull()
            ->and($filters->setCount())->toBe(0);
    });

    it('names the kinds the chosen collections hold', function (): void {
        $filters = ReviewFilters::custom(collections: [ReviewCollection::Mistakes, ReviewCollection::Words]);

        expect($filters->types())->toBe([
            FeedbackType::Mistake,
            FeedbackType::Vocabulary,
        ])->and($filters->allows(FeedbackType::Vocabulary))->toBeTrue()
            ->and($filters->allows(FeedbackType::Question))->toBeFalse();
    });

    it('leaves past questions and grammar notes out of a starred-only sitting', function (): void {
        $starred = ReviewFilters::custom(starredOnly: true);

        expect($starred->types())->toBe(FeedbackType::markable())
            ->and($starred->allows(FeedbackType::Question))->toBeFalse()
            ->and($starred->allows(FeedbackType::GrammarNote))->toBeFalse()
            ->and($starred->allows(FeedbackType::Phrase))->toBeTrue();
    });

    it('narrows a starred-only sitting further when collections are chosen too', function (): void {
        $filters = ReviewFilters::custom(
            collections: [ReviewCollection::Questions, ReviewCollection::Words],
            starredOnly: true,
        );

        // The questions carry no star, so only the words are left.
        expect($filters->types())->toBe([FeedbackType::Vocabulary]);
    });

    it('leaves a starred-only sitting empty when only unstarred kinds are chosen', function (): void {
        $filters = ReviewFilters::custom(
            collections: [ReviewCollection::Questions],
            starredOnly: true,
        );

        expect($filters->types())->toBe([])
            ->and($filters->allows(FeedbackType::Question))->toBeFalse();
    });
});

describe('the daily rules', function (): void {
    it('restrict and add nothing', function (): void {
        $filters = ReviewFilters::daily();

        expect($filters->custom)->toBeFalse()
            ->and($filters->types())->toBeNull()
            ->and($filters->starredOnly)->toBeFalse()
            ->and($filters->includeLearned)->toBeFalse()
            ->and($filters->topicFilter())->toBeNull()
            ->and($filters->toQuery())->toBe([]);
    });
});

describe('the address the choices travel in', function (): void {
    it('lists the topics and collections it was given, and nothing that is default', function (): void {
        $filters = ReviewFilters::custom(
            topicIds: [7, 3],
            collections: [ReviewCollection::Words, ReviewCollection::Phrases],
            starredOnly: true,
            includeLearned: true,
        );

        expect($filters->toQuery())->toBe([
            'topics' => '7,3',
            'collections' => 'words,phrases',
            'starred' => '1',
            'learned' => '1',
        ])->and($filters->topicFilter())->toBe([7, 3])
            ->and($filters->setCount())->toBe(6);
    });

    it('keeps a topic or a collection named twice down to one', function (): void {
        $filters = ReviewFilters::custom(
            topicIds: [4, 4, 9],
            collections: [ReviewCollection::Words, ReviewCollection::Words],
        );

        expect($filters->topicIds)->toBe([4, 9])
            ->and($filters->topicFilter())->toBe([4, 9])
            ->and($filters->collections)->toBe([ReviewCollection::Words])
            ->and($filters->toQuery())->toBe(['topics' => '4,9', 'collections' => 'words']);
    });

    it('counts only the choices that are really set', function (): void {
        expect(ReviewFilters::custom(topicIds: [3])->setCount())->toBe(1)
            ->and(ReviewFilters::custom(collections: [ReviewCollection::Mistakes])->setCount())->toBe(1)
            ->and(ReviewFilters::custom(includeLearned: true)->setCount())->toBe(1)
            ->and(ReviewFilters::custom()->setCount())->toBe(0);
    });
});

describe('the collections', function (): void {
    it('are the five the page lists, in the learner’s order', function (): void {
        expect(array_map(fn (ReviewCollection $c): string => $c->value, ReviewCollection::ordered()))
            ->toBe(['words', 'phrases', 'grammar_notes', 'mistakes', 'questions'])
            ->and(ReviewCollection::Words->label())->toBe('Words')
            ->and(ReviewCollection::Questions->label())->toBe('Past questions')
            ->and(ReviewCollection::GrammarNotes->type())->toBe(FeedbackType::GrammarNote);
    });

    it('cover every kind a review can hold', function (): void {
        expect(ReviewCollection::types(ReviewCollection::ordered()))->toBe([
            FeedbackType::Vocabulary,
            FeedbackType::Phrase,
            FeedbackType::GrammarNote,
            FeedbackType::Mistake,
            FeedbackType::Question,
        ]);
    });
});

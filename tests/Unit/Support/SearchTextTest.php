<?php

declare(strict_types=1);

use App\Support\SearchText;

it('normalises case, accents and extra spaces', function (): void {
    expect(SearchText::normalize("  Café   au\tLAIT \n"))->toBe('cafe au lait')
        ->and(SearchText::normalize('Crème brûlée'))->toBe('creme brulee')
        ->and(SearchText::normalize('  '))->toBe('');
});

it('turns a search into the words it asks for', function (): void {
    expect(SearchText::terms('  Café   AU lait '))->toBe(['cafe', 'au', 'lait'])
        ->and(SearchText::terms('cafe cafe'))->toBe(['cafe'])
        ->and(SearchText::terms('   '))->toBe([]);
});

it('escapes the wildcards a learner typed', function (): void {
    expect(SearchText::likePattern('100%'))->toBe('%100!%%')
        ->and(SearchText::likePattern('a_b'))->toBe('%a!_b%')
        ->and(SearchText::likePattern('wow!'))->toBe('%wow!!%');
});

it('asks for every word in the same piece of text', function (): void {
    $terms = SearchText::terms('went beach');

    expect(SearchText::matches('I went to the beach', $terms))->toBeTrue()
        ->and(SearchText::matches('I went home', $terms))->toBeFalse()
        ->and(SearchText::matches('I went to the beach', []))->toBeFalse();
});

it('keeps the original spelling in a snippet and marks the match', function (): void {
    $segments = SearchText::snippet('I went to the café yesterday', ['cafe']);

    expect(implode('', array_column($segments, 'text')))
        ->toBe('I went to the café yesterday')
        ->and(collect($segments)->firstWhere('match', true)['text'])->toBe('café');
});

it('marks every matching word in a snippet', function (): void {
    $segments = SearchText::snippet('I went to the beach', ['went', 'beach']);

    $matches = array_values(array_filter(
        $segments,
        fn (array $segment): bool => $segment['match'],
    ));

    expect(array_column($matches, 'text'))->toBe(['went', 'beach']);
});

it('shortens a long snippet and says so', function (): void {
    $text = str_repeat('before ', 20).'needle'.str_repeat(' after', 20);

    $segments = SearchText::snippet($text, ['needle'], 10);
    $snippet = implode('', array_column($segments, 'text'));

    expect($snippet)->toContain('needle')
        ->and($snippet)->toStartWith('…')
        ->and($snippet)->toEndWith('…')
        ->and(mb_strlen($snippet))->toBeLessThan(mb_strlen($text));
});

it('treats symbols and non-English letters as ordinary text', function (): void {
    $segments = SearchText::snippet('He said "100% (sure)" — привет', ['100%']);

    expect(collect($segments)->firstWhere('match', true)['text'])->toBe('100%')
        ->and(SearchText::matches('Он сказал привет', SearchText::terms('привет')))
        ->toBeTrue();
});

it('returns plain text when nothing matches', function (): void {
    expect(SearchText::snippet('A quiet line', ['missing']))
        ->toBe([['text' => 'A quiet line', 'match' => false]]);
});

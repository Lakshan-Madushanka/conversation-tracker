<?php

declare(strict_types=1);

use App\Models\PracticeQuestion;

function emptyQuestion(): PracticeQuestion
{
    return PracticeQuestion::factory()->create([
        'question_html' => '',
        'question_text' => '',
        'answer_html' => '',
        'answer_text' => '',
    ]);
}

test('saving a question keeps a normalised copy of its overall feedback', function (): void {
    $question = emptyQuestion();

    $question->feedback_html = '<p>Great use of the <strong>past tense</strong>.</p>';
    $question->feedback_text = "Great use of the past tense.\nNext: watch your articles.";
    $question->save();

    expect($question->fresh()?->feedback_search_text)
        ->toBe('great use of the past tense. next: watch your articles.');
});

test('a question holding only overall feedback has content', function (): void {
    $question = emptyQuestion();

    $question->feedback_html = '<p>Good try — use the past tense next time.</p>';
    $question->feedback_text = 'Good try — use the past tense next time.';
    $question->save();

    expect($question->fresh()?->load('feedbackEntries')->hasContent())->toBeTrue();
});

test('a question with no question, answer, overall feedback or entries is empty', function (): void {
    $question = emptyQuestion();

    expect($question->fresh()?->load('feedbackEntries')->hasContent())->toBeFalse();
});

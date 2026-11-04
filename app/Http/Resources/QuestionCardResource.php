<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Support\RichText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One say-it-again card as the review screen shows it: the tutor's question,
 * and the more natural version revealed on request.
 *
 * Both pieces are read from the session when the card is shown and reduced to
 * the small formatting set the app supports, so saved markup can never be
 * injected into the page. The question's identity is its own row id, so the
 * same words in two sessions remain two items.
 *
 * @mixin PracticeQuestion
 */
final class QuestionCardResource extends JsonResource
{
    public function __construct(PracticeQuestion $question, public readonly PracticeFeedbackEntry $natural)
    {
        parent::__construct($question);
    }

    /**
     * @return array{
     *     kind: string,
     *     exercise: string,
     *     item_identity: string,
     *     label: string,
     *     question_html: string,
     *     question_text: string,
     *     natural_html: string,
     *     natural_text: string,
     *     session_url: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var PracticeQuestion $question */
        $question = $this->resource;

        $session = $question->practiceSession;

        return [
            'kind' => FeedbackType::Question->value,
            'exercise' => 'say_it_again',
            'item_identity' => (string) $question->id,
            'label' => 'Say it again',
            'question_html' => $this->safeHtml((string) $question->question_html, (string) $question->question_text),
            'question_text' => (string) $question->question_text,
            'natural_html' => $this->safeHtml((string) $this->natural->content_html, (string) $this->natural->content_text),
            'natural_text' => (string) $this->natural->content_text,
            'session_url' => $session instanceof PracticeSession
                ? route('sessions.show', [
                    'session' => $session->id,
                    'question' => (string) $question->position,
                    'field' => 'question',
                ])
                : null,
        ];
    }

    /**
     * The safe formatted copy, or the plain words escaped when the saved
     * markup holds nothing readable (an older row, for example).
     */
    private function safeHtml(string $html, string $text): string
    {
        $clean = RichText::clean($html);

        if ($clean !== '') {
            return $clean;
        }

        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<p>'.nl2br($escaped, false).'</p>';
    }
}

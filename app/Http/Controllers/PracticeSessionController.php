<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePracticeSessionRequest;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Topic;
use App\Support\RichText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PracticeSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('sessions/Create', [
            'topics' => $this->topicOptions(),
            'session' => null,
        ]);
    }

    public function store(StorePracticeSessionRequest $request): RedirectResponse
    {
        $session = DB::transaction(function () use ($request): PracticeSession {
            $session = PracticeSession::create([
                'topic_id' => Topic::findOrCreate($request->string('topic')->toString())->id,
            ]);

            $this->saveFirstQuestion($session, $request->validated());

            return $session;
        });

        return redirect()->route('sessions.edit', $session);
    }

    public function edit(PracticeSession $session): Response
    {
        $session->load(['topic', 'questions']);

        return Inertia::render('sessions/Create', [
            'topics' => $this->topicOptions(),
            'session' => [
                'id' => $session->id,
                'status' => $session->status,
                'topic' => [
                    'id' => $session->topic->id,
                    'name' => $session->topic->name,
                ],
                'questions' => $session->questions
                    ->map(fn (PracticeQuestion $question): array => [
                        'id' => $question->id,
                        'position' => $question->position,
                        'question_html' => $question->question_html,
                        'answer_html' => $question->answer_html,
                    ])
                    ->all(),
            ],
        ]);
    }

    public function update(PracticeSession $session, StorePracticeSessionRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($session, $request): void {
            $session->update([
                'topic_id' => Topic::findOrCreate($request->string('topic')->toString())->id,
            ]);

            $this->saveFirstQuestion($session, $request->validated());
        });

        return redirect()->route('sessions.edit', $session);
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function topicOptions(): array
    {
        return Topic::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Topic $topic): array => [
                'id' => $topic->id,
                'name' => $topic->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Step 1 stores one question per session. Later steps add more, so the
     * first question is looked up by its position instead of recreated.
     *
     * @param  array<string, mixed>  $data
     */
    private function saveFirstQuestion(PracticeSession $session, array $data): void
    {
        $question = $session->questions()->firstOrNew(['position' => 1]);

        $questionContent = RichText::cleanWithText((string) ($data['question_html'] ?? ''));
        $answerContent = RichText::cleanWithText((string) ($data['answer_html'] ?? ''));

        $question->question_html = $questionContent['html'];
        $question->question_text = $questionContent['text'];
        $question->answer_html = $answerContent['html'];
        $question->answer_text = $answerContent['text'];
        $question->save();
    }
}

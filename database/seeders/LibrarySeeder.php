<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\FeedbackType;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Topic;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Fills a fresh development database with enough sessions to exercise the
 * Library: a long list, both statuses, empty sessions, sessions edited on
 * a later day, archived sessions, several finished on the same day, and a
 * ten-day streak. The words mix plain English with accents, other languages
 * and punctuation, so search can be tried by hand against real variety. It
 * only runs locally and tops up to the target instead of adding another 500
 * every time.
 */
class LibrarySeeder extends Seeder
{
    private const int TARGET_SESSIONS = 500;

    /** @var list<string> */
    private const array TOPICS = [
        'Ordering at a restaurant',
        'Travel plans',
        'Job interview',
        'Daily routine',
        'Shopping for clothes',
        'Weekend plans',
        'Doctor’s appointment',
        'Renting a flat',
        'Small talk at work',
        'At the airport',
        'Making a complaint',
        'Talking about hobbies',
        'Café conversations',
        'Résumé and cover letters',
        'Cooking with a friend',
        'Explaining a problem',
    ];

    /** @var list<array{question: string, answer: string}> */
    private const array EXCHANGES = [
        [
            'question' => 'What would you like to order?',
            'answer' => 'I’d like the tomato soup, please.',
        ],
        [
            'question' => 'How was your weekend?',
            'answer' => 'It was relaxing. I stayed home and read a book.',
        ],
        [
            'question' => 'Where do you usually go shopping?',
            'answer' => 'I usually go to the market near my flat.',
        ],
        [
            'question' => 'What are you working on at the moment?',
            'answer' => 'I’m preparing a presentation for next week.',
        ],
        [
            'question' => 'Have you ever been to another country?',
            'answer' => 'Yes, I’ve visited Japan and Italy.',
        ],
        [
            'question' => 'What do you do in your free time?',
            'answer' => 'I enjoy cooking and going for long walks.',
        ],
        [
            'question' => 'Could you tell me how to get to the station?',
            'answer' => 'Go straight ahead and turn left at the bank.',
        ],
        [
            'question' => 'What is the weather like today?',
            'answer' => 'It is cold and cloudy, but it is not raining.',
        ],
        [
            'question' => 'Why did you decide to learn English?',
            'answer' => 'I want to feel more confident when I travel.',
        ],
        [
            'question' => 'What kind of music do you like?',
            'answer' => 'I like jazz and old soul music.',
        ],
        [
            'question' => 'What does “crème brûlée” mean?',
            'answer' => 'It is a French dessert with a caramel top.',
        ],
        [
            'question' => '¿Dónde está la estación?',
            'answer' => 'Está cerca del café, a la derecha.',
        ],
        [
            'question' => 'Comment dit-on « merci » en anglais ?',
            'answer' => 'On dit “thank you”, avec un grand sourire.',
        ],
        [
            'question' => 'Is “naïve” the same as “innocent”?',
            'answer' => 'Not quite: naïve is more about being inexperienced.',
        ],
        [
            'question' => 'Which email should I use?',
            'answer' => 'learner+english@example.com — is that right?',
        ],
        [
            'question' => 'How do you say “saudade” in English?',
            'answer' => 'There is no single word for it, only a long explanation.',
        ],
        [
            'question' => 'What is the plural of “analysis”?',
            'answer' => 'It is “analyses”, with an -es ending.',
        ],
        [
            'question' => 'How do you pronounce “though”?',
            'answer' => 'It sounds like “thoh”, rhyming with “go”.',
        ],
    ];

    /** @var list<array{said: string, correction: string, reason: string}> */
    private const array MISTAKES = [
        [
            'said' => 'I go to the shop yesterday.',
            'correction' => 'I went to the shop yesterday.',
            'reason' => 'Use the past tense for a finished action.',
        ],
        [
            'said' => 'He don’t like coffee.',
            'correction' => 'He doesn’t like coffee.',
            'reason' => 'Third person singular takes “does”.',
        ],
        [
            'said' => 'I am living here since 2020.',
            'correction' => 'I have lived here since 2020.',
            'reason' => 'Use the present perfect with “since”.',
        ],
        [
            'said' => 'She can to swim.',
            'correction' => 'She can swim.',
            'reason' => 'After “can”, use the base verb.',
        ],
        [
            'said' => 'I very like this film.',
            'correction' => 'I really like this film.',
            'reason' => 'Use “really” before the verb.',
        ],
        [
            'said' => 'We discussed about the plan.',
            'correction' => 'We discussed the plan.',
            'reason' => '“Discuss” does not take “about”.',
        ],
        [
            'said' => 'I look forward to meet you.',
            'correction' => 'I look forward to meeting you.',
            'reason' => '“Look forward to” takes the -ing form.',
        ],
        [
            'said' => 'If I will have time, I will call.',
            'correction' => 'If I have time, I will call.',
            'reason' => 'Do not use “will” in the if-clause.',
        ],
        [
            'said' => 'I have been to the café three times.',
            'correction' => 'I have been to the café three times.',
            'reason' => 'Nothing to fix here: the accents are correct.',
        ],
        [
            'said' => 'She is more taller than me.',
            'correction' => 'She is taller than me.',
            'reason' => 'Do not use “more” with a comparative ending.',
        ],
        [
            'said' => 'I am agree with you.',
            'correction' => 'I agree with you.',
            'reason' => '“Agree” is a verb, so it needs no “am”.',
        ],
        [
            'said' => 'It depends of the weather.',
            'correction' => 'It depends on the weather.',
            'reason' => '“Depend” takes “on”.',
        ],
    ];

    /** @var list<array{word: string, meaning: string, example: string}> */
    private const array VOCABULARY = [
        ['word' => 'delicious', 'meaning' => 'very tasty', 'example' => 'The soup was delicious.'],
        ['word' => 'crowded', 'meaning' => 'full of people', 'example' => 'The market was crowded.'],
        ['word' => 'convenient', 'meaning' => 'easy and useful', 'example' => 'Online banking is convenient.'],
        ['word' => 'exhausted', 'meaning' => 'very tired', 'example' => 'I was exhausted after the trip.'],
        ['word' => 'recommend', 'meaning' => 'to suggest something good', 'example' => 'Can you recommend a book?'],
        ['word' => 'affordable', 'meaning' => 'not too expensive', 'example' => 'The hotel was affordable.'],
        ['word' => 'neighbourhood', 'meaning' => 'the area around your home', 'example' => 'I like this neighbourhood.'],
        ['word' => 'appointment', 'meaning' => 'a planned meeting', 'example' => 'I have a dentist appointment.'],
        ['word' => 'crème brûlée', 'meaning' => 'a French dessert', 'example' => 'We shared a crème brûlée.'],
        ['word' => 'naïve', 'meaning' => 'inexperienced and trusting', 'example' => 'It was a naïve question.'],
        ['word' => 'déjà vu', 'meaning' => 'the feeling that this happened before', 'example' => 'I had déjà vu in that street.'],
        ['word' => 'fiancé', 'meaning' => 'the man you are engaged to', 'example' => 'Her fiancé cooks every Sunday.'],
        ['word' => 'résumé', 'meaning' => 'a summary of your work history', 'example' => 'Send your résumé by Friday.'],
    ];

    /** @var list<array{phrase: string, meaning: string, example: string}> */
    private const array PHRASES = [
        [
            'phrase' => 'Could I have the bill, please?',
            'meaning' => 'Ask for the bill politely',
            'example' => 'Could I have the bill, please?',
        ],
        [
            'phrase' => 'I would like to…',
            'meaning' => 'A polite way to ask',
            'example' => 'I would like to book a table.',
        ],
        [
            'phrase' => 'It depends on…',
            'meaning' => 'Say that something changes with the situation',
            'example' => 'It depends on the weather.',
        ],
        [
            'phrase' => 'I am used to…',
            'meaning' => 'Something feels normal now',
            'example' => 'I am used to waking up early.',
        ],
        [
            'phrase' => 'On the way to…',
            'meaning' => 'While travelling toward a place',
            'example' => 'I stopped for coffee on the way to work.',
        ],
        [
            'phrase' => 'Take your time',
            'meaning' => 'Do not hurry',
            'example' => 'Take your time with the answer.',
        ],
        [
            'phrase' => 'That sounds great',
            'meaning' => 'Show enthusiasm',
            'example' => 'That sounds great, thank you.',
        ],
        [
            'phrase' => 'I am not sure yet',
            'meaning' => 'Say you have not decided',
            'example' => 'I am not sure yet, let me check.',
        ],
        [
            'phrase' => 'Bon appétit',
            'meaning' => 'Enjoy your meal',
            'example' => 'Bon appétit, everyone!',
        ],
        [
            'phrase' => 'Ça marche',
            'meaning' => 'That works for me',
            'example' => 'Friday at six? Ça marche.',
        ],
        [
            'phrase' => 'No worries at all',
            'meaning' => 'Say that something does not matter',
            'example' => 'No worries at all, it happens.',
        ],
    ];

    /** @var list<string> */
    private const array GRAMMAR_NOTES = [
        'Use the present perfect for experiences up to now.',
        'Put adverbs of frequency before the main verb.',
        'Use “a” before a consonant sound and “an” before a vowel sound.',
        'After “enjoy”, use the -ing form.',
        'Use “much” with uncountable nouns and “many” with countable ones.',
        'Use “since” with a point in time and “for” with a length of time.',
        'In French, “café” keeps its accent, but English often drops it.',
        'Use “the” before a specific place: the café on the corner.',
        'Words like “analysis” and “crisis” form their plurals in -es.',
        'A semicolon joins two closely related ideas; a comma does not.',
    ];

    /** @var list<string> */
    private const array NATURAL_VERSIONS = [
        'I’d love to join you, but I’m already busy.',
        'That’s a great idea — let’s do it.',
        'I’m not sure I follow. Could you explain that again?',
        'I’d rather stay in tonight, if that’s okay.',
        'Thanks for letting me know. I’ll sort it out.',
        'Honestly, the crème brûlée was the best part of the meal.',
        'I’ve never been there, but I’d love to try it.',
        'Sorry — I meant to say “analyses”, not “analysis”.',
        'It was a naïve mistake, but I learned from it.',
        'Ça marche for me; let’s meet at the café.',
    ];

    public function run(): void
    {
        if (! app()->isLocal()) {
            return;
        }

        $topics = collect(self::TOPICS)
            ->map(fn (string $name): Topic => Topic::firstOrCreate(['name' => $name]))
            ->values();

        $missing = self::TARGET_SESSIONS - PracticeSession::query()->count();

        for ($index = 0; $index < $missing; $index++) {
            $this->createSession($topics->random());
        }

        // The extra cases are added once, together. An existing archived
        // session means an earlier run already added them, so running the
        // seeder again tops up in silence instead of duplicating the set.
        if (PracticeSession::query()->whereNotNull('archived_at')->exists()) {
            return;
        }

        $this->seedArchivedSessions($topics);
        $this->seedLongStreak($topics);
        $this->seedSameDaySessions($topics);
    }

    private function createSession(Topic $topic): void
    {
        $createdAt = now()
            ->subDays(fake()->numberBetween(0, 240))
            ->setTime(fake()->numberBetween(7, 21), fake()->numberBetween(0, 59));

        $finished = fake()->boolean(82);
        $finishedAt = $finished
            ? $this->notInTheFuture($createdAt->addMinutes(fake()->numberBetween(20, 300)))
            : null;

        $lastChangedAt = $this->lastChangedAt($createdAt, $finishedAt);

        $session = PracticeSession::factory()
            ->for($topic)
            ->create([
                'status' => $finished
                    ? PracticeSession::STATUS_FINISHED
                    : PracticeSession::STATUS_UNFINISHED,
                'created_at' => $createdAt,
                'updated_at' => $lastChangedAt,
                'finished_at' => $finishedAt,
                'last_changed_at' => $lastChangedAt,
            ]);

        // A few sessions were started and left empty, so the Library has real
        // zero-count cards to show.
        if (fake()->boolean(5)) {
            return;
        }

        $this->addContent($session);
    }

    /**
     * A dozen finished sessions already put away, so the Archived view has
     * real sessions to search, restore, and delete together.
     *
     * @param  Collection<int, Topic>  $topics
     */
    private function seedArchivedSessions(Collection $topics): void
    {
        for ($index = 0; $index < 12; $index++) {
            $finishedAt = now()
                ->subDays(fake()->numberBetween(1, 240))
                ->setTime(fake()->numberBetween(7, 21), fake()->numberBetween(0, 59));

            $archivedAt = $finishedAt
                ->addDays(fake()->numberBetween(0, 14))
                ->setTime(fake()->numberBetween(8, 22), fake()->numberBetween(0, 59));

            $this->finishedSessionAt($topics->random(), $finishedAt, $archivedAt);
        }
    }

    /**
     * One finished session a day for ten days, ending today, so Progress has
     * a long current streak to show.
     *
     * @param  Collection<int, Topic>  $topics
     */
    private function seedLongStreak(Collection $topics): void
    {
        for ($daysAgo = 9; $daysAgo >= 0; $daysAgo--) {
            $this->finishedSessionAt(
                $topics->random(),
                now()->subDays($daysAgo)->setTime(19, 0),
            );
        }
    }

    /**
     * Several finished sessions on the same day, so deleting one leaves the
     * practice day and the streak intact. The combined delete warning can be
     * tried against a day that holds more than one session.
     *
     * @param  Collection<int, Topic>  $topics
     */
    private function seedSameDaySessions(Collection $topics): void
    {
        $today = now()->startOfDay();

        foreach ([10, 13, 16] as $hour) {
            $this->finishedSessionAt(
                $topics->random(),
                $this->notInTheFuture($today->addHours($hour)),
            );
        }

        $sharedDay = now()->subDays(3)->setTime(18, 30);

        $this->finishedSessionAt($topics->random(), $sharedDay);
        $this->finishedSessionAt($topics->random(), $sharedDay->addMinutes(40));
    }

    /**
     * A finished session at a chosen moment, optionally already archived.
     */
    private function finishedSessionAt(
        Topic $topic,
        CarbonImmutable $finishedAt,
        ?CarbonImmutable $archivedAt = null,
    ): PracticeSession {
        $finishedAt = $this->notInTheFuture($finishedAt);
        $createdAt = $this->notInTheFuture(
            $finishedAt->subMinutes(fake()->numberBetween(20, 120)),
        );
        $lastChangedAt = $this->lastChangedAt($createdAt, $finishedAt);

        $session = PracticeSession::factory()
            ->for($topic)
            ->create([
                'status' => PracticeSession::STATUS_FINISHED,
                'created_at' => $createdAt,
                'updated_at' => $lastChangedAt,
                'finished_at' => $finishedAt,
                'last_changed_at' => $lastChangedAt,
                'archived_at' => $archivedAt instanceof CarbonImmutable
                    ? $this->notInTheFuture($archivedAt)
                    : null,
            ]);

        $this->addContent($session);

        return $session;
    }

    private function addContent(PracticeSession $session): void
    {
        $questionCount = fake()->numberBetween(1, 8);

        foreach (range(1, $questionCount) as $position) {
            $exchange = fake()->randomElement(self::EXCHANGES);

            $question = PracticeQuestion::factory()->for($session)->create([
                'position' => $position,
                'question_text' => $exchange['question'],
                'question_html' => '<p>'.e($exchange['question']).'</p>',
                'answer_text' => $exchange['answer'],
                'answer_html' => '<p>'.e($exchange['answer']).'</p>',
            ]);

            $this->addFeedback($question);
        }
    }

    /**
     * Most sessions were edited on the day they happened. Some were tidied up
     * days later, which is what makes the "Edited …" line appear.
     */
    private function lastChangedAt(
        CarbonImmutable $createdAt,
        ?CarbonImmutable $finishedAt,
    ): CarbonImmutable {
        $base = $finishedAt ?? $createdAt;

        if (! fake()->boolean(35)) {
            return $base;
        }

        return $this->notInTheFuture($base->addDays(fake()->numberBetween(1, 20)));
    }

    private function notInTheFuture(CarbonImmutable $moment): CarbonImmutable
    {
        return $moment->greaterThan(now()) ? now() : $moment;
    }

    private function addFeedback(PracticeQuestion $question): void
    {
        $kinds = fake()->randomElements(
            ['mistake', 'vocabulary', 'phrase', 'grammar_note', 'natural_version'],
            fake()->numberBetween(1, 3),
        );

        // Positions are zero-based, the same as entries saved by the editor.
        $position = 0;

        foreach ($kinds as $kind) {
            $entry = match ($kind) {
                'mistake' => $this->mistake(),
                'vocabulary' => $this->vocabulary(),
                'phrase' => $this->phrase(),
                'grammar_note' => $this->grammarNote(),
                default => $this->naturalVersion(),
            };

            PracticeFeedbackEntry::factory()->for($question)->create([
                ...$entry,
                'position' => $position++,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mistake(): array
    {
        $mistake = fake()->randomElement(self::MISTAKES);

        return [
            'type' => FeedbackType::Mistake,
            'content_text' => $mistake['said'],
            'correction_text' => $mistake['correction'],
            'correction_html' => '<strong>'.e($mistake['correction']).'</strong>',
            'reason' => $mistake['reason'],
            'is_split' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function vocabulary(): array
    {
        $word = fake()->randomElement(self::VOCABULARY);

        return [
            'type' => FeedbackType::Vocabulary,
            'content_text' => $word['word'],
            'correction_text' => $word['meaning'],
            'example' => $word['example'],
            'is_split' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function phrase(): array
    {
        $phrase = fake()->randomElement(self::PHRASES);

        return [
            'type' => FeedbackType::Phrase,
            'content_text' => $phrase['phrase'],
            'correction_text' => $phrase['meaning'],
            'example' => $phrase['example'],
            'is_split' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function grammarNote(): array
    {
        $note = fake()->randomElement(self::GRAMMAR_NOTES);

        return [
            'type' => FeedbackType::GrammarNote,
            'content_html' => '<p>'.e($note).'</p>',
            'content_text' => $note,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function naturalVersion(): array
    {
        $version = fake()->randomElement(self::NATURAL_VERSIONS);

        return [
            'type' => FeedbackType::NaturalVersion,
            'content_html' => '<p>'.e($version).'</p>',
            'content_text' => $version,
        ];
    }
}

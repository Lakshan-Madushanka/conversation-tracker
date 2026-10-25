<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Topic;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Fills a fresh development database with enough sessions to exercise the
 * Library: a long list, both statuses, empty sessions, and sessions edited on
 * a later day. It only runs locally and tops up to the target instead of
 * adding another 300 every time.
 */
class LibrarySeeder extends Seeder
{
    private const int TARGET_SESSIONS = 300;

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
    ];

    /** @var list<string> */
    private const array GRAMMAR_NOTES = [
        'Use the present perfect for experiences up to now.',
        'Put adverbs of frequency before the main verb.',
        'Use “a” before a consonant sound and “an” before a vowel sound.',
        'After “enjoy”, use the -ing form.',
        'Use “much” with uncountable nouns and “many” with countable ones.',
        'Use “since” with a point in time and “for” with a length of time.',
    ];

    /** @var list<string> */
    private const array NATURAL_VERSIONS = [
        'I’d love to join you, but I’m already busy.',
        'That’s a great idea — let’s do it.',
        'I’m not sure I follow. Could you explain that again?',
        'I’d rather stay in tonight, if that’s okay.',
        'Thanks for letting me know. I’ll sort it out.',
    ];

    public function run(): void
    {
        if (! app()->isLocal()) {
            return;
        }

        $missing = self::TARGET_SESSIONS - PracticeSession::query()->count();

        if ($missing <= 0) {
            return;
        }

        $topics = collect(self::TOPICS)
            ->map(fn (string $name): Topic => Topic::firstOrCreate(['name' => $name]))
            ->values();

        for ($index = 0; $index < $missing; $index++) {
            $this->createSession($topics->random());
        }
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

        $position = 1;

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
            'type' => PracticeFeedbackEntry::TYPE_MISTAKE,
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
            'type' => PracticeFeedbackEntry::TYPE_VOCABULARY,
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
            'type' => PracticeFeedbackEntry::TYPE_PHRASE,
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
            'type' => PracticeFeedbackEntry::TYPE_GRAMMAR_NOTE,
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
            'type' => PracticeFeedbackEntry::TYPE_NATURAL_VERSION,
            'content_html' => '<p>'.e($version).'</p>',
            'content_text' => $version,
        ];
    }
}

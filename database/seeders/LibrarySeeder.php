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
use Illuminate\Support\Facades\DB;

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

    /**
     * Enough words to fill a few hundred collection pages, so "Load more" and
     * the endless list can be tried by hand.
     */
    private const int TARGET_VOCABULARY = 5000;

    /** One showcase session: eight questions with twelve rows each. */
    private const int SHOWCASE_QUESTIONS = 8;

    private const int SHOWCASE_ROWS = 12;

    /** A meaning long enough to wrap over several lines on a phone. */
    private const string LONG_MEANING = 'A feeling of quiet, unhurried contentment that comes from simple things — a warm drink, a familiar chair, a book you have read before, and the knowledge that there is nowhere else you need to be for the next hour.';

    private const string LONG_EXAMPLE = 'When the rain finally stopped, we walked the long way home past the market, the old bookshop and the café where the waiter always remembers our order, and none of us said very much because the evening already felt complete.';

    /**
     * One word written four ways, so grouping, versions and a row without a
     * meaning can be tried in the same vocabulary box.
     *
     * @var list<array{word: string, meaning: string, example: string}>
     */
    private const array SHOWCASE_VARIANTS = [
        ['word' => 'Café', 'meaning' => 'a place that serves coffee', 'example' => 'We met at the café on the corner.'],
        ['word' => 'cafe', 'meaning' => 'a coffee shop', 'example' => 'The cafe was busy all morning.'],
        ['word' => 'CAFÉ', 'meaning' => '', 'example' => ''],
        ['word' => '  café  ', 'meaning' => 'a small coffee house', 'example' => 'The little café stays open late.'],
    ];

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
        ['word' => 'relish', 'meaning' => 'to enjoy something greatly', 'example' => 'I relish a quiet evening at home.'],
        ['word' => 'savour', 'meaning' => 'to enjoy slowly and fully', 'example' => 'She savoured every spoonful of the soup.'],
        ['word' => 'gemütlich', 'meaning' => 'cosy and comfortable in a German-speaking way', 'example' => 'The little café felt gemütlich on a cold day.'],
        ['word' => 'hygge', 'meaning' => 'a Danish feeling of cosy contentment', 'example' => 'Candles and blankets are part of hygge.'],
        ['word' => 'piñata', 'meaning' => 'a decorated container filled with sweets', 'example' => 'The children broke the piñata at the party.'],
        ['word' => 'jalapeño', 'meaning' => 'a small hot green pepper', 'example' => 'Add one jalapeño, not three.'],
        ['word' => 'smörgåsbord', 'meaning' => 'a Swedish spread of many small dishes', 'example' => 'The buffet was a proper smörgåsbord.'],
        ['word' => 'schadenfreude', 'meaning' => 'pleasure taken from another person’s trouble', 'example' => 'He felt a flicker of schadenfreude when the rival team lost.'],
        ['word' => 'doppelgänger', 'meaning' => 'a person who looks exactly like another', 'example' => 'You are my doppelgänger, surely.'],
        ['word' => 'zeitgeist', 'meaning' => 'the mood of a particular period', 'example' => 'The film captured the zeitgeist of the decade.'],
        ['word' => 'wanderlust', 'meaning' => 'a strong desire to travel', 'example' => 'Her wanderlust took her to three continents.'],
        ['word' => 'entrepreneur', 'meaning' => 'someone who starts a business', 'example' => 'The young entrepreneur opened a bakery.'],
        ['word' => 'rendezvous', 'meaning' => 'a meeting arranged at a place and time', 'example' => 'We had a rendezvous at the station clock.'],
        ['word' => 'bouquet', 'meaning' => 'a bunch of flowers', 'example' => 'He brought a bouquet of wild flowers.'],
        ['word' => 'liaison', 'meaning' => 'communication between groups', 'example' => 'She acts as our liaison with the school.'],
        ['word' => 'façade', 'meaning' => 'the front of a building', 'example' => 'The façade hides a quiet courtyard.'],
        ['word' => 'coöperate', 'meaning' => 'to work together', 'example' => 'The two teams coöperate on every project.'],
        ['word' => 'soufflé', 'meaning' => 'a light baked egg dish', 'example' => 'The cheese soufflé rose beautifully.'],
        ['word' => 'crêpe', 'meaning' => 'a thin French pancake', 'example' => 'We ordered a lemon crêpe.'],
        ['word' => 'mêlée', 'meaning' => 'a confused crowd or struggle', 'example' => 'He lost his hat in the mêlée.'],
        ['word' => 'fiancée', 'meaning' => 'the woman you are engaged to', 'example' => 'His fiancée arrives on Friday.'],
        ['word' => 'tsunami', 'meaning' => 'a very large ocean wave', 'example' => 'The coast is ready for a tsunami warning.'],
        ['word' => 'karaoke', 'meaning' => 'singing popular songs over a backing track', 'example' => 'We stayed for one more karaoke song.'],
        ['word' => 'kindergarten', 'meaning' => 'a school for very young children', 'example' => 'She teaches at a kindergarten.'],
        ['word' => 'angst', 'meaning' => 'a feeling of deep worry', 'example' => 'The deadline filled him with angst.'],
        ['word' => 'kitsch', 'meaning' => 'art or objects that are purposely sentimental', 'example' => 'The souvenir shop was full of kitsch.'],
        ['word' => 'glühwein', 'meaning' => 'warm spiced wine', 'example' => 'A cup of glühwein warmed our hands.'],
        ['word' => 'smørrebrød', 'meaning' => 'a Danish open sandwich', 'example' => 'Lunch was smørrebrød and strong coffee.'],
        ['word' => 'avant-garde', 'meaning' => 'new and experimental', 'example' => 'The gallery shows avant-garde work.'],
        ['word' => 'faux pas', 'meaning' => 'a small social mistake', 'example' => 'Forgetting her name was an awkward faux pas.'],
        ['word' => 'tour de force', 'meaning' => 'an impressive performance or achievement', 'example' => 'Her final speech was a tour de force.'],
        ['word' => 'bon appétit', 'meaning' => 'enjoy your meal', 'example' => 'Bon appétit, everyone!'],
        ['word' => '朝ごはん', 'meaning' => 'breakfast in Japanese', 'example' => '朝ごはん is ready at seven.'],
        ['word' => '안녕하세요', 'meaning' => 'hello in Korean', 'example' => '안녕하세요, nice to meet you.'],
        ['word' => 'merhaba', 'meaning' => 'hello in Turkish', 'example' => 'Merhaba, how are you today?'],
        ['word' => 'gracias', 'meaning' => 'thank you in Spanish', 'example' => 'Gracias for the lovely evening.'],
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
        if (! PracticeSession::query()->whereNotNull('archived_at')->exists()) {
            $this->seedArchivedSessions($topics);
            $this->seedLongStreak($topics);
            $this->seedSameDaySessions($topics);
        }

        $this->seedVocabularyShowcase($topics);
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
     * Tops the vocabulary up to a few thousand rows, so the Collection has
     * enough words to page through by hand.
     *
     * The pool mixes unique terms with the cases the page has to handle:
     * one word written several ways, four copies in the same box, rows with
     * no meaning, very long meanings and examples, letters from outside
     * English, and a blank row that must never be gathered. The target is
     * counted from the database, so a later run continues where the last one
     * stopped instead of adding another 5,000 every time.
     *
     * @param  Collection<int, Topic>  $topics
     */
    private function seedVocabularyShowcase(Collection $topics): void
    {
        $existing = PracticeFeedbackEntry::query()
            ->where('type', FeedbackType::Vocabulary->value)
            ->count();

        if ($existing >= self::TARGET_VOCABULARY) {
            return;
        }

        $perSession = self::SHOWCASE_QUESTIONS * self::SHOWCASE_ROWS;
        $sessions = (int) ceil(
            (self::TARGET_VOCABULARY - $existing) / $perSession,
        );
        $index = 0;

        DB::transaction(function () use ($topics, $sessions, &$index): void {
            for ($number = 0; $number < $sessions; $number++) {
                $finishedAt = $this->notInTheFuture(
                    now()
                        ->subDays(fake()->numberBetween(0, 240))
                        ->setTime(
                            fake()->numberBetween(7, 21),
                            fake()->numberBetween(0, 59),
                        ),
                );
                $createdAt = $this->notInTheFuture(
                    $finishedAt->subMinutes(fake()->numberBetween(20, 120)),
                );
                $lastChangedAt = $this->lastChangedAt($createdAt, $finishedAt);

                $session = PracticeSession::factory()
                    ->for($topics->random())
                    ->create([
                        'status' => PracticeSession::STATUS_FINISHED,
                        'created_at' => $createdAt,
                        'updated_at' => $lastChangedAt,
                        'finished_at' => $finishedAt,
                        'last_changed_at' => $lastChangedAt,
                        // A few showcase words sit in archived sessions, so
                        // the Collection proves it leaves them out.
                        'archived_at' => $number % 12 === 0
                            ? $this->notInTheFuture($finishedAt->copy()->addDays(2))
                            : null,
                    ]);

                foreach (range(1, self::SHOWCASE_QUESTIONS) as $position) {
                    $exchange = fake()->randomElement(self::EXCHANGES);

                    $question = PracticeQuestion::factory()->for($session)->create([
                        'position' => $position,
                        'question_text' => $exchange['question'],
                        'question_html' => '<p>'.e($exchange['question']).'</p>',
                        'answer_text' => $exchange['answer'],
                        'answer_html' => '<p>'.e($exchange['answer']).'</p>',
                    ]);

                    foreach (range(0, self::SHOWCASE_ROWS - 1) as $row) {
                        $this->createShowcaseRow($question, $position, $row, $index++);
                    }
                }
            }
        });
    }

    /**
     * One row of the showcase vocabulary, with the awkward cases spread
     * through it rather than kept in a separate corner.
     */
    private function createShowcaseRow(
        PracticeQuestion $question,
        int $position,
        int $row,
        int $index,
    ): void {
        $word = $this->showcaseWord($index, $position, $row);

        $meaning = $word['meaning'];
        $example = $word['example'];

        if (! $word['variant']) {
            $meaning = ucfirst(fake()->sentence(6));
            $example = fake()->sentence(10);

            if ($index % 7 === 0) {
                $meaning = '';
                $example = '';
            }

            if ($index % 13 === 0) {
                $meaning = self::LONG_MEANING;
            }

            if ($index % 17 === 0) {
                $example = self::LONG_EXAMPLE;
            }
        }

        // A blank row here and there proves it is left out of the collection.
        if (! $word['variant'] && $index % 41 === 0) {
            $word['word'] = '   ';
        }

        $exampleHtml = $example === '' || $index % 4 !== 0
            ? []
            : [[
                'id' => sprintf('example-%05d', $index),
                'text' => $example,
                'html' => '<p>'.e($example).'</p>',
            ]];

        PracticeFeedbackEntry::factory()->for($question)->create([
            'type' => FeedbackType::Vocabulary,
            'position' => $row,
            'client_id' => sprintf('showcase-%05d', $index),
            'content_text' => $word['word'],
            'correction_text' => $meaning,
            'example' => $example,
            'example_html' => $exampleHtml,
            'is_split' => true,
        ]);
    }

    /**
     * The word for one showcase row: a four-way variant in the first box, a
     * word from the curated pool now and then, and a unique two-word term for
     * the rest so the collection really has thousands of different items.
     *
     * @return array{word: string, meaning: string, example: string, variant: bool}
     */
    private function showcaseWord(int $index, int $position, int $row): array
    {
        if ($position === 1 && $row < count(self::SHOWCASE_VARIANTS)) {
            return [...self::SHOWCASE_VARIANTS[$row], 'variant' => true];
        }

        if ($index % 6 === 0) {
            $entry = self::VOCABULARY[$index % count(self::VOCABULARY)];
            $word = $entry['word'];

            if ($index % 4 === 0) {
                $word = ' '.$word.' ';
            }

            if ($index % 9 === 0) {
                $word = mb_strtoupper($word);
            }

            return [
                'word' => $word,
                'meaning' => $entry['meaning'],
                'example' => $entry['example'],
                'variant' => false,
            ];
        }

        $term = rtrim(fake()->unique()->sentence(2, false), '.');

        return [
            'word' => ucfirst($term),
            'meaning' => 'a term for “'.$term.'” used in everyday conversation',
            'example' => 'We talked about '.$term.' on the way home.',
            'variant' => false,
        ];
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

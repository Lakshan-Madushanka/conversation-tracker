<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Collections\SaveItemMark;
use App\Actions\Collections\SaveItemNotes;
use App\Actions\Reviews\SyncReviewSchedule;
use App\Enums\CollectionItemStatus;
use App\Enums\FeedbackType;
use App\Enums\ReviewRating;
use App\Models\CollectionItemMark;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Rating;
use App\Models\Topic;
use App\Support\MistakeIdentity;
use App\Support\SearchText;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fills a fresh development database with enough sessions to exercise the
 * Library: a long list, both statuses, empty sessions, sessions edited on
 * a later day, archived sessions, several finished on the same day, and a
 * ten-day streak. The words mix plain English with accents, other languages
 * and punctuation, so search can be tried by hand against real variety. The
 * collections are topped up too: a few thousand words, a few thousand
 * phrases and a few thousand grammar notes, with the awkward cases each
 * page has to handle mixed in. It only runs locally and tops up to the
 * target instead of adding everything again on every run.
 */
class LibrarySeeder extends Seeder
{
    private const int TARGET_SESSIONS = 500;

    /**
     * Enough words to fill a few hundred collection pages, so "Load more" and
     * the endless list can be tried by hand.
     */
    private const int TARGET_VOCABULARY = 5000;

    /**
     * Enough phrases to page through the second tab the same way, with the
     * unsplittable rows and quiet no-meaning rows mixed in.
     */
    private const int TARGET_PHRASES = 5000;

    /**
     * Enough notes to page through while the twice-saved rules stay apart.
     */
    private const int TARGET_GRAMMAR_NOTES = 3000;

    /**
     * Enough mistakes to practice My mistakes by hand: several thousand
     * grouped pairs, with the same mistake seen one, two, three and many
     * times mixed through the tab.
     */
    private const int TARGET_MISTAKES = 5000;

    /**
     * How many distinct words and phrases get a rating history, so the
     * daily suggestion has due, overdue and never-reviewed items to
     * choose from.
     */
    private const int TARGET_RATED_ITEMS = 900;

    /**
     * Enough questions with a natural version to practice Say it again by
     * hand, with questions that have no natural version, blank ones,
     * formatted and very long text, and the same question in two sessions
     * mixed through the set.
     */
    private const int TARGET_SAY_IT_AGAIN = 5000;

    /**
     * How many of those questions get a rating history over many days, so
     * the daily suggestion has due, overdue and never-reviewed questions.
     */
    private const int TARGET_RATED_QUESTIONS = 400;

    /** One showcase session: eight questions with twelve rows each. */
    private const int SHOWCASE_QUESTIONS = 8;

    private const int SHOWCASE_ROWS = 12;

    /** A meaning long enough to wrap over several lines on a phone. */
    private const string LONG_MEANING = 'A feeling of quiet, unhurried contentment that comes from simple things — a warm drink, a familiar chair, a book you have read before, and the knowledge that there is nowhere else you need to be for the next hour.';

    private const string LONG_EXAMPLE = 'When the rain finally stopped, we walked the long way home past the market, the old bookshop and the café where the waiter always remembers our order, and none of us said very much because the evening already felt complete.';

    /** A note longer than the list preview can hold, so the cut shows. */
    private const string LONG_NOTE = 'Remember that the present perfect is about a link to now: the action or its result still matters in the present, so it is the natural choice for news, for results you can still see, and for experiences that do not carry a specific finished time.';

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

    /**
     * One phrase written four ways, so phrase grouping and a row without a
     * meaning can be tried in the same phrases box.
     *
     * @var list<array{phrase: string, meaning: string, example: string}>
     */
    private const array SHOWCASE_PHRASE_VARIANTS = [
        ['phrase' => 'Chase up', 'meaning' => 'to hurry someone for an answer', 'example' => 'I will chase them up tomorrow.'],
        ['phrase' => 'CHASE  UP', 'meaning' => '', 'example' => ''],
        ['phrase' => '  chase up  ', 'meaning' => 'to follow up until it is done', 'example' => 'She chased up the invoice last week.'],
        ['phrase' => 'chase up', 'meaning' => 'to ask again because there was no answer', 'example' => 'Chase up the reply this time.'],
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
        [
            'said' => 'Ayer I went al mercado.',
            'correction' => 'Yesterday I went to the market.',
            'reason' => 'Say “yesterday” and “to the market” in English.',
        ],
        [
            'said' => 'The food was muy delicious.',
            'correction' => 'The food was very delicious.',
            'reason' => 'English uses “very”, not “muy”.',
        ],
        [
            'said' => 'J’adore to cook with my friend.',
            'correction' => 'I love to cook with my friend.',
            'reason' => 'Say the sentence in one language at a time.',
        ],
    ];

    /**
     * One mistake written four ways, so mistake grouping, counting and the
     * needs-attention rule can all be tried in the same mistakes box: the
     * same pair in different capitals and spacing, the same words without a
     * reason, and the same words with no correction at all.
     *
     * @var list<array{said: string, correction: string, reason: string}>
     */
    private const array SHOWCASE_MISTAKE_VARIANTS = [
        [
            'said' => 'I go to the shop yesterday.',
            'correction' => 'I went to the shop yesterday.',
            'reason' => 'Use the past tense for a finished action.',
        ],
        [
            'said' => 'I GO  TO THE SHOP yesterday.',
            'correction' => 'I went to the  shop yesterday.',
            'reason' => 'The same rule again: past tense for a finished action.',
        ],
        [
            'said' => '  i go to the shop yesterday  ',
            'correction' => 'I went to the shop yesterday.',
            'reason' => '',
        ],
        [
            'said' => 'I go to the shop yesterday.',
            'correction' => '',
            'reason' => '',
        ],
    ];

    /** A reason longer than the list can show without wrapping over lines. */
    private const string LONG_REASON = 'The past simple is for actions that started and finished at a known time in the past — such as yesterday, last week or in 2019 — while the present perfect links the action to now, so it is the better choice when the exact moment does not matter or has not been said yet.';

    /** A mistake long enough to be shortened in the list and full when opened. */
    private const string LONG_SAID = 'I went to the market near my flat yesterday morning because I wanted to buy some fresh vegetables for the soup I was planning to cook for my family that evening while my sister visited us after her long trip.';

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

    /**
     * Questions reused across different sessions, so the same words are two
     * separate say-it-again items. Each keeps the natural version it had.
     *
     * @var list<array{question: string, natural: string}>
     */
    private const array SHOWCASE_QUESTION_PAIRS = [
        [
            'question' => 'What do you usually do at the weekend?',
            'natural' => 'Most weekends I keep things simple: a slow breakfast, a long walk and an afternoon with a book.',
        ],
        [
            'question' => 'Could you tell me where the nearest bank is?',
            'natural' => 'Sorry to bother you — could you point me towards the nearest bank?',
        ],
        [
            'question' => 'Why did you decide to learn English?',
            'natural' => 'I wanted to feel at ease when I travel and to speak with colleagues without hesitating.',
        ],
        [
            'question' => 'What are you working on at the moment?',
            'natural' => 'I’m putting together a presentation for next week, so most of my time goes into that.',
        ],
        [
            'question' => 'How was your weekend?',
            'natural' => 'It was lovely, thank you — quiet, unhurried and exactly what I needed.',
        ],
    ];

    /**
     * Questions with accents, other scripts and punctuation, so the review
     * card proves it shows them exactly as they were saved.
     *
     * @var list<string>
     */
    private const array SPECIAL_QUESTIONS = [
        '¿Cómo llegarías a la estación desde aquí, y cuánto tarda?',
        'Comment expliqueriez-vous « dépaysement » à un ami anglophone ?',
        'Was bedeutet „Feierabend“ auf Englisch, und wann benutzt man es?',
        '「よろしくお願いします」は英語でどう言えば自然ですか？',
        'Could you explain the difference between “savoir” and “connaître”?',
        'Naïve, café, façade — which accents do you keep in English?',
    ];

    /** A question longer than a phone screen, so the card must scroll. */
    private const string LONG_QUESTION = 'I’ve been invited to a work dinner next Thursday, and I’m not sure how to accept politely while also explaining that I need to leave early because of a train. Could you help me put that into one clear, friendly message that sounds natural rather than rude, and could you also suggest a shorter version I could use in a quick reply if the first one feels too formal?';

    /** A long, natural reply to the long question above. */
    private const string LONG_NATURAL = 'Thanks so much for the invitation — I’d love to join you on Thursday. I do need to catch a train afterwards, so I’ll probably slip away a little early, but I’m really looking forward to seeing everyone. If it helps, I can send a quick note on the day to confirm what time I’ll be there.';

    /** A question kept in the Archive: Say it again must never show it. */
    private const string EXCLUDED_ARCHIVED_QUESTION = 'Why was this question kept in an archived session?';

    /** A question still waiting in an unfinished session: never shown. */
    private const string EXCLUDED_UNFINISHED_QUESTION = 'Why is this question still waiting in an unfinished session?';

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
        $this->seedPhrasesShowcase($topics);
        $this->seedGrammarShowcase($topics);
        $this->seedMistakesShowcase($topics);
        $this->seedQuestionsShowcase($topics);
        $this->seedMarks();
        $this->seedReviewRatings();
        $this->seedQuestionRatings();
    }

    /**
     * Gives a bite-sized corner of each collection the learner's marks: a
     * set star, a chosen status, stars with notes behind them, notes of
     * words and lists, and items with nothing at all. Marks are counted from
     * the database, so a later run adds none again.
     */
    private function seedMarks(): void
    {
        if (CollectionItemMark::query()->exists()) {
            return;
        }

        $this->seedTableMarks(FeedbackType::Vocabulary);
        $this->seedTableMarks(FeedbackType::Phrase);
        $this->seedNoteMarks();
        $this->seedMistakeMarks();
    }

    /**
     * Marks the words or phrases of one table box, cycling through the
     * combinations the page has to show.
     *
     * @param  FeedbackType  $kind  the box being marked, Vocabulary or Phrase
     */
    private function seedTableMarks(FeedbackType $kind): void
    {
        $saveMark = app(SaveItemMark::class);
        $saveNotes = app(SaveItemNotes::class);

        PracticeFeedbackEntry::query()
            ->where('type', $kind)
            ->whereRaw('trim(content_text) <> ? and content_text is not null', [''])
            ->inRandomOrder()
            ->limit(40)
            ->get(['content_text'])
            ->unique(fn (PracticeFeedbackEntry $entry): string => SearchText::normalize((string) $entry->content_text))
            ->take(40)
            ->values()
            ->each(function (PracticeFeedbackEntry $entry, int $n) use ($saveMark, $saveNotes, $kind): void {
                $itemKey = SearchText::normalize((string) $entry->content_text);
                $addNote = function () use ($saveNotes, $kind, $itemKey, $n): void {
                    $saveNotes->handle(
                        $kind,
                        $itemKey,
                        $n % 10 === 0
                            ? $this->listNote('Long notes wrap over lines.')
                            : '<p>Word family: <strong>varieties</strong> and <em>various</em> appear once a page.</p>',
                        0,
                    );
                };

                match ($n % 4) {
                    // The star is on and the item is learned.
                    0 => $saveMark->handle($kind, $itemKey, true, CollectionItemStatus::Learned),
                    // The star is on and the item is still difficult.
                    1 => $saveMark->handle($kind, $itemKey, true, CollectionItemStatus::StillDifficult),
                    // A difficult one a learner keeps writing down, with notes.
                    2 => (function () use ($saveMark, $addNote, $kind, $itemKey): void {
                        $saveMark->handle($kind, $itemKey, false, CollectionItemStatus::StillDifficult);
                        $addNote();
                    })(),
                    // The rest stay untouched, so most rows on a page are plain.
                    default => null,
                };
            });
    }

    /**
     * Marks a sample of the grammar notes. Notes are never merged, so two
     * identical rules arriving with their own marks stay two separate marks
     * — the same wording twice keeps its marks apart.
     */
    private function seedNoteMarks(): void
    {
        $saveMark = app(SaveItemMark::class);
        $saveNotes = app(SaveItemNotes::class);

        PracticeFeedbackEntry::query()
            ->where('type', FeedbackType::GrammarNote)
            ->whereRaw('trim(content_text) <> ? and content_text is not null', [''])
            ->inRandomOrder()
            ->limit(60)
            ->get(['id', 'content_text'])
            ->each(function (PracticeFeedbackEntry $entry, int $n) use ($saveMark, $saveNotes): void {
                $itemKey = 'note-'.$entry->id;

                match ($n % 4) {
                    0 => $saveMark->handle(FeedbackType::GrammarNote, $itemKey, true, CollectionItemStatus::Learned),
                    1 => $saveMark->handle(FeedbackType::GrammarNote, $itemKey, true, null),
                    2 => $saveMark->handle(FeedbackType::GrammarNote, $itemKey, false, CollectionItemStatus::StillDifficult),
                    default => null,
                };

                if ($n % 2 === 0) {
                    $saveNotes->handle(
                        FeedbackType::GrammarNote,
                        $itemKey,
                        $n % 6 === 0
                            ? $this->listNote('Read this again on Friday.')
                            : '<p>My own reminder: check these <em>before</em> the exam.</p>',
                        0,
                    );
                }
            });
    }

    private function seedMistakeMarks(): void
    {
        $saveMark = app(SaveItemMark::class);
        $saveNotes = app(SaveItemNotes::class);

        PracticeFeedbackEntry::query()
            ->where('type', FeedbackType::Mistake)
            ->whereRaw('trim(content_text) <> ? and trim(correction_text) <> ? and content_text is not null and correction_text is not null', ['', ''])
            ->inRandomOrder()
            ->limit(60)
            ->get(['content_text', 'correction_text'])
            ->unique(fn (PracticeFeedbackEntry $entry): string => MistakeIdentity::pairKey(
                (string) $entry->content_text,
                (string) $entry->correction_text,
            ))
            ->take(60)
            ->values()
            ->each(function (PracticeFeedbackEntry $entry, int $n) use ($saveMark, $saveNotes): void {
                $itemKey = MistakeIdentity::pairKey(
                    (string) $entry->content_text,
                    (string) $entry->correction_text,
                );

                match ($n % 4) {
                    0 => $saveMark->handle(FeedbackType::Mistake, $itemKey, true, CollectionItemStatus::StillDifficult),
                    1 => $saveMark->handle(FeedbackType::Mistake, $itemKey, true, null),
                    2 => $saveMark->handle(FeedbackType::Mistake, $itemKey, false, CollectionItemStatus::Learned),
                    default => null,
                };

                if ($n % 3 === 0) {
                    $saveNotes->handle(
                        FeedbackType::Mistake,
                        $itemKey,
                        '<p>Say it out loud on Monday: <strong>'.e((string) $entry->correction_text).'</strong></p>',
                        0,
                    );
                }
            });
    }

    /** One of the long, list-shaped notes the page has to hold. */
    private function listNote(string $firstLine): string
    {
        return '<p>'.e($firstLine).' The word family grows on paper: write the parts down one at a time, check them later, and keep the ones that stay difficult.</p>'
            .'<ul><li>'.e('The word on its own, spelt the way you meant it.').'</li>'
            .'<li>'.e('The phrase where it belongs, in a sentence you said.').'</li>'
            .'<li>'.e('The familiar friend nearby — one term, three words.').'</li>'
            .'<li>'.e('A last case long enough that the saved note is later than the first page can show.').'</li></ul>';
    }

    /**
     * Gives a large sample of words, phrases and mistakes a rating history
     * spread over the past four months, so the daily suggestion and the
     * schedule have real work to do in every built exercise kind: some items
     * are overdue, some are freshly easy, and most have never been rated.
     * Creates its own historical daily reviews to hold the ratings. Runs once;
     * a second run adds nothing.
     */
    private function seedReviewRatings(): void
    {
        if (Rating::query()->exists()) {
            return;
        }

        $entries = PracticeFeedbackEntry::query()
            ->whereIn('type', [
                FeedbackType::Vocabulary->value,
                FeedbackType::Phrase->value,
                FeedbackType::Mistake->value,
            ])
            ->whereNotNull('collection_key')
            ->whereRaw("TRIM(collection_key) <> ''")
            ->whereRaw("TRIM(content_text) <> ''")
            ->whereRaw("TRIM(correction_text) <> ''")
            ->whereHas(
                'practiceQuestion.practiceSession',
                fn ($query) => $query->study(),
            )
            ->inRandomOrder()
            ->limit(1500)
            ->get(['type', 'collection_key']);

        $items = [];

        foreach ($entries as $entry) {
            $key = $entry->type->value."\u{0}".$entry->collection_key;

            $items[$key] ??= ['kind' => $entry->type, 'identity' => (string) $entry->collection_key];
        }

        $items = array_values($items);
        shuffle($items);
        $items = array_slice($items, 0, self::TARGET_RATED_ITEMS);

        if ($items === []) {
            return;
        }

        // A season of historical daily reviews to hold the ratings, one or
        // two per week-ish, spread over the past four months.
        $reviewIds = [];
        $now = CarbonImmutable::now();

        for ($day = 130; $day >= 1; $day -= random_int(2, 5)) {
            $moment = $now->subDays($day)->setTime(19, 0);

            $reviewIds[] = (int) DB::table('reviews')->insertGetId([
                'is_daily_review' => true,
                'completed_on' => $moment->toDateString(),
                'created_at' => $moment,
                'updated_at' => $moment,
            ]);
        }

        $saveMark = app(SaveItemMark::class);
        $sync = app(SyncReviewSchedule::class);

        foreach ($items as $position => $item) {
            $kind = $item['kind'];
            $identity = $item['identity'];

            // The scenario for this item, for a spread-over-time mix.
            $scenario = match ($position % 10) {
                // Hard a while ago: due and overdue, the queue to work from.
                0, 1 => 'hard_overdue',
                // Okay a while ago: due soon after.
                2, 3, 4 => 'okay_overdue',
                // A run of Easy a long time ago: due around now, gap grown.
                5, 6 => 'easy_run',
                // Freshly Easy: not due for a while.
                7 => 'easy_fresh',
                // A mixed history that ends Okay.
                8 => 'mixed_okay',
                // Long since learned-ish: Easy several times.
                default => 'easy_repeat',
            };

            $ratings = $this->ratingHistoryFor($scenario, $now);

            // Each rating of one item goes into a different sitting, so the
            // one rating per item per review stays exactly one.
            $picks = array_rand($reviewIds, count($ratings));
            $picks = is_array($picks) ? $picks : [$picks];

            foreach ($ratings as $index => $rating) {
                $moment = $rating['at'];
                $reviewId = $reviewIds[$picks[$index]];
                $key = Rating::keyFor($reviewId, $kind, $identity);

                DB::table('ratings')->insert([
                    'review_id' => $reviewId,
                    'kind' => $kind->value,
                    'item_identity' => $identity,
                    'rating' => $rating['rating']->value,
                    'unique_key' => $key,
                    'created_at' => $moment,
                    'updated_at' => $moment,
                ]);
            }

            // A third of the Hard items are marked Still difficult or
            // starred, so the queue's priorities have something to show.
            if ($scenario === 'hard_overdue') {
                if ($position % 9 === 0) {
                    $saveMark->handle($kind, $identity, true, null);
                } elseif ($position % 9 === 3) {
                    $saveMark->handle($kind, $identity, false, CollectionItemStatus::StillDifficult);
                }
            }

            $sync->handle($kind, $identity);
        }
    }

    /**
     * A rating history for one scenario, ending where the scenario wants.
     *
     * @return list<array{rating: ReviewRating, at: CarbonImmutable}>
     */
    private function ratingHistoryFor(string $scenario, CarbonImmutable $now): array
    {
        return match ($scenario) {
            'hard_overdue' => [
                ['rating' => ReviewRating::Hard, 'at' => $now->subDays(random_int(2, 30))->setTime(19, 0)],
            ],
            'okay_overdue' => [
                ['rating' => ReviewRating::Okay, 'at' => $now->subDays(random_int(4, 90))->setTime(19, 0)],
            ],
            'easy_run' => [
                ['rating' => ReviewRating::Easy, 'at' => $now->subDays(random_int(130, 150))->setTime(19, 0)],
                ['rating' => ReviewRating::Easy, 'at' => $now->subDays(random_int(80, 100))->setTime(19, 0)],
                ['rating' => ReviewRating::Easy, 'at' => $now->subDays(random_int(30, 40))->setTime(19, 0)],
            ],
            'easy_fresh' => [
                ['rating' => ReviewRating::Easy, 'at' => $now->subDays(random_int(1, 5))->setTime(19, 0)],
            ],
            'mixed_okay' => [
                ['rating' => ReviewRating::Hard, 'at' => $now->subDays(random_int(90, 110))->setTime(19, 0)],
                ['rating' => ReviewRating::Okay, 'at' => $now->subDays(random_int(20, 40))->setTime(19, 0)],
            ],
            default => [
                ['rating' => ReviewRating::Easy, 'at' => $now->subDays(random_int(200, 260))->setTime(19, 0)],
                ['rating' => ReviewRating::Easy, 'at' => $now->subDays(random_int(120, 160))->setTime(19, 0)],
                ['rating' => ReviewRating::Easy, 'at' => $now->subDays(random_int(40, 70))->setTime(19, 0)],
            ],
        };
    }

    /**
     * Gives a few hundred finished questions a rating history spread over the
     * past months, so Say it again has due and overdue items to work from.
     * Creates its own historical daily reviews to hold the ratings. Runs once;
     * a second run adds nothing.
     */
    private function seedQuestionRatings(): void
    {
        if (Rating::query()->where('kind', FeedbackType::Question->value)->exists()) {
            return;
        }

        $identities = PracticeQuestion::query()
            ->whereRaw("TRIM(question_text) <> ''")
            ->whereHas(
                'practiceSession',
                fn ($query) => $query->study(),
            )
            ->whereHas(
                'feedbackEntries',
                fn ($query) => $query
                    ->where('type', FeedbackType::NaturalVersion->value)
                    ->whereRaw("TRIM(content_text) <> ''"),
            )
            ->inRandomOrder()
            ->limit(self::TARGET_RATED_QUESTIONS)
            ->pluck('id');

        if ($identities->isEmpty()) {
            return;
        }

        $reviewIds = [];
        $now = CarbonImmutable::now();

        for ($day = 150; $day >= 1; $day -= random_int(2, 6)) {
            $moment = $now->subDays($day)->setTime(19, 0);

            $reviewIds[] = (int) DB::table('reviews')->insertGetId([
                'is_daily_review' => true,
                'completed_on' => $moment->toDateString(),
                'created_at' => $moment,
                'updated_at' => $moment,
            ]);
        }

        $sync = app(SyncReviewSchedule::class);

        foreach ($identities->values() as $position => $id) {
            $identity = (string) $id;

            $scenario = match ($position % 10) {
                0, 1 => 'hard_overdue',
                2, 3, 4 => 'okay_overdue',
                5, 6 => 'easy_run',
                7 => 'easy_fresh',
                8 => 'mixed_okay',
                default => 'easy_repeat',
            };

            $ratings = $this->ratingHistoryFor($scenario, $now);

            $picks = array_rand($reviewIds, count($ratings));
            $picks = is_array($picks) ? $picks : [$picks];

            foreach ($ratings as $index => $rating) {
                $moment = $rating['at'];
                $reviewId = $reviewIds[$picks[$index]];

                DB::table('ratings')->insert([
                    'review_id' => $reviewId,
                    'kind' => FeedbackType::Question->value,
                    'item_identity' => $identity,
                    'rating' => $rating['rating']->value,
                    'unique_key' => Rating::keyFor($reviewId, FeedbackType::Question, $identity),
                    'created_at' => $moment,
                    'updated_at' => $moment,
                ]);
            }

            $sync->handle(FeedbackType::Question, $identity);
        }
    }

    /**
     * Tops the My mistakes tab up to several thousand rows: the same pair of
     * what was said and the correction written several ways, the same mistake
     * with different reasons, the same correction on two different mistakes,
     * a repeated row in one box, rows with no correction, lines that could
     * not be split, very long texts, letters from outside English, and a
     * blank row here and there that must never be gathered. The target is
     * counted from the database, so a later run continues where the last one
     * stopped.
     *
     * @param  Collection<int, Topic>  $topics
     */
    private function seedMistakesShowcase(Collection $topics): void
    {
        $this->seedShowcaseRows(
            $topics,
            FeedbackType::Mistake,
            self::TARGET_MISTAKES,
            function (PracticeQuestion $question, int $position, int $row, int $index): void {
                $this->createShowcaseMistakeRow($question, $position, $row, $index);
            },
        );
    }

    /**
     * Adds mistake rows to one showcase question.
     */
    private function createShowcaseMistakeRow(
        PracticeQuestion $question,
        int $position,
        int $row,
        int $index,
    ): void {
        $mistake = $this->showcaseMistake($index, $position, $row);

        $this->createMistakeEntry($question, $row, $index, $mistake);

        // Two copies of one pair in the same box: the page counts them once,
        // as one appearance in that question.
        if ($mistake['repeated']) {
            $this->createMistakeEntry($question, $row, 9000 + $index, [
                ...$mistake,
                'said' => ' '.$mistake['said'].' ',
            ]);
        }
    }

    /**
     * One stored mistake row, as the editor would save it: a bold correction
     * beside its plain words.
     *
     * @param  array{said: string, correction: string, correction_html: string, reason: string, repeated: bool}  $mistake
     */
    private function createMistakeEntry(
        PracticeQuestion $question,
        int $row,
        int $index,
        array $mistake,
    ): void {
        PracticeFeedbackEntry::factory()->for($question)->create([
            'type' => FeedbackType::Mistake,
            'position' => $row,
            'client_id' => sprintf('mistake-showcase-%05d', $index),
            'content_text' => $mistake['said'],
            'correction_text' => $mistake['correction'],
            'correction_html' => $mistake['correction_html'],
            'reason' => $mistake['reason'],
            'is_split' => true,
        ]);
    }

    /**
     * The mistake for one showcase row: a four-way variant in the first box,
     * a curated common mistake now and then, and a unique sentence-sized
     * mistake for the rest so the tab really has thousands of groups.
     *
     * @return array{said: string, correction: string, correction_html: string, reason: string, repeated: bool}
     */
    private function showcaseMistake(int $index, int $position, int $row): array
    {
        if ($position === 1 && $row < count(self::SHOWCASE_MISTAKE_VARIANTS)) {
            $variant = self::SHOWCASE_MISTAKE_VARIANTS[$row];

            return [
                ...$variant,
                'correction_html' => $this->boldCorrection($variant['correction']),
                'repeated' => false,
            ];
        }

        // A blank row here and there proves the tab leaves it out: neither
        // column holds words, so the row never becomes a group.
        if ($index % 41 === 0) {
            return [
                'said' => '   ',
                'correction' => '',
                'correction_html' => '',
                'reason' => '',
                'repeated' => false,
            ];
        }

        // A line with more than one separator could not be split: the whole
        // line sits in the what-I-said column with no correction.
        if ($index % 11 === 0) {
            return [
                'said' => sprintf(
                    '%s, then %s, and finally %s.',
                    rtrim(fake()->sentence(2, false), '.'),
                    rtrim(fake()->sentence(2, false), '.'),
                    rtrim(fake()->sentence(2, false), '.'),
                ),
                'correction' => '',
                'correction_html' => '',
                'reason' => '',
                'repeated' => false,
            ];
        }

        // The same correction on two different mistakes: two groups, because
        // the pair is what identifies a mistake.
        if ($index % 19 === 0) {
            return [
                'said' => ucfirst(rtrim(fake()->sentence(6, false), '.')).'.',
                'correction' => 'I went to the shop yesterday.',
                'correction_html' => '<strong>I went</strong> to the shop yesterday.',
                'reason' => '',
                'repeated' => false,
            ];
        }

        // A mistake with a line long enough to be shortened in the list.
        if ($index % 13 === 0) {
            return [
                'said' => self::LONG_SAID,
                'correction' => self::LONG_REASON,
                'correction_html' => '<p>'.e(self::LONG_REASON).'</p>',
                'reason' => 'Keep the two tenses apart.',
                'repeated' => false,
            ];
        }

        // A repeated row in the same box, with the same correction again.
        if ($index % 15 === 0) {
            $entry = self::MISTAKES[$index % count(self::MISTAKES)];

            return [
                'said' => $entry['said'],
                'correction' => $entry['correction'],
                'correction_html' => $this->boldCorrection($entry['correction']),
                'reason' => $entry['reason'],
                'repeated' => true,
            ];
        }

        if ($index % 6 === 0) {
            $entry = self::MISTAKES[$index % count(self::MISTAKES)];
            $said = $entry['said'];
            $correction = $entry['correction'];

            // The same words written with different capitals or spacing are
            // still the same mistake.
            if ($index % 4 === 0) {
                $said = ' '.$said;
            }

            if ($index % 9 === 0) {
                $said = mb_strtoupper($said);
            }

            return [
                'said' => $said,
                'correction' => $correction,
                'correction_html' => $this->boldCorrection($correction),
                'reason' => $entry['reason'],
                'repeated' => false,
            ];
        }

        // A unique mistake for the rest, so most groups are seen exactly
        // once, the way a real notebook reads.
        $said = ucfirst(rtrim(fake()->sentence(8, false), '.')).'.';
        $correction = ucfirst(rtrim(fake()->sentence(8, false), '.')).'.';

        // Now and then the reason is left out, and now and then it is one the
        // learner wrote differently the time before.
        $reason = $index % 7 === 0
            ? ''
            : ucfirst(rtrim(fake()->sentence(5, false), '.')).'.';

        return [
            'said' => $said,
            'correction' => $correction,
            'correction_html' => $this->boldCorrection($correction),
            'reason' => $reason,
            'repeated' => false,
        ];
    }

    /** The correction as the editor saves it: the fixed words in bold. */
    private function boldCorrection(string $correction): string
    {
        return $correction === ''
            ? ''
            : '<strong>'.e($correction).'</strong>';
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
        $this->seedShowcaseRows(
            $topics,
            FeedbackType::Vocabulary,
            self::TARGET_VOCABULARY,
            function (PracticeQuestion $question, int $position, int $row, int $index): void {
                $this->createShowcaseRow($question, $position, $row, $index);
            },
        );
    }

    /**
     * Tops the phrases tab up to a few thousand rows, so the second
     * collection pages like the first: rows written several ways, rows that
     * could not be split, quiet no-meaning rows, and a blank row here and
     * there that must never be gathered.
     *
     * @param  Collection<int, Topic>  $topics
     */
    private function seedPhrasesShowcase(Collection $topics): void
    {
        $this->seedShowcaseRows(
            $topics,
            FeedbackType::Phrase,
            self::TARGET_PHRASES,
            function (PracticeQuestion $question, int $position, int $row, int $index): void {
                $this->createShowcasePhraseRow($question, $position, $row, $index);
            },
        );
    }

    /**
     * Adds phrase rows to one showcase question.
     */
    private function createShowcasePhraseRow(
        PracticeQuestion $question,
        int $position,
        int $row,
        int $index,
    ): void {
        $phrase = $this->showcasePhrase($index, $position, $row);

        $meaning = $phrase['meaning'];
        $example = $phrase['example'];

        if (! $phrase['variant']) {
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

            // A line with more than one separator: half of these keep their
            // example, so the page proves a phrase with one stays calm, and
            // the rest ask for help on their own.
            if ($index % 11 === 0) {
                $phrase['phrase'] = sprintf(
                    '%s -> %s -> %s',
                    rtrim(fake()->sentence(2, false), '.'),
                    rtrim(fake()->sentence(2, false), '.'),
                    rtrim(fake()->sentence(2, false), '.'),
                );

                if ($index % 22 !== 0) {
                    $meaning = '';
                    $example = '';
                }
            }

            // A blank row here and there proves it is left out.
            if ($index % 41 === 0) {
                $phrase['phrase'] = '   ';
            }
        }

        $exampleHtml = [];

        if ($example !== '' && $index % 4 === 0) {
            $html = '<p>'.e($example).'</p>';

            if ($index % 20 === 0) {
                $html = '<p><em>'.e($example).'</em> <strong>Say it warmly.</strong></p>';
            }

            $exampleHtml = [[
                'id' => sprintf('example-%05d', $index),
                'text' => $example,
                'html' => $html,
            ]];
        }

        PracticeFeedbackEntry::factory()->for($question)->create([
            'type' => FeedbackType::Phrase,
            'position' => $row,
            'client_id' => sprintf('phrase-showcase-%05d', $index),
            'content_text' => $phrase['phrase'],
            'correction_text' => $meaning,
            'example' => $example,
            'example_html' => $exampleHtml,
            'is_split' => true,
        ]);
    }

    /**
     * The phrase for one showcase row: a four-way variant in the first box, a
     * phrase from the curated pool now and then, and a unique sentence-sized
     * phrase for the rest so the tab really has thousands of items.
     *
     * @return array{phrase: string, meaning: string, example: string, variant: bool}
     */
    private function showcasePhrase(int $index, int $position, int $row): array
    {
        if ($position === 1 && $row < count(self::SHOWCASE_PHRASE_VARIANTS)) {
            return [...self::SHOWCASE_PHRASE_VARIANTS[$row], 'variant' => true];
        }

        if ($index % 6 === 0) {
            $entry = self::PHRASES[$index % count(self::PHRASES)];
            $phrase = $entry['phrase'];

            if ($index % 4 === 0) {
                $phrase = ' '.$phrase.' ';
            }

            if ($index % 9 === 0) {
                $phrase = mb_strtoupper($phrase);
            }

            return [
                'phrase' => $phrase,
                'meaning' => $entry['meaning'],
                'example' => $entry['example'],
                'variant' => false,
            ];
        }

        $term = rtrim(fake()->unique()->sentence(3, false), '.');

        return [
            'phrase' => ucfirst($term),
            'meaning' => 'a way to say “'.$term.'” in conversation',
            'example' => 'I heard about '.$term.' at the café.',
            'variant' => false,
        ];
    }

    /**
     * Tops the grammar notes tab up to a few thousand rows. Notes are never
     * merged, so the first box of every session says the same rule twice and
     * the page keeps both entries apart; the rest mix very long rules, rules
     * saved with formatting, rules with accents and quotes, and blank rows
     * that must never be gathered.
     *
     * @param  Collection<int, Topic>  $topics
     */
    private function seedGrammarShowcase(Collection $topics): void
    {
        $this->seedShowcaseRows(
            $topics,
            FeedbackType::GrammarNote,
            self::TARGET_GRAMMAR_NOTES,
            function (PracticeQuestion $question, int $position, int $row, int $index): void {
                $this->createShowcaseNoteRow($question, $position, $row, $index);
            },
        );
    }

    /**
     * Adds note rows to one showcase question.
     */
    private function createShowcaseNoteRow(
        PracticeQuestion $question,
        int $position,
        int $row,
        int $index,
    ): void {
        $note = $this->showcaseNote($position, $row, $index);

        PracticeFeedbackEntry::factory()->for($question)->create([
            'type' => FeedbackType::GrammarNote,
            'position' => $row,
            'client_id' => sprintf('note-showcase-%05d', $index),
            'content_text' => $note['text'],
            'content_html' => $note['html'],
        ]);
    }

    /**
     * The note for one showcase row: twice-saved rules and the long and
     * formatted cases in the first box, and a unique rule for the rest.
     *
     * @return array{text: string, html: string}
     */
    private function showcaseNote(int $position, int $row, int $index): array
    {
        if ($position === 1) {
            return match ($row % 4) {
                // The same rule twice: two entries, never one merged blob.
                0, 1 => [
                    'text' => 'Watch the past tense in this answer.',
                    'html' => '<p>Watch the <strong>past tense</strong> in this answer.</p>',
                ],
                // A rule longer than the list preview.
                2 => [
                    'text' => self::LONG_NOTE,
                    'html' => '<p>'.e(self::LONG_NOTE).'</p>',
                ],
                // A rule saved with formatting, accents and quotes.
                default => [
                    'text' => 'Use “an” before a vowel sound, and put the rules of the article in order as one list.',
                    'html' => '<p>Use <em>“an”</em> before a vowel sound, and put the rules of the article in order: <ul><li>one</li><li>two</li><li>three</li></ul></p>',
                ],
            };
        }

        // A blank row here and there proves it is left out of the tab.
        if ($index % 29 === 0) {
            return ['text' => '   ', 'html' => ''];
        }

        $rule = rtrim(fake()->unique()->sentence(4, false), '.');
        $text = ucfirst($rule).'.';
        $html = '<p>'.e($text).'</p>';

        if ($index % 13 === 0) {
            $text = self::LONG_NOTE;
            $html = '<p>'.e(self::LONG_NOTE).'</p>';
        }

        if ($index % 3 === 0) {
            $html = '<p>'.e($text).' <strong>Keep this one.</strong></p>';
        }

        if ($index % 5 === 0) {
            $accented = 'Réglé in French keeps its accents everywhere.';
            $text = $accented;
            $html = '<p>'.e($accented).' <ul><li>so does café</li><li>and façade</li></ul></p>';
        }

        return ['text' => $text, 'html' => $html];
    }

    /**
     * Tops the say-it-again pool up to a few thousand finished questions, so
     * the review screen has real work by hand.
     *
     * The pool mixes unique questions with the cases the card has to handle:
     * questions without a natural version, blank natural versions, formatted
     * text, very long text, accents and other scripts, and the same question
     * in two different sessions. Two extra questions sit in an archived and
     * an unfinished session, so the review proves it leaves them out. The
     * target is counted from the database, so a later run continues where the
     * last one stopped.
     *
     * @param  Collection<int, Topic>  $topics
     */
    private function seedQuestionsShowcase(Collection $topics): void
    {
        $remaining = self::TARGET_SAY_IT_AGAIN - $this->eligibleQuestionCount();

        if ($remaining > 0) {
            $index = 0;

            DB::transaction(function () use ($topics, $remaining, &$index): void {
                $left = $remaining;

                while ($left > 0) {
                    $session = $this->questionShowcaseSession($topics->random());

                    foreach (range(1, self::SHOWCASE_QUESTIONS) as $position) {
                        if ($this->createShowcaseQuestion($session, $position, $index++)) {
                            $left--;
                        }
                    }
                }
            });
        }

        $this->seedExcludedQuestions($topics);
    }

    /** How many questions Say it again can draw on right now. */
    private function eligibleQuestionCount(): int
    {
        return PracticeQuestion::query()
            ->whereRaw("TRIM(question_text) <> ''")
            ->whereHas(
                'practiceSession',
                fn ($query) => $query->study(),
            )
            ->whereHas(
                'feedbackEntries',
                fn ($query) => $query
                    ->where('type', FeedbackType::NaturalVersion->value)
                    ->whereRaw("TRIM(content_text) <> ''"),
            )
            ->count();
    }

    /** One finished session holding a screen of showcase questions. */
    private function questionShowcaseSession(Topic $topic): PracticeSession
    {
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

        return PracticeSession::factory()
            ->for($topic)
            ->create([
                'status' => PracticeSession::STATUS_FINISHED,
                'created_at' => $createdAt,
                'updated_at' => $lastChangedAt,
                'finished_at' => $finishedAt,
                'last_changed_at' => $lastChangedAt,
            ]);
    }

    /**
     * One showcase question, with a natural version unless this one is meant
     * to sit outside the pool.
     *
     * @return bool whether the question is eligible for Say it again
     */
    private function createShowcaseQuestion(
        PracticeSession $session,
        int $position,
        int $index,
    ): bool {
        $question = $this->showcaseQuestionText($index);

        $stored = PracticeQuestion::factory()->for($session)->create([
            'position' => $position,
            'question_text' => $question['text'],
            'question_html' => $question['html'],
            'answer_text' => '',
            'answer_html' => '',
        ]);

        // No natural version at all.
        if ($index % 7 === 0) {
            return false;
        }

        // A natural version that holds nothing readable.
        if ($index % 11 === 0) {
            PracticeFeedbackEntry::factory()->for($stored)->create([
                'type' => FeedbackType::NaturalVersion,
                'position' => 0,
                'content_text' => '   ',
                'content_html' => '',
            ]);

            return false;
        }

        $natural = $this->showcaseNatural($index);

        PracticeFeedbackEntry::factory()->for($stored)->create([
            'type' => FeedbackType::NaturalVersion,
            'position' => 0,
            'content_text' => $natural['text'],
            'content_html' => $natural['html'],
        ]);

        return true;
    }

    /**
     * The tutor's question for one showcase row, with the repeated, long,
     * special and formatted cases spread through the set.
     *
     * @return array{text: string, html: string}
     */
    private function showcaseQuestionText(int $index): array
    {
        if ($index % 13 === 0) {
            $pair = self::SHOWCASE_QUESTION_PAIRS[$index % count(self::SHOWCASE_QUESTION_PAIRS)];

            return [
                'text' => $pair['question'],
                'html' => '<p>'.e($pair['question']).'</p>',
            ];
        }

        if ($index % 23 === 0) {
            return [
                'text' => self::LONG_QUESTION,
                'html' => '<p>'.e(self::LONG_QUESTION).'</p>',
            ];
        }

        if ($index % 9 === 0) {
            $question = self::SPECIAL_QUESTIONS[$index % count(self::SPECIAL_QUESTIONS)];

            return [
                'text' => $question,
                'html' => '<p>'.e($question).'</p>',
            ];
        }

        if ($index % 37 === 0) {
            $opening = 'Before we meet, I’d like to know a few things.';
            $points = [
                'What time suits you best?',
                'Where should we meet?',
                'Is there anything I should bring?',
            ];

            return [
                'text' => $opening.' '.implode(' ', $points),
                'html' => '<p>'.e($opening).'</p><ul>'
                    .implode('', array_map(fn (string $point): string => '<li>'.e($point).'</li>', $points))
                    .'</ul>',
            ];
        }

        if ($index % 5 === 0) {
            $question = 'Could you recommend a quiet café near the station?';

            return [
                'text' => $question,
                'html' => '<p>Could you <strong>recommend</strong> a quiet café <em>near the station</em>?</p>',
            ];
        }

        $question = ucfirst(rtrim(fake()->unique()->sentence(8, false), '.')).'?';

        return [
            'text' => $question,
            'html' => '<p>'.e($question).'</p>',
        ];
    }

    /**
     * The natural version for one showcase question, matching the long,
     * special and formatted cases above.
     *
     * @return array{text: string, html: string}
     */
    private function showcaseNatural(int $index): array
    {
        if ($index % 13 === 0) {
            $pair = self::SHOWCASE_QUESTION_PAIRS[$index % count(self::SHOWCASE_QUESTION_PAIRS)];

            return [
                'text' => $pair['natural'],
                'html' => '<p>'.e($pair['natural']).'</p>',
            ];
        }

        if ($index % 23 === 0) {
            return [
                'text' => self::LONG_NATURAL,
                'html' => '<p>'.e(self::LONG_NATURAL).'</p>',
            ];
        }

        if ($index % 9 === 0) {
            $natural = 'A natural way to say it, with the non-English words kept exactly as they are: café, naïve, façade, smörgåsbord, 朝ごはん and 안녕하세요.';

            return [
                'text' => $natural,
                'html' => '<p>'.e($natural).'</p>',
            ];
        }

        if ($index % 31 === 0) {
            return [
                'text' => 'A more natural reply: start with the main point, add one detail, then invite the other person to respond.',
                'html' => '<p>A more natural reply:</p><ul>'
                    .'<li>start with the main point,</li>'
                    .'<li>add one detail,</li>'
                    .'<li>then invite the other person to respond.</li></ul>',
            ];
        }

        if ($index % 5 === 0) {
            $natural = 'I’d say it more simply: could you suggest a quiet café close to the station?';

            return [
                'text' => $natural,
                'html' => '<p>I’d say it more simply: could you suggest a <strong>quiet</strong> café <em>close to the station</em>?</p>',
            ];
        }

        $natural = ucfirst(rtrim(fake()->sentence(12, false), '.')).'.';

        return [
            'text' => $natural,
            'html' => '<p>'.e($natural).'</p>',
        ];
    }

    /**
     * One question kept in an archived session and one still waiting in an
     * unfinished one, both with a natural version, so the review proves it
     * only ever offers finished, visible sessions. Added once.
     *
     * @param  Collection<int, Topic>  $topics
     */
    private function seedExcludedQuestions(Collection $topics): void
    {
        if (! PracticeQuestion::query()
            ->where('question_text', self::EXCLUDED_ARCHIVED_QUESTION)
            ->exists()) {
            $finishedAt = $this->notInTheFuture(
                now()->subDays(45)->setTime(18, 0),
            );

            $archived = PracticeSession::factory()
                ->for($topics->random())
                ->create([
                    'status' => PracticeSession::STATUS_FINISHED,
                    'created_at' => $finishedAt->subMinutes(60),
                    'updated_at' => $finishedAt,
                    'finished_at' => $finishedAt,
                    'last_changed_at' => $finishedAt,
                    'archived_at' => $finishedAt->addDays(3),
                ]);

            $this->createExcludedQuestion($archived, self::EXCLUDED_ARCHIVED_QUESTION);
        }

        if (! PracticeQuestion::query()
            ->where('question_text', self::EXCLUDED_UNFINISHED_QUESTION)
            ->exists()) {
            $createdAt = $this->notInTheFuture(
                now()->subDays(20)->setTime(20, 0),
            );

            $unfinished = PracticeSession::factory()
                ->for($topics->random())
                ->create([
                    'status' => PracticeSession::STATUS_UNFINISHED,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                    'finished_at' => null,
                    'last_changed_at' => $createdAt,
                ]);

            $this->createExcludedQuestion($unfinished, self::EXCLUDED_UNFINISHED_QUESTION);
        }
    }

    private function createExcludedQuestion(PracticeSession $session, string $question): void
    {
        $stored = PracticeQuestion::factory()->for($session)->create([
            'position' => 1,
            'question_text' => $question,
            'question_html' => '<p>'.e($question).'</p>',
            'answer_text' => '',
            'answer_html' => '',
        ]);

        $natural = 'This natural version is real, but the session itself is out of reach.';

        PracticeFeedbackEntry::factory()->for($stored)->create([
            'type' => FeedbackType::NaturalVersion,
            'position' => 0,
            'content_text' => $natural,
            'content_html' => '<p>'.e($natural).'</p>',
        ]);
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
     * Tops one collection up to its target in rows of finished sessions,
     * eight questions with twelve rows in each one.
     *
     * The target is counted from the database, so a later run continues
     * where the last one stopped instead of adding everything again. A few
     * sessions keep showcase rows while sitting in the Archive, so the page
     * proves it leaves archived sessions out.
     *
     * @param  Collection<int, Topic>  $topics
     */
    private function seedShowcaseRows(
        Collection $topics,
        FeedbackType $type,
        int $target,
        Closure $createRow,
    ): void {
        $existing = PracticeFeedbackEntry::query()
            ->where('type', $type->value)
            ->count();

        if ($existing >= $target) {
            return;
        }

        $perSession = self::SHOWCASE_QUESTIONS * self::SHOWCASE_ROWS;
        $sessions = (int) ceil(
            ($target - $existing) / $perSession,
        );
        $index = 0;

        DB::transaction(function () use ($topics, $sessions, $createRow, &$index): void {
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
                        // A few showcase rows sit in archived sessions, so
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
                        $createRow($question, $position, $row, $index++);
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

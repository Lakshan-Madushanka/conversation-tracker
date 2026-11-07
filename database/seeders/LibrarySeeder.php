<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Collections\SaveItemMark;
use App\Actions\Collections\SaveItemNotes;
use App\Actions\CustomReviews\AddCustomReviewItems;
use App\Actions\CustomReviews\CreateCustomReview;
use App\Actions\CustomReviews\SetCustomReviewStatus;
use App\Actions\Reviews\DeclineStatusSuggestion;
use App\Actions\Reviews\FinishReviewEarly;
use App\Actions\Reviews\RecordRating;
use App\Actions\Reviews\SyncReviewSchedule;
use App\Actions\Reviews\UndoRating;
use App\Enums\CollectionItemStatus;
use App\Enums\CustomReviewAttemptStatus;
use App\Enums\CustomReviewStatus;
use App\Enums\FeedbackType;
use App\Enums\ReviewRating;
use App\Models\CollectionItemMark;
use App\Models\CustomReview;
use App\Models\CustomReviewAttempt;
use App\Models\CustomReviewAttemptItem;
use App\Models\CustomReviewItem;
use App\Models\PracticeFeedbackEntry;
use App\Models\PracticeQuestion;
use App\Models\PracticeSession;
use App\Models\Rating;
use App\Models\Review;
use App\Models\ReviewItem;
use App\Models\Topic;
use App\Queries\CustomReviewAttemptList;
use App\Queries\ReviewCandidates;
use App\Support\MistakeIdentity;
use App\Support\ReviewExercises;
use App\Support\ReviewFilters;
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
 * collections are topped up too: enough words, phrases, mistakes and grammar
 * notes to page through, with the awkward cases each page has to handle mixed
 * in. One showcase session per collection is the smallest set that still shows
 * every case, so the seed stays quick. It only runs locally and tops up to the
 * target instead of adding everything again on every run.
 */
class LibrarySeeder extends Seeder
{
    private const int TARGET_SESSIONS = 20;

    /**
     * Enough words to page through the Collection more than once, so "Load
     * more" and the quiet no-meaning rows can be tried by hand. One showcase
     * session holds 8 questions x 12 rows, and every awkward case the page
     * handles is keyed to a row number below that, so a single session shows
     * them all.
     */
    private const int TARGET_VOCABULARY = self::SHOWCASE_ROWS_PER_SESSION;

    /**
     * Enough phrases to page through the second tab the same way, with the
     * unsplittable rows and quiet no-meaning rows mixed in.
     */
    private const int TARGET_PHRASES = self::SHOWCASE_ROWS_PER_SESSION;

    /**
     * Enough notes to page through while the twice-saved rules stay apart.
     */
    private const int TARGET_GRAMMAR_NOTES = self::SHOWCASE_ROWS_PER_SESSION;

    /**
     * Enough mistakes to practice My mistakes by hand: grouped pairs, with the
     * same mistake seen one, two, three and many times mixed through the tab.
     */
    private const int TARGET_MISTAKES = self::SHOWCASE_ROWS_PER_SESSION;

    /**
     * How many distinct words and phrases get a rating history, so the
     * daily suggestion has due, overdue and never-reviewed items to
     * choose from.
     */
    private const int TARGET_RATED_ITEMS = 90;

    /**
     * Enough questions with a natural version to practice Say it again by
     * hand, with questions that have no natural version, blank ones,
     * formatted and very long text, and the same question in two sessions
     * mixed through the set.
     */
    private const int TARGET_SAY_IT_AGAIN = 48;

    /**
     * How many of those questions get a rating history over many days, so
     * the daily suggestion has due, overdue and never-reviewed questions.
     */
    private const int TARGET_RATED_QUESTIONS = 40;

    /**
     * A review large enough to prove its page reads and scrolls without a
     * growing number of queries, with every kind alongside it.
     */
    private const int TARGET_REVIEW_ITEMS = 2000;

    /**
     * Enough custom reviews to page through the listing several times, with
     * every status, an oversized review, an all-archived review and names and
     * descriptions that search has to cope with.
     */
    private const int TARGET_CUSTOM_REVIEWS = 300;

    /** Attempts per ordinary practice review, so the seed holds about 500. */
    private const int BULK_ATTEMPTS_PER_REVIEW = 20;

    /** Ordinary reviews that each carry a long practice history. */
    private const int BULK_ATTEMPT_REVIEWS = 24;

    /** A description long enough to be shortened on a card. */
    private const string LONG_REVIEW_DESCRIPTION = 'A set I keep coming back to when I want to sound more natural: the words I actually use at the café, the phrases that make a request sound polite rather than abrupt, and the small corrections that took me years to notice. Read it slowly, say each line out loud, and add anything new that still feels awkward.';

    /**
     * Names that exercise search: accents, other languages, punctuation and
     * spaces. Most reviews take one of these and a number, so every name stays
     * tellable apart while the set still looks lived in.
     *
     * @var list<string>
     */
    private const array CUSTOM_REVIEW_NAMES = [
        'Café conversations',
        'Résumé and cover letters',
        'Reise nach Berlin',
        'Conversación en la estación',
        'Les petits riens',
        'Kleine Dinge',
        '¿Cómo estás?',
        '日本語の練習',
        '한국어 회화',
        'Gespräche beim Arzt',
        'Rendez-vous au café',
        'Smörgåsbord of phrases',
        'Naïve questions',
        'Crème brûlée evening',
        'Façade and first impressions',
        'Gemütlich at home',
        'Piñata party',
        'Jalapeño kitchen',
        'Doppelgänger stories',
        'Zeitgeist reading',
        'Wanderlust and trains',
        'Entrepreneur small talk',
        'Rendezvous at the station',
        'Bouquet of thanks',
        'Liaison at work',
        'Soufflé Sunday',
        'Crêpe afternoon',
        'Mêlée at the market',
        'Tsunami of questions',
        'Karaoke night',
    ];

    /**
     * The descriptions the listing's search is tried against: none, short
     * everyday lines, and one long enough to be shortened on a card.
     *
     * @var list<string|null>
     */
    private const array CUSTOM_REVIEW_DESCRIPTIONS = [
        null,
        'Phrases for the airport and the train station.',
        'Ordering food at a restaurant, politely.',
        self::LONG_REVIEW_DESCRIPTION,
        'Words and phrases I keep mixing up on the phone.',
        'Small talk before a meeting.',
        'Doctor’s appointment vocabulary.',
        'Things I want to say without hesitating.',
    ];

    /**
     * Distinct items the recent sittings are built from: enough for six
     * sittings, the runs the finish screen reads, and a few items left out of
     * every sitting so the library is not all spoken for.
     */
    private const int RECENT_POOL = 20;

    /** One showcase session: eight questions with twelve rows each. */
    private const int SHOWCASE_QUESTIONS = 8;

    private const int SHOWCASE_ROWS = 12;

    /** The rows one showcase session adds, so targets are counted in sessions. */
    private const int SHOWCASE_ROWS_PER_SESSION = self::SHOWCASE_QUESTIONS * self::SHOWCASE_ROWS;

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

        // One transaction for the whole set. Sqlite otherwise pays a disk sync
        // for every single row, which costs far more time than the rows do.
        DB::transaction(function () use ($topics): void {
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
            $this->seedRecentSittings();
            $this->seedCustomReviews($topics);
            $this->seedCustomReviewAttempts();
        });
    }

    /**
     * The reviews a learner would build up over a week: the handful whose
     * every detail each page has to cope with, then enough ordinary reviews to
     * page, search, filter and sort the listing by hand.
     *
     * @param  Collection<int, Topic>  $topics
     */
    private function seedCustomReviews(Collection $topics): void
    {
        $this->seedStartingReviews($topics);
        $this->seedListingReviews();
    }

    /**
     * Practice history for the pages that read it: a few reviews whose attempts
     * show every status, an attempt left In progress to resume, a Not started
     * attempt, a review whose items changed after it was practised, and enough
     * ordinary reviews with long histories to page and load.
     *
     * Attempts are written once and never updated, exactly as the app does it,
     * so an existing attempt means an earlier run already added the set.
     */
    private function seedCustomReviewAttempts(): void
    {
        if (CustomReviewAttempt::query()->exists()) {
            return;
        }

        $this->seedAttemptShowcases();
        $this->seedBulkAttempts();
    }

    /**
     * The handful of histories the pages have to cope with: every attempt
     * status, a running attempt at the top, a review that is Draft again but
     * keeps its attempts, a completed attempt with skipped items, and a review
     * whose words changed after the attempt took its snapshot.
     */
    private function seedAttemptShowcases(): void
    {
        $this->seedAttemptedReview(
            name: 'Practice log',
            reviewStatus: CustomReviewStatus::Finished,
            kinds: [
                [FeedbackType::Vocabulary, 8],
                [FeedbackType::Phrase, 4],
                [FeedbackType::GrammarNote, 2],
                [FeedbackType::Mistake, 2],
                [FeedbackType::Question, 2],
            ],
            states: [
                CustomReviewAttemptStatus::Completed,
                CustomReviewAttemptStatus::FinishedEarly,
                CustomReviewAttemptStatus::Completed,
                CustomReviewAttemptStatus::NotStarted,
                CustomReviewAttemptStatus::FinishedEarly,
                CustomReviewAttemptStatus::Completed,
                CustomReviewAttemptStatus::InProgress,
            ],
            firstStartedDaysAgo: 20,
        );

        // Draft again, but the attempt history stays exactly as it was.
        $this->seedAttemptedReview(
            name: 'Words for the week',
            reviewStatus: CustomReviewStatus::Draft,
            kinds: [[FeedbackType::Vocabulary, 6]],
            states: [
                CustomReviewAttemptStatus::Completed,
                CustomReviewAttemptStatus::FinishedEarly,
                CustomReviewAttemptStatus::Completed,
            ],
            firstStartedDaysAgo: 12,
        );

        // Every item settled, with the second half skipped rather than rated.
        $this->seedAttemptedReview(
            name: 'Skipped practice',
            reviewStatus: CustomReviewStatus::Finished,
            kinds: [[FeedbackType::Vocabulary, 12], [FeedbackType::Phrase, 4]],
            states: [
                CustomReviewAttemptStatus::FinishedEarly,
                CustomReviewAttemptStatus::Completed,
            ],
            firstStartedDaysAgo: 9,
            skipFrom: 8,
        );

        // The two attempts were taken before one meaning was reworded, so
        // their snapshots still show the old one while the review shows the new.
        $edited = $this->seedAttemptedReview(
            name: 'Edited after practice',
            reviewStatus: CustomReviewStatus::Finished,
            kinds: [[FeedbackType::Vocabulary, 5]],
            states: [
                CustomReviewAttemptStatus::Completed,
                CustomReviewAttemptStatus::FinishedEarly,
            ],
            firstStartedDaysAgo: 5,
        );

        $this->editOneAttemptedWord($edited);
    }

    /**
     * Enough ordinary reviews with long histories to page through a review's
     * attempts and to fill the listing's recently attempted order.
     */
    private function seedBulkAttempts(): void
    {
        for ($index = 0; $index < self::BULK_ATTEMPT_REVIEWS; $index++) {
            $states = [];

            for ($attempt = 1; $attempt <= self::BULK_ATTEMPTS_PER_REVIEW; $attempt++) {
                $states[] = match (true) {
                    $attempt === self::BULK_ATTEMPTS_PER_REVIEW && $index % 4 === 0 => CustomReviewAttemptStatus::InProgress,
                    $attempt % 6 === 0 => CustomReviewAttemptStatus::FinishedEarly,
                    default => CustomReviewAttemptStatus::Completed,
                };
            }

            $this->seedAttemptedReview(
                name: 'Practice log '.($index + 1),
                reviewStatus: CustomReviewStatus::Finished,
                kinds: [[FeedbackType::Vocabulary, 20]],
                states: $states,
                firstStartedDaysAgo: 60,
            );
        }
    }

    /**
     * Creates a Finished or Draft review holding a little of each chosen kind,
     * then one attempt per status in `$states`.
     *
     * Every attempt copies the snapshot list the review would start with right
     * now, so its cards have the same shape a real start would write. Ratings
     * are spread over the items the way a learner's would be: a completed
     * attempt settles every item, an early finish keeps what was rated, and a
     * running one stops partway. A Not started attempt keeps its items but no
     * number and no rating.
     *
     * @param  list<array{0: FeedbackType, 1: int}>  $kinds
     * @param  list<CustomReviewAttemptStatus>  $states
     */
    private function seedAttemptedReview(
        string $name,
        CustomReviewStatus $reviewStatus,
        array $kinds,
        array $states,
        int $firstStartedDaysAgo,
        ?int $skipFrom = null,
    ): CustomReview {
        $create = app(CreateCustomReview::class);
        $add = app(AddCustomReviewItems::class);
        $setStatus = app(SetCustomReviewStatus::class);
        $list = app(CustomReviewAttemptList::class);

        $review = $create->handle();
        $review->name = $name;
        $review->save();

        foreach ($kinds as [$kind, $limit]) {
            $add->handle(
                $review,
                $kind,
                $this->listingReviewKeys($kind, $limit, false),
            );
        }

        if ($reviewStatus === CustomReviewStatus::Finished) {
            $setStatus->handle($review, CustomReviewStatus::Finished);
        }

        $items = $list->for($review);
        $number = 0;
        $now = CarbonImmutable::now();

        foreach ($states as $index => $state) {
            $startedAt = $now
                ->subDays($firstStartedDaysAgo)
                ->addDays($index * 2)
                ->addMinutes($index);

            if ($state->isCounted()) {
                $number += 1;
            }

            $this->writeSeededAttempt(
                review: $review,
                items: $items,
                number: $state->isCounted() ? $number : null,
                status: $state,
                startedAt: $startedAt,
                skipFrom: $skipFrom,
            );
        }

        return $review;
    }

    /**
     * Writes one attempt and its snapshot items in two inserts, the way the
     * start action writes them in one transaction.
     *
     * @param  list<array{
     *     position: int,
     *     kind: FeedbackType,
     *     identity: string,
     *     exercise: string,
     *     card: array<string, mixed>,
     *     snapshot_text: string,
     *     topic: string,
     *     session_date: CarbonImmutable|null,
     *     source_session_id: int,
     *     source_url: string
     * }>  $items
     */
    private function writeSeededAttempt(
        CustomReview $review,
        array $items,
        ?int $number,
        CustomReviewAttemptStatus $status,
        CarbonImmutable $startedAt,
        ?int $skipFrom,
    ): void {
        $attempt = CustomReviewAttempt::query()->create([
            'custom_review_id' => $review->id,
            'number' => $number,
            'status' => $status,
            'started_at' => $status === CustomReviewAttemptStatus::NotStarted ? null : $startedAt,
            'finished_at' => $status->isFinished() ? $startedAt->addMinutes(12) : null,
        ]);

        $ratings = [ReviewRating::Hard, ReviewRating::Okay, ReviewRating::Easy];
        $ratedShare = match ($status) {
            CustomReviewAttemptStatus::Completed => count($items),
            CustomReviewAttemptStatus::FinishedEarly => (int) ceil(count($items) / 2),
            CustomReviewAttemptStatus::InProgress => (int) ceil(count($items) / 3),
            CustomReviewAttemptStatus::NotStarted => 0,
        };
        $rows = [];
        $takenAt = $status === CustomReviewAttemptStatus::NotStarted
            ? CarbonImmutable::now()
            : $startedAt;

        foreach ($items as $index => $item) {
            $rated = $index < $ratedShare;
            $skipped = $rated && $skipFrom !== null && $index >= $skipFrom;

            $rows[] = [
                'custom_review_attempt_id' => $attempt->id,
                'position' => $item['position'],
                'kind' => $item['kind']->value,
                'exercise' => $item['exercise'],
                'item_identity' => $item['identity'],
                'snapshot_text' => $item['snapshot_text'],
                'snapshot' => json_encode($item['card'], JSON_THROW_ON_ERROR),
                'topic' => $item['topic'],
                'session_date' => $item['session_date'],
                'source_session_id' => $item['source_session_id'],
                'source_url' => $item['source_url'],
                'snapshot_taken_at' => $takenAt,
                'rating' => $rated && ! $skipped ? $ratings[$index % 3]->value : null,
                'rated_at' => $rated && ! $skipped ? $startedAt->addMinutes($index) : null,
                'rating_key' => CustomReviewAttemptItem::ratingKeyFor(
                    (int) $attempt->id,
                    $item['kind'],
                    $item['identity'],
                ),
                'skipped_at' => $skipped ? $startedAt->addMinutes($index) : null,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            CustomReviewAttemptItem::query()->insert($chunk);
        }
    }

    /**
     * Rewrites one meaning the edited review already practised. The item's
     * identity is its word, so the reference still holds, while the attempts
     * keep the meaning that was on screen when they were taken.
     */
    private function editOneAttemptedWord(CustomReview $review): void
    {
        $item = CustomReviewItem::query()
            ->where('custom_review_id', $review->id)
            ->where('kind', FeedbackType::Vocabulary->value)
            ->orderBy('id')
            ->first();

        if (! $item instanceof CustomReviewItem) {
            return;
        }

        $entry = PracticeFeedbackEntry::query()
            ->where('type', FeedbackType::Vocabulary->value)
            ->where('collection_key', $item->item_identity)
            ->first();

        if (! $entry instanceof PracticeFeedbackEntry) {
            return;
        }

        $entry->correction_text .= ' (reworded after practice)';
        $entry->correction_html = '<p>'.e($entry->correction_text).'</p>';
        $entry->save();
    }

    /**
     * The reviews a learner would build up over a week, in the sizes the
     * pages have to cope with: one still empty, one holding a single item, and
     * one holding a little of every collection, an item that only sits in an
     * archived session, a reference to an item whose session was deleted, and
     * a couple of thousand words. Clearing the last one is the quickest way to
     * see the empty state.
     *
     * @param  Collection<int, Topic>  $topics
     */
    private function seedStartingReviews(Collection $topics): void
    {
        if (CustomReview::query()->exists()) {
            return;
        }

        $create = app(CreateCustomReview::class);
        $add = app(AddCustomReviewItems::class);

        // The two sizes a review begins at: nothing in it yet, and one item.
        $create->handle();

        $single = $create->handle();
        $add->handle(
            $single,
            FeedbackType::Vocabulary,
            $this->reviewKeys(FeedbackType::Vocabulary, 1),
        );

        // A review with something in every group its page shows, one of the
        // words archived and one reference left by a deleted item.
        $mixed = $create->handle();

        $add->handle($mixed, FeedbackType::Vocabulary, [
            ...$this->reviewKeys(FeedbackType::Vocabulary, 2),
            ...$this->reviewKeys(FeedbackType::Vocabulary, 1, archived: true),
        ]);
        $add->handle(
            $mixed,
            FeedbackType::Phrase,
            $this->reviewKeys(FeedbackType::Phrase, 1),
        );
        $add->handle($mixed, FeedbackType::GrammarNote, $this->reviewNoteKeys(1));
        $add->handle($mixed, FeedbackType::Mistake, $this->reviewMistakeKeys(1));
        $add->handle($mixed, FeedbackType::Question, $this->reviewQuestionKeys(1));

        $add->handle(
            $mixed,
            FeedbackType::Vocabulary,
            $this->reviewScaleIdentities($topics->random()),
        );

        // A reference left behind by a deleted item. Opening the review takes
        // it away, so the seeded state proves the repair.
        CustomReviewItem::query()->insert([
            'custom_review_id' => $mixed->id,
            'kind' => FeedbackType::Vocabulary->value,
            'item_identity' => SearchText::normalize('an item whose session was deleted'),
            'added_at' => now(),
        ]);
    }

    /**
     * Enough ordinary reviews to page, search, filter and sort the listing by
     * hand. Most are empty; a few carry items so Draft, Finished and the
     * unavailable note all appear, one is far past the recommended size, and
     * the names and descriptions mix accents, other scripts and long text.
     */
    private function seedListingReviews(): void
    {
        $missing = self::TARGET_CUSTOM_REVIEWS - CustomReview::query()->count();

        if ($missing <= 0) {
            return;
        }

        $now = CarbonImmutable::now();
        $names = self::CUSTOM_REVIEW_NAMES;
        $descriptions = self::CUSTOM_REVIEW_DESCRIPTIONS;
        $rows = [];

        for ($index = 0; $index < $missing; $index++) {
            $name = $names[$index % count($names)].' '.($index + 1);
            $description = $descriptions[$index % count($descriptions)];
            $at = $now->subDays($index % 330)->subMinutes($index);

            $rows[] = [
                'name' => $name,
                'description' => $description,
                'search_text' => SearchText::normalize(
                    trim($name.' '.($description)),
                ),
                'status' => CustomReviewStatus::Empty->value,
                'created_at' => $at,
                'updated_at' => $at,
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('custom_reviews')->insert($chunk);
        }

        /** @var Collection<string, CustomReview> $byName */
        $byName = CustomReview::query()
            ->whereIn('name', array_column($rows, 'name'))
            ->get()
            ->keyBy('name');

        $add = app(AddCustomReviewItems::class);
        $setStatus = app(SetCustomReviewStatus::class);

        foreach ($this->listingReviewPlans($missing) as $index => $plan) {
            $row = $rows[$index] ?? null;

            if ($row === null) {
                continue;
            }

            $review = $byName->get($row['name']);

            if (! $review instanceof CustomReview) {
                continue;
            }

            foreach ($plan['items'] as [$kind, $limit, $archived]) {
                $add->handle(
                    $review,
                    $kind,
                    $this->listingReviewKeys($kind, $limit, $archived),
                );
            }

            $total = CustomReviewItem::query()
                ->where('custom_review_id', $review->id)
                ->count();

            if ($plan['status'] === CustomReviewStatus::Finished && $total > 0) {
                $setStatus->handle($review, CustomReviewStatus::Finished);
            }
        }
    }

    /**
     * Which of the ordinary reviews hold items, and how many. The first is
     * past the recommended size, the second is finished with nothing usable
     * right now, and the rest show the everyday Draft and Finished cases.
     *
     * @return array<int, array{
     *     status: CustomReviewStatus,
     *     items: list<array{0: FeedbackType, 1: int, 2: bool}>
     * }>
     */
    private function listingReviewPlans(int $available): array
    {
        $plans = [
            0 => $this->listingReviewPlan(
                CustomReviewStatus::Finished,
                [[FeedbackType::Vocabulary, 25, false]],
            ),
            1 => $this->listingReviewPlan(
                CustomReviewStatus::Finished,
                [[FeedbackType::Vocabulary, 5, true]],
            ),
            2 => $this->listingReviewPlan(CustomReviewStatus::Draft, [
                [FeedbackType::Vocabulary, 3, false],
                [FeedbackType::Phrase, 2, false],
            ]),
            3 => $this->listingReviewPlan(CustomReviewStatus::Finished, [
                [FeedbackType::Vocabulary, 1, false],
                [FeedbackType::GrammarNote, 1, false],
                [FeedbackType::Mistake, 1, false],
                [FeedbackType::Question, 1, false],
            ]),
            4 => $this->listingReviewPlan(
                CustomReviewStatus::Finished,
                [[FeedbackType::Phrase, 2, false]],
            ),
        ];

        for ($index = 5; $index <= 10; $index++) {
            $plans[$index] = $this->listingReviewPlan(
                CustomReviewStatus::Draft,
                [[FeedbackType::Vocabulary, 1 + $index % 3, false]],
            );
        }

        return array_filter(
            $plans,
            fn (int $index): bool => $index < $available,
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * One review's status and the items it should gather.
     *
     * @param  list<array{0: FeedbackType, 1: int, 2: bool}>  $items
     * @return array{status: CustomReviewStatus, items: list<array{0: FeedbackType, 1: int, 2: bool}>}
     */
    private function listingReviewPlan(
        CustomReviewStatus $status,
        array $items,
    ): array {
        return ['status' => $status, 'items' => $items];
    }

    /**
     * The identities one seeded review should hold, read by kind so notes and
     * questions get their own identity and the table kinds share one helper.
     *
     * @return list<string>
     */
    private function listingReviewKeys(
        FeedbackType $kind,
        int $limit,
        bool $archived,
    ): array {
        return match ($kind) {
            FeedbackType::GrammarNote => $this->reviewNoteKeys($limit),
            FeedbackType::Mistake => $this->reviewMistakeKeys($limit),
            FeedbackType::Question => $this->reviewQuestionKeys($limit),
            default => $this->reviewKeys($kind, $limit, $archived),
        };
    }

    /**
     * The identities of a few items of one table kind, from a live session or
     * an archived one.
     *
     * @return list<string>
     */
    private function reviewKeys(
        FeedbackType $kind,
        int $limit,
        bool $archived = false,
    ): array {
        /** @var list<string> $keys */
        $keys = $this->reviewEntries($kind, $limit, $archived)
            ->pluck('collection_key')
            ->filter()
            ->values()
            ->all();

        return $keys;
    }

    /**
     * The identities of a few grammar notes, each named by its own row.
     *
     * @return list<string>
     */
    private function reviewNoteKeys(int $limit): array
    {
        /** @var list<string> $keys */
        $keys = $this->reviewEntries(FeedbackType::GrammarNote, $limit)
            ->pluck('id')
            ->map(fn (int $id): string => 'note-'.$id)
            ->values()
            ->all();

        return $keys;
    }

    /**
     * The identities of a few mistakes, each named by the key of its pair.
     *
     * @return list<string>
     */
    private function reviewMistakeKeys(int $limit): array
    {
        /** @var list<string> $keys */
        $keys = $this->reviewEntries(FeedbackType::Mistake, $limit, tablePair: true)
            ->map(fn (PracticeFeedbackEntry $entry): string => MistakeIdentity::pairKey(
                (string) $entry->content_text,
                (string) $entry->correction_text,
            ))
            ->values()
            ->all();

        return $keys;
    }

    /**
     * The identities of a few questions, each named by its own id.
     *
     * @return list<string>
     */
    private function reviewQuestionKeys(int $limit): array
    {
        /** @var list<string> $keys */
        $keys = PracticeQuestion::query()
            ->whereHas('practiceSession', fn ($query) => $query->study())
            ->inRandomOrder()
            ->limit($limit)
            ->pluck('id')
            ->map(fn (int $id): string => (string) $id)
            ->values()
            ->all();

        return $keys;
    }

    /**
     * Rows of one kind that a review can name, from a live session or, when
     * asked, an archived one. Mistakes need both halves of the pair to have an
     * identity.
     *
     * @return Collection<int, PracticeFeedbackEntry>
     */
    private function reviewEntries(
        FeedbackType $kind,
        int $limit,
        bool $archived = false,
        bool $tablePair = false,
    ): Collection {
        return PracticeFeedbackEntry::query()
            ->where('type', $kind->value)
            ->whereRaw("TRIM(collection_key) <> ''")
            ->when($archived, fn ($query) => $query->whereHas(
                'practiceQuestion.practiceSession',
                fn ($session) => $session->whereNotNull('archived_at'),
            ))
            ->when(! $archived, fn ($query) => $query->whereHas(
                'practiceQuestion.practiceSession',
                fn ($session) => $session->study(),
            ))
            ->when($tablePair, fn ($query) => $query
                ->whereRaw("TRIM(content_text) <> ''")
                ->whereRaw("TRIM(correction_text) <> ''"))
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }

    /**
     * One finished session holding a long list of plain words, so a review can
     * hold a couple of thousand items with real sources behind them. Only
     * the columns the projections need are written; the rest keep their
     * defaults.
     *
     * @return list<string>
     */
    private function reviewScaleIdentities(Topic $topic): array
    {
        /** @var list<string> $existing */
        $existing = PracticeFeedbackEntry::query()
            ->where('type', FeedbackType::Vocabulary->value)
            ->whereHas(
                'practiceQuestion',
                fn ($query) => $query->where('question_text', 'Scale check'),
            )
            ->orderBy('id')
            ->pluck('collection_key')
            ->filter()
            ->map(fn (string $key): string => $key)
            ->unique()
            ->values()
            ->all();

        // Clearing a review is a normal way to see the empty state, so a
        // later run tops the same session back up instead of adding another.
        if ($existing !== []) {
            return $existing;
        }

        $session = $this->questionShowcaseSession($topic);
        $question = PracticeQuestion::factory()->for($session)->create([
            'position' => 1,
            'question_text' => 'Scale check',
            'question_html' => '<p>Scale check</p>',
            'answer_text' => '',
            'answer_html' => '',
        ]);

        $at = $session->created_at;
        $words = [];
        $rows = [];

        for ($index = 1; $index <= self::TARGET_REVIEW_ITEMS; $index++) {
            $word = sprintf('Scale word %04d', $index);
            $meaning = 'one of the words that make the review long';
            $words[] = SearchText::normalize($word);
            $rows[] = [
                'practice_question_id' => $question->id,
                'type' => FeedbackType::Vocabulary->value,
                'position' => $index - 1,
                'client_id' => sprintf('scale-word-%05d', $index),
                'content_html' => '',
                'content_text' => $word,
                'correction_html' => '',
                'correction_text' => $meaning,
                'reason' => '',
                'is_split' => true,
                'example' => '',
                'example_html' => '[]',
                'search_text' => SearchText::normalize($word.' '.$meaning),
                'collection_key' => SearchText::normalize($word),
                'sort_text' => SearchText::normalize($word),
                'attention_reason' => null,
                'split_broke' => false,
                'created_at' => $at,
                'updated_at' => $at,
            ];
        }

        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('practice_feedback_entries')->insert($chunk);
        }

        return $words;
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
     * The last six sittings, left the way the learner walked away from them:
     * daily and custom reviews worked straight through, one short look stopped
     * part-way, one rating taken back and given again, and histories that stop
     * exactly where the finish screen offers a status for one. Recent results,
     * a past sitting opened as a result and the status offers all read from
     * these. Runs once; a second run adds nothing.
     */
    private function seedRecentSittings(): void
    {
        if (Review::query()->whereNotNull('finished_at')->exists()) {
            return;
        }

        $pool = $this->recentSittingPool();

        if (count($pool) < self::RECENT_POOL) {
            return;
        }

        // The six items whose runs of ratings the finish screen reads, the
        // plain items one sitting is starred from, and the questions and notes
        // that keep every exercise kind present.
        $run = array_slice($pool, 0, 6);
        [$easyRun, $hardRun, $declinedRun, $alreadyLearned, $brokenRun, $alreadyDifficult] = $run;
        $marks = array_slice($pool, 6, 6);
        $mixed = array_slice($pool, 12, 8);

        $at = fn (int $daysAgo): CarbonImmutable => CarbonImmutable::now()->subDays($daysAgo)->setTime(19, 30);

        // Six days ago: the day's sitting, worked straight through. The oldest
        // of the six, so it falls off the end of the recent list.
        $first = $this->openSitting([...$marks, $mixed[0], $mixed[1]], daily: true, at: $at(6));
        $this->rateSitting($first, [
            ReviewRating::Easy, ReviewRating::Easy, ReviewRating::Okay, ReviewRating::Hard,
            ReviewRating::Easy, ReviewRating::Okay, ReviewRating::Easy, ReviewRating::Hard,
        ], $at(6));
        $this->closeSitting($first, $at(6), daily: true);

        // Five days ago: a custom sitting, chosen down to a few collections.
        $second = $this->openSitting([...array_slice($marks, 0, 4), $mixed[2], $mixed[3]], daily: false, at: $at(5));
        $this->rateSitting($second, [
            ReviewRating::Okay, ReviewRating::Easy, ReviewRating::Hard,
            ReviewRating::Okay, ReviewRating::Easy, ReviewRating::Okay,
        ], $at(5));
        $this->closeSitting($second, $at(5), daily: false);

        // Four days ago: the first Easy of the runs the finish screen reads.
        $third = $this->openSitting([$easyRun, $declinedRun, $alreadyLearned, $brokenRun, ...array_slice($marks, 2)], daily: true, at: $at(4));
        $rated = $this->rateSitting($third, [
            ReviewRating::Easy, ReviewRating::Easy, ReviewRating::Easy, ReviewRating::Easy,
            ReviewRating::Hard, ReviewRating::Okay, ReviewRating::Easy, ReviewRating::Easy,
        ], $at(4));
        $this->closeSitting($third, $at(4), daily: true);

        // Three days ago: a starred-only sitting stopped after two cards.
        $this->starItems(array_slice($marks, 0, 5));
        $fourth = $this->openSitting(array_slice($marks, 0, 5), daily: false, at: $at(3));
        $this->rateSitting($fourth, [ReviewRating::Easy, ReviewRating::Okay], $at(3));
        $this->stopSittingEarly($fourth, $at(3));

        // Two days ago: the runs grow, and one rating is taken back and given
        // again, so the sitting shows the second answer and counts it once.
        $fifth = $this->openSitting([...$run, $mixed[4], $mixed[5]], daily: true, at: $at(2));
        $this->rateSitting($fifth, [
            ReviewRating::Easy, ReviewRating::Hard, ReviewRating::Easy, ReviewRating::Easy,
            ReviewRating::Okay, ReviewRating::Hard, ReviewRating::Easy, ReviewRating::Okay,
        ], $at(2));
        $this->rateAgain($fifth, $mixed[4], ReviewRating::Hard, $at(2)->addMinutes(20));
        $this->closeSitting($fifth, $at(2), daily: true);

        // Yesterday: a custom sitting that ends the runs. Its finish screen
        // offers Learned for the three Easy in a row and Still difficult for
        // the two Hard, and stays quiet about everything else.
        $sixth = $this->openSitting([...$run, $mixed[6]], daily: false, at: $at(1));
        $this->rateSitting($sixth, [
            ReviewRating::Easy, ReviewRating::Hard, ReviewRating::Easy, ReviewRating::Easy,
            ReviewRating::Easy, ReviewRating::Hard, ReviewRating::Okay,
        ], $at(1));
        $this->closeSitting($sixth, $at(1), daily: false);

        // The marks the offers are read against: two items carry no status at
        // all, two already carry the status a run would earn.
        $saveMark = app(SaveItemMark::class);
        $saveMark->handle($easyRun['kind'], $easyRun['identity'], null, null);
        $saveMark->handle($hardRun['kind'], $hardRun['identity'], null, null);
        $saveMark->handle($declinedRun['kind'], $declinedRun['identity'], null, null);
        $saveMark->handle($alreadyLearned['kind'], $alreadyLearned['identity'], null, CollectionItemStatus::Learned);
        $saveMark->handle($alreadyDifficult['kind'], $alreadyDifficult['identity'], null, CollectionItemStatus::StillDifficult);

        // The "Not now" the learner already gave, tied to the run that began
        // with this item's Easy rating four days ago.
        $declinedKey = app(ReviewCandidates::class)->key($declinedRun['kind'], $declinedRun['identity']);
        $declinedRating = $rated[$declinedKey] ?? null;

        if ($declinedRating instanceof Rating) {
            app(DeclineStatusSuggestion::class)->handle(
                $declinedRun['kind'],
                $declinedRun['identity'],
                CollectionItemStatus::Learned,
                $declinedRating->id,
            );
        }

        // The schedules are rebuilt last, from the history as it now stands.
        $sync = app(SyncReviewSchedule::class);

        foreach ($pool as $item) {
            $sync->handle($item['kind'], $item['identity']);
        }
    }

    /**
     * The items the recent sittings are built from: a dozen words, phrases and
     * mistakes, four questions with a more natural version, and four grammar
     * notes that can make a blank, each named by the identity a review uses.
     *
     * @return list<array{kind: FeedbackType, identity: string}>
     */
    private function recentSittingPool(): array
    {
        $candidates = app(ReviewCandidates::class);
        $filters = ReviewFilters::custom();

        $items = [];

        foreach ($candidates->base($filters)
            ->select(['entries.type', 'entries.collection_key'])
            ->distinct()
            ->inRandomOrder()
            ->limit(self::RECENT_POOL)
            ->get() as $entry) {
            $items[] = [
                'kind' => FeedbackType::from((string) $entry->type),
                'identity' => (string) $entry->collection_key,
            ];
        }

        foreach ($candidates->questionsBase($filters)
            ->select('questions.id as collection_key')
            ->distinct()
            ->inRandomOrder()
            ->limit(4)
            ->get() as $question) {
            $items[] = [
                'kind' => FeedbackType::Question,
                'identity' => (string) $question->collection_key,
            ];
        }

        $notes = [];

        foreach ($candidates->grammarNotesBase($filters)
            ->select('entries.collection_key as collection_key')
            ->distinct()
            ->limit(30)
            ->get() as $note) {
            $notes[] = [
                'kind' => FeedbackType::GrammarNote,
                'identity' => (string) $note->collection_key,
            ];
        }

        // A note that cannot make a blank has no card to show, so only the
        // usable ones join the sittings.
        $blankable = $candidates->blankableKeys($notes);

        foreach ($notes as $note) {
            if (isset($blankable[$candidates->key($note['kind'], $note['identity'])])) {
                $items[] = $note;
            }
        }

        return array_slice($items, 0, self::RECENT_POOL);
    }

    /**
     * A sitting the learner started: its items, in the order they meet them,
     * kept from the moment they started it.
     *
     * @param  list<array{kind: FeedbackType, identity: string}>  $items
     */
    private function openSitting(array $items, bool $daily, CarbonImmutable $at): Review
    {
        /** @var Review $review */
        $review = Review::query()->create([
            'is_daily_review' => $daily,
            'is_custom_review' => ! $daily,
        ]);

        foreach ($items as $position => $item) {
            ReviewItem::query()->create([
                'review_id' => $review->id,
                'position' => $position + 1,
                'kind' => $item['kind'],
                'exercise' => ReviewExercises::kindOf($item['kind']),
                'item_identity' => $item['identity'],
            ]);
        }

        $review->created_at = $at;
        $review->updated_at = $at;
        $review->save();

        return $review;
    }

    /**
     * The ratings given as the learner went through a sitting, one per item in
     * the order they met them. A sitting stopped early has fewer ratings than
     * it has items, and the rest are left unrated. Each rating is kept from the
     * minute it was given.
     *
     * @param  list<ReviewRating>  $ratings
     * @return array<string, Rating> the saved ratings, keyed by kind and identity
     */
    private function rateSitting(Review $review, array $ratings, CarbonImmutable $startedAt): array
    {
        $record = app(RecordRating::class);
        $candidates = app(ReviewCandidates::class);
        $saved = [];

        foreach ($review->items()->orderBy('position')->limit(count($ratings))->get() as $position => $item) {
            $at = $startedAt->addSeconds($position * 40);

            /** @var Rating $rating */
            $rating = $record->handle($review, $item->kind, $item->item_identity, $ratings[$position]);

            DB::table('ratings')->where('id', $rating->id)->update([
                'created_at' => $at,
                'updated_at' => $at,
            ]);

            $saved[$candidates->key($item->kind, $item->item_identity)] = $rating;
        }

        return $saved;
    }

    /**
     * The rating the learner took back and then gave again, a minute later.
     * The second answer is the one that stays, and it still counts once.
     *
     * @param  array{kind: FeedbackType, identity: string}  $item
     */
    private function rateAgain(Review $review, array $item, ReviewRating $rating, CarbonImmutable $at): void
    {
        app(UndoRating::class)->handle($review, $item['kind'], $item['identity']);

        /** @var Rating $again */
        $again = app(RecordRating::class)->handle($review, $item['kind'], $item['identity'], $rating);

        DB::table('ratings')->where('id', $again->id)->update([
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    /**
     * The moment the learner reached the finish screen. A daily sitting also
     * keeps the day it was completed on, the way the finish screen gives it one.
     */
    private function closeSitting(Review $review, CarbonImmutable $at, bool $daily): void
    {
        $review->finished_at = $at->addMinutes(25);
        $review->completed_on = $daily ? $at : null;
        $review->updated_at = $review->finished_at;
        $review->save();
    }

    /**
     * The sitting the learner stopped part-way: what they had rated stays, the
     * rest is left unrated, and no day is claimed for it.
     */
    private function stopSittingEarly(Review $review, CarbonImmutable $at): void
    {
        app(FinishReviewEarly::class)->handle($review);

        $review->refresh();
        $review->finished_at = $at->addMinutes(9);
        $review->updated_at = $review->finished_at;
        $review->save();
    }

    /**
     * A star on each of these items, keeping any status the learner already
     * chose for them.
     *
     * @param  list<array{kind: FeedbackType, identity: string}>  $items
     */
    private function starItems(array $items): void
    {
        $saveMark = app(SaveItemMark::class);

        foreach ($items as $item) {
            /** @var CollectionItemMark|null $mark */
            $mark = CollectionItemMark::query()
                ->where('kind', $item['kind']->value)
                ->where('item_key', CollectionItemMark::keyFor($item['kind'], $item['identity']))
                ->first();

            $saveMark->handle($item['kind'], $item['identity'], true, $mark?->status);
        }
    }

    /**
     * Tops the My mistakes tab up to the target: the same pair of
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
     * Tops the vocabulary up to the target, so the Collection has
     * enough words to page through by hand.
     *
     * The pool mixes unique terms with the cases the page has to handle:
     * one word written several ways, four copies in the same box, rows with
     * no meaning, very long meanings and examples, letters from outside
     * English, and a blank row that must never be gathered. The target is
     * counted from the database, so a later run continues where the last one
     * stopped instead of adding the same set again.
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
     * Tops the phrases tab up to the target, so the second
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
     * Tops the grammar notes tab up to the target. Notes are never
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
     * Tops the say-it-again pool up to its target of finished questions, so
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

        $perSession = self::SHOWCASE_ROWS_PER_SESSION;
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

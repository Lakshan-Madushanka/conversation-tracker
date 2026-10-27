/**
 * Hard-coded sample content so the placeholder screens read like a real app.
 * Nothing here is persisted; it is only used for the layout shell.
 */

export interface SessionSummary {
    id: number;
    topic: string;
    date: string;
    questions: number;
    words: number;
    phrases: number;
    mistakes: number;
    preview: string;
}

export interface QuestionEntry {
    id: number;
    question: string;
    answer: string;
    mistakes: string;
    vocabulary: string;
    phrases: string;
    grammar: string;
    natural: string;
}

export interface SessionDetail extends SessionSummary {
    entries: QuestionEntry[];
}

export type ReviewType = 'flashcard' | 'fix' | 'say-again' | 'fill-blank';

export interface ReviewItem {
    id: number;
    type: ReviewType;
    prompt: string;
    answer: string;
    explanation?: string;
}

export const topics = [
    'Ordering at a restaurant',
    'Job interview practice',
    'Small talk about the weather',
    'Asking for directions',
    'Talking about hobbies',
];

export const sessions: SessionSummary[] = [
    {
        id: 1,
        topic: 'Ordering at a restaurant',
        date: '12 Oct',
        questions: 6,
        words: 12,
        phrases: 4,
        mistakes: 3,
        preview:
            'Practised ordering food, asking about dishes and reacting to a wrong order.',
    },
    {
        id: 2,
        topic: 'Job interview practice',
        date: '10 Oct',
        questions: 8,
        words: 15,
        phrases: 6,
        mistakes: 5,
        preview:
            'Walked through strengths, weaknesses and a short closing question.',
    },
    {
        id: 3,
        topic: 'Asking for directions',
        date: '7 Oct',
        questions: 5,
        words: 9,
        phrases: 3,
        mistakes: 2,
        preview:
            'Learned polite ways to ask for and confirm a route across town.',
    },
];

export const sessionDetail: SessionDetail = {
    ...sessions[0],
    entries: [
        {
            id: 1,
            question: 'What would you like to start with?',
            answer: 'I want a soup. And then a salad, if it is possible.',
            mistakes:
                'Use “I’d like …” instead of “I want …” when ordering. It sounds more polite.',
            vocabulary: 'relish — to enjoy something very much',
            phrases: 'if it is possible — a softer alternative to “if you can”',
            grammar: 'Softening requests with “I’d like” + noun.',
            natural:
                'I’d like to start with the soup, then a salad if that’s okay.',
        },
        {
            id: 2,
            question: 'The soup is not available. How would you react?',
            answer: 'Oh no. Then give me the pasta, please.',
            mistakes:
                '“Give me” is too direct. Try “Could I have …?” or “I’ll go with …” instead.',
            vocabulary: 'reluctant — unwilling, not eager',
            phrases: 'never mind — used to move on politely',
            grammar: 'Avoiding imperative sentences in requests.',
            natural:
                'That’s a shame. Never mind — I’ll go with the pasta, please.',
        },
    ],
};

export const reviewItems: ReviewItem[] = [
    {
        id: 1,
        type: 'flashcard',
        prompt: 'reluctant',
        answer: 'unwilling; not eager',
    },
    {
        id: 2,
        type: 'fix',
        prompt: 'I want a soup.',
        answer: 'I’d like the soup, please.',
        explanation: '“I’d like …” sounds more polite than “I want …”.',
    },
    {
        id: 3,
        type: 'say-again',
        prompt: 'How would you politely react to a wrong order?',
        answer: 'That’s a shame. Could I have the pasta instead, please?',
    },
    {
        id: 4,
        type: 'fill-blank',
        prompt: '_____ I have the menu, please?',
        answer: 'Could',
    },
];

export const progressStats = {
    streak: 4,
    practiceDays: 23,
    sessions: 24,
    words: 180,
    phrases: 65,
    grammar: 41,
    weeklySummary:
        'This week you practised on 4 days, learned 18 new words and fixed 6 recurring mistakes.',
    win: 'Your longest streak so far.',
};

export const mistakeTrend: { week: string; count: number }[] = [
    { week: 'W1', count: 14 },
    { week: 'W2', count: 11 },
    { week: 'W3', count: 8 },
    { week: 'W4', count: 5 },
];

export const todayReview = {
    minutes: 5,
    items: 8,
    mix: '3 flashcards · 2 mistake fixes · 1 say-it-again · 2 fill-in-the-blank',
};

export const recentReviews: { date: string; result: string; counts: string }[] =
    [
        {
            date: 'Yesterday',
            result: 'Well done',
            counts: '6 easy · 2 hard',
        },
        {
            date: '11 Oct',
            result: 'Good session',
            counts: '5 easy · 3 okay · 2 hard',
        },
        {
            date: '9 Oct',
            result: 'Keep going',
            counts: '4 easy · 6 hard',
        },
    ];

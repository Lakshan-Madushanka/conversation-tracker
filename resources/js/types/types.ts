import type { FeedbackType, TopicSummary } from '@/types/practice';

/** Re-exported so the collection views share the practice page's names. */
export type { FeedbackType, TopicSummary };

export interface PracticeFeedbackEntry {
    id: number;
    topic?: string;
    practice_question_id: number;
    type: FeedbackType;
    position: number;
    client_id: string | null;
    content_html: string;
    content_text: string;
    correction_html: string;
    correction_text: string;
    reason: string;
    example: string;
    example_html:
        | string
        | Array<{
              id: string;
              text: string;
              html: string;
          }>;
    is_split: boolean;
    created_at: string | null;
    updated_at: string | null;
    practice_question?: PracticeQuestion;
}

export interface PracticeQuestion {
    id: number;
    practice_session?: PracticeSession;
}

export interface PracticeSession {
    id: number;
    topic?: TopicSummary;
}

export interface LaravelPaginatorLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface LaravelPaginator<T> {
    current_page: number;
    data: T[];
    first_page_url: string;
    from: number | null;
    last_page: number;
    last_page_url: string;
    links: LaravelPaginatorLink[];
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
}

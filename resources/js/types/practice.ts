/** Shapes shared between the practice session page and the backend props. */

export interface TopicSummary {
    id: number;
    name: string;
}

export interface PracticeQuestionData {
    id: number;
    position: number;
    question_html: string;
    answer_html: string;
}

export interface PracticeSessionData {
    id: number;
    status: string;
    topic: TopicSummary;
    questions: PracticeQuestionData[];
}

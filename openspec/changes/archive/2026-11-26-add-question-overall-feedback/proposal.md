# Proposal

## Why

A practice session stores the tutor's question, the learner's answer, and five structured feedback boxes (mistakes, vocabulary, phrases, grammar notes, natural version). Real tutor replies also carry an overall remark about the whole answer — praise, a summary of what to work on, or a next step — that has no home in a table row or a box. Today the learner either drops it or forces it into one of the five boxes, mixing a general comment with structured entries.

## What Changes

- Add an optional, rich-text **overall feedback** field to each question in a practice session, stored as its own columns on `practice_questions` (`feedback_html`, `feedback_text`, `feedback_search_text`). It is deliberately separate from the five feedback entries: it never becomes an entry, a collection item, or a review card.
- Show the field on the session page: a formatted editor inside the question card (both collapsed and expanded layouts) with its own save state, and a read-only "Overall feedback" block on the reading page, placed after "My answer" and before the structured boxes.
- Accept the field on create, full save, and autosave, with the same length limit and rich-text cleaning as the question and answer.
- Count a question holding only overall feedback as a question with content, so it survives finishing a session and appears on the reading page.
- Make the field searchable: include it in the Library's session search and in the per-session match list as its own match kind, and let a search result open the session at that block.
- Payload and TypeScript shapes gain `feedback_html`, keeping the existing `feedback` object (the five boxes) untouched.

## Capabilities

### New Capabilities

- `practice-sessions`: recording a practice session — its questions, the learner's answer, the structured feedback boxes, and the question-level overall feedback field, including how they are saved, read back, searched, and counted.

### Modified Capabilities

None. No spec exists yet for practice sessions; this change introduces the capability with the overall-feedback behaviour and the surrounding question contract it depends on.

## Impact

- Database: one migration adding three columns to `practice_questions`.
- Backend: `PracticeQuestion` model (fillable, property docs, search-text hook, `hasContent()`), `SavePracticeQuestion`, `StorePracticeSessionRequest`, `SessionEditorResource`, `LibrarySessions` search, `SessionSearchMatches`, `BackfillSearchText`.
- Frontend: `types/practice.ts`, `lib/feedback.ts`, `lib/session-autosave.ts`, `lib/feedback.ts` helpers, `QuestionCard.vue`, `ReadingQuestion.vue`, `pages/sessions/Create.vue`, `pages/sessions/Show.vue`, `lib/search-target.ts`, `types/practice.ts` match kinds.
- Tests: session question saving, autosave, finish, show, library search, plus Vue tests for the editor card, reading page, autosave payload, and search target.
- No change to collections, reviews, custom reviews, notebooks, progress counts, or the five feedback boxes.

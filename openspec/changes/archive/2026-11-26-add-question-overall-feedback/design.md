# Design

## Context

See `proposal.md` for motivation and `specs/practice-sessions/spec.md` for the behaviour contract.

Current state that shapes the approach:

- `practice_questions` stores `question_html` + `question_text` (+ a `*_search_text` copy) and `answer_html` + `answer_text` (+ search copy). The same pattern exists for every rich field in the app: formatted markup, a plain-text copy for search/counting, and a normalised search column.
- The five feedback boxes live in `practice_feedback_entries`, keyed by `FeedbackType`. The word "feedback" in code already means those boxes, and `PracticeQuestion` exposes `feedbackEntries`.
- `SavePracticeQuestion` cleans question and answer through `RichText::cleanWithText()`, so the server never trusts client markup.
- `StorePracticeSessionRequest` normalises each question payload and applies one shared length limit (`MAX_CONTENT_LENGTH`) to rich fields.
- `SessionEditorResource` is the single payload for both the editor and the reading page; it sends question/answer markup and the grouped `feedback` object.
- Search has three layers: the Library listing query (`LibrarySessions::applySearch`), per-session matches (`SessionSearchMatches`), and the client's search target (`lib/search-target.ts` in `resources/js`). Question and answer matches use kind `question`/`answer`; typed boxes use kind `entry`.
- `PracticeQuestion::hasContent()` and its client mirrors (`questionHasContent`, `questionDataHasContent`) decide whether a question survives finishing.

## Goals / Non-Goals

**Goals:**

- Add overall feedback without touching the feedback-entry model, its types, or anything derived from entries (collections, marks, reviews, counts).
- Reuse the existing rich-text, validation, autosave, and search machinery rather than inventing a parallel path.
- Keep the editor's question card, the reading page, and the saved payload in step.

**Non-Goals:**

- Showing overall feedback in review exercises, custom reviews, collections, notebooks, or progress totals.
- Adding it as a new `FeedbackType` or a sixth feedback box.
- Changing the session `version`/autosave/conflict behaviour.

## Decisions

### Three columns on `practice_questions`, not one

Add `feedback_html` (text, default `''`), `feedback_text` (text, default `''`), and `feedback_search_text` (text, nullable), placed after the answer columns.

- Rationale: rich fields in this project always keep markup plus plain text plus a normalised search copy (`question_*`, `answer_*`). A single column would force HTML parsing at read time for search, previews and emptiness checks, and would break the "structured field" convention.
- Alternatives considered: (a) one `feedback` text column — rejected because the app never stores markup without its plain-text twin; (b) reuse the natural-version feedback entry — rejected by the requirement that overall feedback is not an entry; (c) a separate `practice_question_feedback` table — rejected as unnecessary for a 1:1 optional value.

Column names use `feedback_*` rather than `overall_feedback_*` so they line up with the existing `feedback` payload group while staying distinguishable from it. The UI label is "Overall feedback".

### The payload key is `feedback_html`, the boxes stay `feedback`

`PracticeQuestionData`/`PracticeQuestionForm` gain `feedback_html: string` at the question level; the existing `feedback: FeedbackValues` object is untouched. This keeps the five-box shape and the new field visibly different in both TypeScript and PHP, so no consumer can mistake one for the other.

- Alternative: nest the new field inside `feedback` — rejected because `FeedbackValues` is indexed by `FeedbackType` and every consumer iterates it; a stray key would leak into box loops and counts.

### Placement on the question card and reading page

Editor: a labelled `RichTextEditor` directly under "My answer", before the five boxes — it remarks on the whole answer, so it reads in that order. Reading page: the same order, as a block with the label "Overall feedback", rendered with `v-html` from the server-cleaned markup, hidden when blank, id `reading-{position}-feedback` so a search result can land on it.

- Alternative: below the boxes — rejected because the structured boxes are several screens long in a full session, and an overall remark would be hard to find.

### Reuse the existing save path end to end

- `StorePracticeSessionRequest::normalizeQuestion` defaults `feedback_html` to `''`; rules add `questions.*.feedback_html` with the shared max and a message.
- `SavePracticeQuestion` runs `RichText::cleanWithText()` and writes `feedback_html`/`feedback_text`; the model's `saving` hook fills `feedback_search_text`, exactly as it does for question and answer.
- `SessionEditorResource` adds `feedback_html` to each question payload (the editor and reading page need markup only).
- The client adds `feedback_html` to `emptyQuestion()`, `toQuestionForm()`, and `cleanQuestions()`; no other autosave logic changes.

This keeps one cleaning rule and one length rule; there is no second definition of "what overall feedback is allowed to contain".

### Overall feedback joins the Content filter as a content choice

The Library's Content filter is one list of checkboxes, so Overall feedback is added as `overall_feedback` beside the five `FeedbackType` values. It is not added to `FeedbackType` itself, because that enum describes feedback entries and drives validation, collections and reviews.

- `LibraryFilters` keeps one ordered list but exposes `boxTypes()` (only real entry types) and `includesOverallFeedback()`.
- Filtering: a session matches when it has a non-empty entry of a chosen box type OR a question with non-empty overall feedback. The existing single `whereHas` becomes an `orWhereHas` group, so "any of the chosen options" keeps its meaning.
- Search: with no content filter, entries, question, answer, overall feedback and topic are searched. With content choices, only the chosen ones are searched — chosen boxes through their entries, overall feedback through `feedback_search_text`. Choosing only Overall feedback therefore searches no entries, which matches the filter's own meaning.
- Client: `LibraryContentType = FeedbackType | 'overall_feedback'`; the option is appended to `contentTypes`, and chips, counts and the URL reuse the existing list unchanged.
- Alternative considered: a separate boolean filter beside Content — rejected because the learner asked for one Content filter and a second control would split an obvious choice across two places.

### Content and search behaviour

- `PracticeQuestion::hasContent()` returns true when `feedback_text` is non-empty, and the two client helpers check `feedback_html` with `isRichTextBlank`; the reading page's "worth showing" filter does the same. This makes "only overall feedback" a real question and keeps the finish action's pruning correct.
- Library search: overall feedback is matched through `feedback_search_text` when no content filter is active or when `overall_feedback` is chosen; entries are matched only for the chosen box types, so a content filter never widens beyond what the learner picked.
- Per-session matches: emit kind `feedback` with `question_position` and `segments` from the plain text, positioned after the answer match. Add `'feedback'` to the client's `SearchMatchKind` union, to `search-target.ts` `FIELDS`, and to `Show.vue`'s `targetPlace()`.
- `BackfillSearchText` fills the new search column for existing rows (all empty today, so it is a no-op in practice but keeps the command authoritative).
- Session counts stay driven by the five boxes and question/answer presence, so no `SessionCounts` or progress number changes.

### Schema change is additive

The migration only adds nullable/defaulted columns; no data migration is required. `down()` drops them.

## Risks / Trade-offs

- [Search target kind is a client-side contract; a new kind can silently stop revealing] → Add `feedback` to the `FIELDS` set, `targetPlace()`, and cover it with a `search-target`/`Show` test.
- [Payload bloat for long sessions] → Same 100k-per-field cap as question and answer; overall feedback is a short remark in practice, and the payload already carries two rich fields per question.
- [Two "feedback" concepts confuse future readers] → Keep the distinction explicit in PHPDoc, property names (`feedback_html` vs `feedbackEntries`), and the UI label ("Overall feedback" vs named boxes); note it in the model docblock.
- [Existing fixtures/factories create questions without the new columns] → Columns default to `''`, so every existing factory and test keeps working without edits; only tests that assert the payload shape need the new key.

## Migration Plan

1. Ship the additive migration; existing rows get empty overall feedback.
2. Deploy code that reads/writes the new columns; old clients that omit `feedback_html` are normalised to `''`, so nothing fails mid-deploy.
3. Rollback: revert code first, then run the migration's `down()`; no data loss beyond the overall feedback values.

## Open Questions

None.

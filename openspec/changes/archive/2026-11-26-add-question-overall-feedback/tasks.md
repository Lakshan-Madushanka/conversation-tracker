# Tasks

## 1. Schema and model

- [x] 1.1 Add a migration that adds `feedback_html` (text, default `''`), `feedback_text` (text, default `''`) and `feedback_search_text` (text, nullable) to `practice_questions`, with a `down()` that drops them; verify with `php artisan migrate` on a fresh SQLite database and by checking the column list.
- [x] 1.2 Update `PracticeQuestion` (fillable, property docblock, search-text hook, and `hasContent()` treating non-empty `feedback_text` as content); verify a feature test asserts the saving hook normalises `feedback_search_text` and that `hasContent()` is true for overall-feedback-only questions and false for fully blank ones.

## 2. Saving overall feedback

- [x] 2.1 Add `questions.*.feedback_html` to `StorePracticeSessionRequest` normalisation and rules (nullable string, `MAX_CONTENT_LENGTH`) with its own message; verify a feature test asserts a blank value is accepted and an over-limit value returns a validation error for `questions.0.feedback_html` without writing a question.
- [x] 2.2 Clean and persist the field in `SavePracticeQuestion` via `RichText::cleanWithText()`, writing `feedback_html`/`feedback_text`; verify tests in `tests/Feature/PracticeSessionQuestionsTest.php` cover formatted text kept, unsupported markup stripped, formatting-only stored as empty, and feedback entries left unchanged.
- [x] 2.3 Confirm autosave carries the field end to end; verify `tests/Feature/PracticeSessionAutoSaveTest.php` asserts an autosave with `feedback_html` updates the question and a subsequent read returns the same value.
- [x] 2.4 Confirm finishing keeps an overall-feedback-only question and still prunes a fully blank one; verify `tests/Feature/PracticeSessionFinishTest.php` covers both cases.

## 3. Payload for the editor and reading page

- [x] 3.1 Add `feedback_html` to each question in `SessionEditorResource` and update its array-shape docblock; verify `tests/Feature/SessionShowTest.php` (and the edit payload test) assert `session.questions.0.feedback_html` for a question with and without overall feedback.

## 4. Editor UI

- [x] 4.1 Add `feedback_html` to `PracticeQuestionData`, `PracticeQuestionForm`, `emptyQuestion()`, `toQuestionForm()` and `cleanQuestions()` in `resources/js`; verify `npm run types:check` and update any fixture helpers that build a form question so the suite compiles.
- [x] 4.2 Include the field in the client content checks (`questionHasContent`, `questionDataHasContent`) using `isRichTextBlank`; verify `resources/js/lib/__tests__/feedback.test.ts` style unit coverage (add if missing) for overall-feedback-only and blank cases.
- [x] 4.3 Add a labelled "Overall feedback" `RichTextEditor` to `QuestionCard.vue` under "My answer", with `feedbackHtml` prop, `update:feedbackHtml` event and an error message slot; verify a Vue component test asserts the editor renders, emits updates, and shows the error text.
- [x] 4.4 Wire both question-card layouts in `pages/sessions/Create.vue` (`v-model:feedback-html` plus `questions.{index}.feedback_html` error) and include it in the collapsed-card preview/content checks; verify `resources/js/pages/__tests__/Create.test.ts` covers typing overall feedback and sending it on save.

## 5. Reading page

- [x] 5.1 Render the overall feedback block in `ReadingQuestion.vue` after "My answer" and before the sections, hidden when blank, with id `reading-{position}-feedback`; verify `resources/js/pages/__tests__/Show.test.ts` asserts it renders formatted text and is absent when empty.
- [x] 5.2 Include `feedback_html` in `Show.vue`'s "worth showing" filter so an overall-feedback-only question is read back; verify the same test file covers a question with only overall feedback.

## 6. Library overall-feedback filter

- [x] 6.1 Accept `overall_feedback` in `LibraryFilters`' Content list beside the box types (helpers for box types and overall feedback), and make `LibrarySessions` match a session with overall feedback or with a chosen box entry; verify `tests/Feature/LibraryFiltersTest.php` covers overall-feedback-only sessions, the any-of combination with a box type, and that unknown values are still dropped.
- [x] 6.2 Narrow the Library search to the chosen content: chosen box types search their entries, `overall_feedback` searches `feedback_search_text`, and with no content choice everything is searched; verify `tests/Feature/LibrarySearchTest.php` covers found-by-overall-feedback, not-found when only Mistakes is chosen, and found when Overall feedback is chosen.
- [x] 6.3 Add the Overall feedback option to the client `contentTypes` list, widen the filter type to `LibraryContentType`, and keep chips, counts and the URL round-tripping; verify `resources/js/lib/__tests__/library-filters.test.ts` covers normalising, serialising and removing the choice, and `LibraryFilterControls` renders it.

## 7. Search matches, backfill and reveal

- [x] 7.1 Report an overall-feedback match in `SessionSearchMatches` as kind `feedback` with position and segments after the answer match, respecting the chosen content types; verify `tests/Feature/LibrarySearchTest.php` asserts the match kind, question position and snippet.
- [x] 7.2 Fill the new search column in `BackfillSearchText`; verify a test asserts a stale/missing copy is rewritten and a second run changes nothing.
- [x] 7.3 Add `feedback` to the client `SearchMatchKind`, `search-target.ts` `FIELDS` and `Show.vue` `targetPlace()`; verify `resources/js/lib/__tests__/search-target.test.ts` reads the field and the `Show` test reveals the block.

## 8. Verification

- [x] 8.1 Run `composer rector`, then `vendor/bin/pint --dirty --format agent`, then `composer types:check`; verify all pass with no new errors.
- [x] 8.2 Run `npm run check` and `npm run types:check`; verify both pass.
- [x] 8.3 Run the narrow PHP feature tests for sessions and search, then `php artisan test --compact`; verify the full suite passes.
- [x] 8.4 Manually confirm in the running app that overall feedback can be typed, autosaved, read back, filtered, found by search, and opened from its search result; record the result.

## Workflow follow-up

- Archive the change with `openspec archive add-question-overall-feedback` after review and a green suite.
- Verify the archived main spec at `openspec/specs/practice-sessions/spec.md` keeps the Purpose section.

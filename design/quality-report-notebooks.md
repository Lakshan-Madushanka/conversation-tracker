# Notebooks — Quality Report

| Item             | Detail                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| ---------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Feature          | F14 Notebooks: creation, listing and search, the autosaving three-box board, scoped identities, Collections sources and marks, Create review and practise, daily/Quick participation, and hard deletion with an impact preview                                                                                                                                                                                                                                                                                                                                                           |
| Date             | 16 November 2026                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| Environment      | Windows, local Laravel app; PHP 8.5.6, Laravel 13.36.0, Inertia 3.5.1 (`@inertiajs/vue3` 3.0), Vue 3.5.13, Tailwind 4.1.1, shadcn-vue, SQLite                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| Sources of truth | `design/spec.md`, `design/features.md` (F14), `design/feature-Notebooks.md`; the shared sections of `feature-F3-Personal collections.md`, `feature-F4-Review and re-practice.md`, `feature-Custom reviews.md`, and `feature-F5-Progress and motivation.md`                                                                                                                                                                                                                                                                                                                               |
| Dataset          | Seeded local database: **1 notebook with 1 saved word**, 52 sessions, 17 topics. The browser checks used the same database. It is a development dataset, not a scale fixture; the query-flatness tests create their own rows.                                                                                                                                                                                                                                                                                                                                                            |
| Result           | Steps 1–6 are implemented and covered by 102 notebook backend tests and 32 notebook component/page tests. The first pass found and fixed two listing defects (a topic read per card, and stale cards after a search). A follow-up pass fixed all five remaining code issues: the listing's topic filter, the matched-entry preview and deep link, paginated review candidates, focus after deleting a card, and the deletion preview's full-table read. Progress reporting remains unimplemented by design while its F5 measures are open, and export shape is still a product decision. |
| Repository       | Branch `notebook/step6-hard-deletion-polish`; Steps 5–6 in `bf7b513`, `d0abc8d`, `909444c`, PR #47. The quality-pass fixes described here are committed after `96c7eb3` in `3a12db7` (listing, preview, candidates, focus), `90eff6a` (deletion preview read) and `b68c11d` (this report). `design/feature-Notebooks.md` records the resolved General default (open question 4) and, in requirement 21, the duplicate guidance; `design/feature-F5-Progress and motivation.md` was not modified in this pass.                                                                            |

**Verdict in one paragraph:** Notebooks behaves as the F14 document describes: a real notebook parent with shared topics, one autosaving board built from the session editors, identities scoped to the notebook so that a session's “bank” and a notebook's “bank” stay separate, Collections rows and marks that follow the scoped item, a Create review and practise shortcut with the 20/50 limits and selection step, daily/Quick practice for a notebook-only learner, and a stale-safe hard deletion that never touches its topic, other notebooks, sessions, or attempt snapshots. The listing now combines search with a topic filter, shows where a search matched inside an entry with safe highlighting, and opens that entry on the board; review candidates arrive fifty at a time; deleting a card keeps focus useful; and the deletion preview streams one notebook's rows in the columns it needs. What remains is the deliberately unimplemented Progress review-activity area, export coverage, server-side grouping still reading a whole notebook before candidate pages are cut, and the evidence gaps (no screenshots, screen reader, real device, or scale fixture).

---

## 1. Summary

### 1.1 What was in scope

- Notebook storage and identity: two nullable parents with an exactly-one-parent guard, notebook-scoped vocabulary/phrase identities, individual grammar notes, and shared topics with reference-protected deletion.
- The notebook listing (`/notebooks`): cards with name, topic, counts, date and plain-text description; search across metadata, topic and own entries; cursor pagination; empty-create-search states.
- The notebook board (`/notebooks/{notebook}`): metadata editing, the three boxes, autosave, retries, duplicate and blank-row handling, two-tab conflict behaviour, and save-state feedback.
- Collections integration: Source All/Sessions/Notebooks, notebook picker, scoped marks and notes, notebook panels with **Open notebook**, mixed-source sorting and pagination.
- Create review and practise: candidate reads, the 20/50 limits, selection above 50, allowed kinds, fixed membership, live content, and attempt snapshots.
- Daily/Quick participation: notebook-only learners, per-scope schedules, source naming, live-source deletion during practice, and the Progress boundary that a notebook is never a session.
- Hard deletion: read-only preview of saved rows, study items and affected reviews, acknowledgement, stale-token refusal, transactional deletion, reference pruning, and “cannot recreate after deletion”.
- Consistency, accessibility, mobile/dark mode, speed, reliability and data safety for those screens and endpoints.

### 1.2 What was out of scope

- The Progress page itself. F5 Step 1–4 and the review-activity decision are separate work; this pass only verified that notebooks do not leak into session-derived measures.
- Export and backup (F7). The Export screen exists as a placeholder and is not part of notebook delivery.
- Import, spreadsheet-to-notebook, multi-user accounts and authorisation: the app is for one learner and has no `user_id`.
- New features or redesign. This pass fixes correctness, consistency, speed and safety only.

### 1.3 What was verified, and how

The steps are exercised through the routes the learner uses, with component tests for the UI contracts:

| Step / behaviour                                                                              | Covered by                                                                             |
| --------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------- |
| Parent rules, scoped identity, comma-separated meanings, theme and note identities            | `NotebookStorageTest`                                                                  |
| Creation validation, General default, rich description, retry and cancel                      | `NotebookCreationTest`                                                                 |
| Board save/reload with formatting, blanks, duplicates, retries, two-tab conflict              | `NotebookBoardTest`                                                                    |
| Listing fields, search by metadata/entry/note, scope isolation, flat query count              | `NotebookListingTest`, `NotebooksIndex.test.ts`                                        |
| Collections sources, filters, scoped marks, panels, typo-following, mixed sorting             | `NotebookCollectionsTest`; shared Collections UI covered by `CollectionsIndex.test.ts` |
| Review candidates, 20/50 rules, selection, retries, fixed membership, snapshots, isolation    | `NotebookReviewTest`, `NotebookReviewDialog.test.ts`                                   |
| Daily/Quick for notebook-only learners, per-scope ratings, deleted sources, no session totals | `NotebookDailyReviewTest`                                                              |
| Deletion preview, stale confirmation, topic/other-source survival, reference pruning          | `NotebookDeletionTest`, `NotebookDeleteDialog.test.ts`                                 |
| Listing search refresh and stale-page race                                                    | `NotebooksIndex.test.ts` (added in the first pass)                                     |
| Listing topic filter, alone and combined with search                                          | `NotebookListingTest`, `NotebooksIndex.test.ts`                                        |
| Matched-entry preview, safe highlighting and deep link to the row                             | `NotebookListingTest`, `NotebooksIndex.test.ts`                                        |
| Candidate pagination, kept selections and creation of an item from a later page               | `NotebookReviewTest`, `NotebookReviewDialog.test.ts`                                   |
| Focus after deleting a listing card                                                           | `NotebooksIndex.test.ts`                                                               |
| Streamed deletion preview and blank-row exclusion                                             | `NotebookDeletionTest`                                                                 |
| Shared topic rename/delete protection                                                         | `TopicManagementTest` plus `NotebookCreationTest`, `NotebookCollectionsTest`           |

**Tested** means executed by an automated test or a measured request. **Reviewed** means read and reasoned about. **Not verified** means there is no evidence; the gaps are listed in section 3. In the first pass the in-app browser loaded the listing and drove a real search; the tab is hidden, so screenshots are unavailable and the check used the page address and DOM text. The follow-up fixes were verified by tests and measured requests only; no new browser pass was possible.

### 1.4 Method and confidence

| Area                    | Method                                                                | Confidence                                             |
| ----------------------- | --------------------------------------------------------------------- | ------------------------------------------------------ |
| Behaviour               | Pest feature tests through HTTP routes; Vitest component/page tests   | High for the covered journeys                          |
| Accessibility structure | Component tests, DOM assertions, source review                        | Medium-high; no real assistive technology              |
| Keyboard and focus      | Focus assertions in dialogs and after card deletion; source review    | Medium; no live Tab traversal or screen reader         |
| Responsive layout       | Tailwind breakpoints and shared card/dialog patterns, source review   | Medium; no device or screenshot pass                   |
| Light/dark              | Shared tokens and components, source review                           | Medium-high; no computed-colour check                  |
| Speed                   | Query-count assertions and request measurement through the booted app | High for query growth; local timings on a tiny dataset |
| Data safety             | Stale-token tests, read-only preview test, rollback-style assertions  | High for this local database                           |

---

## 2. Fixes made (by area)

### 2.1 The notebook listing reads its topic once per card (first pass)

- **Problem:** `NotebooksListing::paginate()` counted the three boxes in the database but did not eager-load the topic, and `NotebookCardResource` then called `loadMissing('topic')` for every card. A 25-row page ran **28 queries** where a 5-row page ran **8**.
- **Change:** the listing query now selects `topic` with the page (`->with('topic')` before the counts). The resource keeps its `loadMissing` fallback for any other caller.
- **Result:** the small and large pages now run the same number of queries. `NotebookListingTest` holds it with a page-size comparison rather than a brittle fixed count.

### 2.2 A search changed the address but not the list (first pass)

- **Problem:** Inertia visits use `preserveState: true`, so the page component stays alive. Its cards, total, cursor and has-more refs were copied from the first props once and never followed later visits. Searching `zzzz` moved the address, but the page kept showing the first visit's card and “1 notebook”; the empty state never appeared. A search that found a different notebook would likewise have shown the old cards under the new address.
- **Change:** `resources/js/pages/notebooks/Index.vue` now watches the page props: a new `notebooks` list replaces the cards, and `total`, `next_cursor`, `has_more` and `search` follow their props. The search box stays in step with the address for Back/Forward and restored visits.
- **Result:** a search replaces the cards and the count together; clearing it restores the list. Verified in the browser (`?q=zzzz` → empty state, clearing → the card returns) and pinned by five page tests.

### 2.3 A next page could arrive after a new search (first pass)

- **Problem:** “Load more” is a plain JSON request. If it was already in flight when a search brought a new first page, its older page was appended to the new list, mixing results from two searches.
- **Change:** the page keeps a list generation counter, raised whenever new notebook props arrive. A load-more response from an older generation is discarded.
- **Result:** the stale page never lands in the list; a page test resolves the in-flight request after the search and asserts only the new card remains.

### 2.4 Test coverage added or strengthened

| File                                                  | Added or strengthened                                                                                 |
| ----------------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| `tests/Feature/NotebookListingTest.php`               | “reads a page in the same number of queries whatever it holds” (5-row vs 25-row page)                 |
| `resources/js/pages/__tests__/NotebooksIndex.test.ts` | Five tests: prop-driven cards, empty search state, search box sync, count/cursor adoption, stale page |

No test was deleted or weakened. The new backend test was confirmed red before the eager load (**28 vs 8 queries**) and green after; the page tests were confirmed red before the watchers and the generation guard.

### 2.5 The listing had no topic filter (follow-up)

- **Problem:** requirement 3 asks the listing to search and filter by topic, and requirement 56 asks the two to combine. The page's topic picker belonged to the create dialog only, so a learner could not narrow a long list to one topic.
- **Change:** `NotebooksListingRequest` accepts an optional `topic` (checked with `is_numeric`, because validated values are strings), `NotebooksListing` narrows the page and the total by it, and `topics()` offers only topics at least one notebook uses. The controller drops an unknown or removed id and sends the applied filter back, so the address and the select stay in step. The page renders a native select beside the search box and includes the topic in both the visit and the load-more URL; an empty topic offers “Show all topics”.
- **Result:** search and topic combine (`?q=garden&topic=5`) and the load-more page keeps both. `NotebookListingTest` covers narrowing, the combination, the used-topics list and an unknown id; three page tests cover the options, the combined visit and the empty-topic reset.

### 2.6 A search hit inside an entry had no preview or way to open it (follow-up)

- **Problem:** search could match through an entry while the card showed only name, topic and description. Requirements 54 and 55 ask for safe highlighting, a meaningful matching-entry preview, and a way to open that entry.
- **Change:** a page-level `NotebookSearchMatches` query finds the first matching row per notebook in board order (words, phrases, notes, then position). `CollectionMatch::forEntry` reuses the existing field labels and `SearchText` segments, and `NotebookCardResource` returns `{label, segments, type, entry, position}` only when the card's own visible metadata does not already explain the hit. The page renders the existing `CollectionMatchHint` and deep-links the card with the same `field=entry` address Collections uses; the board reveals, focuses and highlights that row, and says calmly when it has moved.
- **Result:** the snippet is plain text (formatting cannot inject markup), metadata matches show no preview, and the first row in board order is the one previewed. Covered by listing feature tests and two page tests (preview text and deep link).

### 2.7 Review candidates arrived in one payload (follow-up)

- **Problem:** the selection screen read every saved row of the notebook and sent every candidate in one response, so a very large notebook produced a large payload and PHP grouping work (technical note 10).
- **Change:** `NotebookReviewCandidates::page()` keeps the server-side identity grouping and revalidation but returns 50 candidates per page with `count`, `eligible_count`, `next_offset` and `has_more`. The dialog asks for the next page from “Show more items”, appends by identity and keeps what was already chosen. Creation still revalidates the chosen identities and the 20 recommended / 50 maximum rules are unchanged.
- **Result:** the dialog test drives a 50 + 1 notebook, chooses an item, loads the next page and creates a review from both pages; backend tests read both pages and create from a later-page identity. **Limit to record:** the server still groups the whole notebook before slicing a page, so the response and screen are bounded but the PHP work is not (section 7, question 4).

### 2.8 Deleting a card dropped focus to the document body (follow-up)

- **Problem:** the focused Delete button left with its card, so focus fell to the body. The warning itself focuses Cancel, but it cannot return to a trigger that no longer exists.
- **Change:** each card link carries `notebook-link-{id}`; after removal focus goes to the card that took its place, the card above, or the search box when the list is empty — the same pattern the Library already uses.
- **Result:** three page tests cover the next card, the last card and the empty list.

### 2.9 The deletion preview read every entry twice (follow-up)

- **Problem:** the preview hydrated every non-blank entry of the notebook, and the delete action then loaded every entry again to recompute the same identities.
- **Change:** the preview now streams one notebook's rows with `cursor()` in the nine columns the blank rule and identity need, counting rows and collecting identities in one pass through `CustomReviewDeletedIdentities::remember()` / `groupedFrom()`. The delete action reuses the identities already computed for the checked warning instead of reading the rows a second time. The read path still writes nothing.
- **Result:** behaviour is unchanged (duplicate rows still collapse, blank rows still excluded); a new test covers a blank word and a blank note, and the existing deletion suites still pass.

### 2.10 Test coverage added in the follow-up pass

| File                                                             | Added or strengthened                                                                                                                                            |
| ---------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `tests/Feature/NotebookListingTest.php`                          | Topic narrowing and combination with search, used-topics list, unknown topic, entry preview shape, metadata-hit suppression, safe plain text, first matching row |
| `tests/Feature/NotebookReviewTest.php`                           | Two-page candidate reads and creating a review from a later-page identity                                                                                        |
| `tests/Feature/NotebookDeletionTest.php`                         | Blank word and blank note excluded from counts and study items                                                                                                   |
| `resources/js/pages/__tests__/NotebooksIndex.test.ts`            | Topic options/visit, empty-topic reset, load-more with topic, match preview and deep link, card link without a match, three focus-after-delete cases             |
| `resources/js/components/__tests__/NotebookReviewDialog.test.ts` | Paged candidates with a kept selection                                                                                                                           |

---

## 3. Issues not fixed

| #   | Severity | Issue                                                                                                                                                                                                                                                                                                                         | Recommendation                                                                                                                                       |
| --- | -------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| 3.3 | Medium   | Progress still shows sample data; no review-activity area exists. Requirement 80 is explicitly blocked until the F5 measures are decided, but requirements 79 and 81 are only partly verified (no session totals or streak are invented).                                                                                     | Record the review-activity measures in F5 first, then build the read model. Do not copy daily ratings into session totals or custom-attempt results. |
| 3.4 | Medium   | Export does not represent notebooks at all — not even as an unchecked option — while the feature list promises all-data backup. This is open question 5 in the Notebooks document.                                                                                                                                            | Decide the export shape for notebook metadata, entries, scopes and marks before F7 ships; add notebook rows to the existing export options.          |
| 3.7 | Low      | No screenshots, real-device pass, Firefox/Safari check, or screen-reader pass was possible; the browser tab is hidden and screenshots fail. Mobile stacking, dark mode and announcements are covered by classes, tokens, tests and source review only. The production build now compiles, but nothing was visually inspected. | Run one manual pass (Chrome at 375 px, one phone, NVDA/VoiceOver) before calling accessibility verified.                                             |
| 3.8 | Low      | The dataset is tiny (one notebook, one word), so request sizes and timings in section 6 describe overhead, not scale. The candidate response is now paged at 50, but candidate grouping, listing payload growth and preview time on a large notebook are still unmeasured.                                                    | Seed a scale fixture (hundreds of notebooks and entries) in a scratch database and measure listing, board, preview and candidate payloads.           |

The five issues fixed in the follow-up pass (3.1 topic filter, 3.2 matched-entry preview and link, 3.5 candidate pagination, 3.6 focus after deletion, 3.9 preview read) are described in section 2.

---

## 4. Differences from the documents (including decisions to verify)

| #   | Decision taken                                                                                                                                                                                                                                     | What the documents say / imply                                                                                                                                                             | Why                                                                                                                                                                                                                            | Action for the product owner                                                                                        |
| --- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------- |
| 4.1 | A renamed **General** topic is not reused; a later notebook created without a topic gets a new General.                                                                                                                                            | Notebooks open question 4 asked how the default is resolved if the shared General is renamed or deleted while unused; it is now resolved in the Notebooks document in favour of this rule. | Resolving General by name keeps creation predictable and writes only on create; the renamed topic keeps the notebooks and sessions already using it.                                                                           | Confirmed as intended on 16 November 2026.                                                                          |
| 4.2 | Notebook practice appears in the Review page's recent-review list and never as a session total.                                                                                                                                                    | F5 requirements 7–9, 34–35 and Notebooks 79–81.                                                                                                                                            | Matches the documents; notebooks feed daily/Quick and leave session measures alone.                                                                                                                                            | None.                                                                                                               |
| 4.3 | The listing card keeps counting saved rows per kind, duplicates included, not distinct study items; a repeated word or phrase is tagged **Duplicate** on the board with the fix: extra meanings go in the same meaning field, separated by commas. | Requirement 2 asks for item counts; requirement 21 says repeated rows are one study identity.                                                                                              | Requirement 19 makes a comma the way to add a meaning, so a repeated subject row is a mistake to fix rather than an intended second meaning; the card shows the total the learner typed and the board explains any repeat.     | Confirmed on 16 November 2026. The card stays a row count; the tag and guidance live in the shared table component. |
| 4.4 | Editing a notebook updates its last-updated time; marking or practising does not.                                                                                                                                                                  | Requirement 31.                                                                                                                                                                            | Matches; `last_changed_at` changes only on metadata/entry saves.                                                                                                                                                               | None.                                                                                                               |
| 4.5 | Notebook deletion is hard deletion; the marks of its items (star, status and notes), their review schedules and their suggestion declines are deleted with them, so nothing is left under the old scope and nothing is inherited.                  | Requirements 82, 87 and 41.                                                                                                                                                                | A notebook's identity never returns, so keeping these rows would leave records nothing can reach; a later notebook with the same name and words has new identities and inherits nothing. Rating history stays with its review. | Confirmed on 16 November 2026; requirements 41 and 87 revised accordingly.                                          |

---

## 5. Test results

| Check                    | Command                                                                                                                     | Result                                                                                   |
| ------------------------ | --------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------- |
| Rector                   | `composer rector`                                                                                                           | Passed, 0 errors                                                                         |
| Pint                     | `vendor/bin/pint --dirty --format agent`                                                                                    | Passed                                                                                   |
| PHPStan                  | `composer types:check`                                                                                                      | Passed, 0 errors                                                                         |
| PHP test suite           | `php artisan test --compact`                                                                                                | **964 passed, 8,902 assertions**, 50.0 s                                                 |
| Notebook tests           | `php artisan test --compact tests/Feature/Notebook*Test.php`                                                                | **102 passed, 1,290 assertions**, 5.8 s                                                  |
| Frontend format and lint | `npm run check`                                                                                                             | Passed, 267 files formatted, 246 linted, no warnings                                     |
| TypeScript               | `npm run types:check`                                                                                                       | Passed                                                                                   |
| JavaScript test suite    | `npx vp test --run`                                                                                                         | **1,016 passed, 82 files**, 86.2 s                                                       |
| Notebook frontend tests  | `npx vp test resources/js/components/__tests__/Notebook*.test.ts resources/js/pages/__tests__/NotebooksIndex.test.ts --run` | **32 passed, 3 files**, 4.7 s                                                            |
| Production build         | `npm run build`                                                                                                             | Passed in 10.8 s; app 235.95 kB (81.93 kB gzip), editor chunk 386.78 kB (122.26 kB gzip) |

Failures seen and resolved while writing the fixes:

- `NotebookListingTest > reads a page in the same number of queries whatever it holds` failed first at **68 vs 8** (the test initially created notebooks while logging, which was corrected), then at **28 vs 8** for a real N+1, and passed after eager loading.
- `NotebooksIndex.test.ts` failed all four original tests before the prop watchers, and the stale-page test failed before the generation guard.
- The topic filter initially ignored every topic: `validated('topic')` returns a numeric string, so an `is_int` check rejected it. Changed to `is_numeric` with a cast.
- The load-more page test read an earlier request's URL because the module-level request mock kept its call history; an `afterEach` reset fixed the test, not the page.
- The entry-preview assertion expected one snippet segment, but `SearchText::snippet` emits one segment per matched term; the test now compares the concatenated text and matched parts.
- Two new review tests called `route(..., $notebook, ['offset' => 50])`; Laravel's third argument is `$absolute`, so the offset never travelled and the helper looped until the run was stopped. All calls now pass a named array.
- Three focus tests failed before the fix (focus fell to the body), then passed once the card links and fallbacks were in place.
- PHPStan rejected three types introduced by the fixes: an `array_map` producing `array<int, int>` instead of a list, a `Collection::all()` typed as `array<int, …>` instead of a list, and the question identity becoming an int array key. Each was fixed at its source, not suppressed.
- `vue-tsc` rejected the topic select's listener: the shared `NativeSelect` declared `'update:modelValue': AcceptableValue` (a bare payload type), so the listener was typed `() => any`. The declaration now uses the tuple form `[value: AcceptableValue]`, and `npm run types:check` passes.

Regression coverage that matters for this step:

- One word in two notebooks and a session is three identities; duplicate rows do not inflate membership; grammar notes never merge.
- A stale deletion confirmation is refused with fresh numbers; a late save cannot recreate the notebook; a review losing its last member becomes Empty while snapshots remain.
- A notebook-only learner receives a daily suggestion; rating a notebook word leaves a same-spelling session word's schedule alone; a saved custom attempt never appears as review activity.
- The listing returns each notebook once and does not match through another notebook or a session.

---

## 6. Measurements

### 6.1 Request measurements through the booted app

Measured with `app()->handle()` in `php artisan tinker` on the development database, including routing, middleware, controller, resources and rendering:

| Route                              | Status | Queries | Body size |
| ---------------------------------- | ------ | ------- | --------- |
| `/notebooks` (JSON, 1 notebook)    | 200    | 8       | 960 B     |
| `/notebooks/1` (HTML board)        | 200    | 6       | 3,046 B   |
| Deletion preview for that notebook | 200    | 8       | 331 B     |

The dataset has one notebook and one saved word, so these numbers describe fixed overhead, not scale. Payload and response-time growth were not measured on a large notebook (3.8). The counts include session middleware; the listing's extra query over the first pass is the topic list, and the preview's count varies with whether any saved review holds the notebook's items.

### 6.2 Query growth

- `NotebookListingTest` compares a 5-row page with a 25-row page and asserts the same query count. Before the eager load the large page ran **28** queries; after it, both sides match.
- The listing does not read entries to count them: the three counts are correlated subqueries in the page query, plus one query for the page's topics and one for the total.
- The entry preview adds one query per page, not one per card: `NotebookSearchMatches` reads the matches for the whole page in a single query.
- The board and the deletion preview read one notebook's rows directly; their query counts do not depend on the number of notebooks. The preview streams those rows and reads only nine columns.

### 6.3 What the numbers mean

- The listing is a bounded read: fixed overhead regardless of page size, with the per-card topic read gone and a single match query per page.
- The candidate response is bounded at 50 candidates per request. The server still groups the whole notebook before slicing a page, so PHP work grows with notebook size even though the payload and screen do not (3.8).
- Local dev timings were not quoted because the dataset is too small to make them meaningful. The production build compiles in 10.8 s; no benchmark on large data was run.

---

## 7. Open questions

1. What are the F5 review-activity measures, and when do they start (3.3, Notebooks open question 2)?
2. What is the export shape for notebooks, and does it ship with F7 (3.4, Notebooks open question 5)?
3. How large may a notebook grow before candidate grouping itself must move into SQL rather than PHP (3.8)?
4. Is a 50-candidate page the right size for the selection screen, or should it follow the 20/50 review rules more directly?

---

## 8. Suggested next steps

1. **Record the F5 measures**, then build the separate review-activity area; do not touch session-derived numbers (3.3).
2. **Decide notebook export** before F7, so the all-data promise stays honest (3.4).
3. **Seed a scale fixture** and measure listing, board, preview and candidate requests; if candidate grouping is slow, group identities in SQL (3.8).
4. **One manual accessibility and device pass** (3.7).
5. **Review the committed follow-up fixes** on the existing branch; they are test-pinned and independent of the open decisions above.

---

_The Notebooks document records the resolved General default (open question 4) and the duplicate guidance in requirement 21, both confirmed 16 November 2026. No other product document was edited, and `design/feature-F5-Progress and motivation.md` is untouched. The first-pass and follow-up fixes are committed on the branch; everything else follows the steps as specified._

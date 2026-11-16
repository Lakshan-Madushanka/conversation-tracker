# Notebooks — Quality Report

| Item             | Detail                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| ---------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Feature          | F14 Notebooks: creation, listing and search, the autosaving three-box board, scoped identities, Collections sources and marks, Create review and practise, daily/Quick participation, and hard deletion with an impact preview                                                                                                                                                                                                                          |
| Date             | 16 November 2026                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| Environment      | Windows, local Laravel app; PHP 8.5.6, Laravel 13.36.0, Inertia 3.5.1 (`@inertiajs/vue3` 3.0), Vue 3.5.13, Tailwind 4.1.1, shadcn-vue, SQLite                                                                                                                                                                                                                                                                                                          |
| Sources of truth | `design/spec.md`, `design/features.md` (F14), `design/feature-Notebooks.md`; the shared sections of `feature-F3-Personal collections.md`, `feature-F4-Review and re-practice.md`, `feature-Custom reviews.md`, and `feature-F5-Progress and motivation.md`                                                                                                                                                                                                 |
| Dataset          | Seeded local database: **1 notebook with 1 saved word**, 52 sessions, 17 topics. The browser checks used the same database. It is a development dataset, not a scale fixture; the query-flatness tests create their own rows.                                                                                                                                                                                                                           |
| Result           | Steps 1–6 are implemented and covered by 93 backend tests and 22 component/page tests. This pass found and fixed two real listing defects: a topic read per card (N+1), and a listing that kept showing the first page's cards after a search. Everything else that can be exercised in code passes. Two documented requirements are visibly missing — a topic filter on the listing and a matching-entry preview/link — and Progress reporting remains unimplemented by design while its F5 measures are open. |
| Repository       | Branch `notebook/step6-hard-deletion-polish`; Steps 5–6 in `bf7b513`, `d0abc8d`, `909444c`, PR #47. The quality-pass fixes described here are in the working tree, uncommitted at the time of writing. `design/feature-F5-Progress and motivation.md` was not modified in this pass.                                                                                                                                                                    |

**Verdict in one paragraph:** Notebooks behaves as the F14 document describes: a real notebook parent with shared topics, one autosaving board built from the session editors, identities scoped to the notebook so that a session's “bank” and a notebook's “bank” stay separate, Collections rows and marks that follow the scoped item, a Create review and practise shortcut with the 20/50 limits and selection step, daily/Quick practice for a notebook-only learner, and a stale-safe hard deletion that never touches its topic, other notebooks, sessions, or attempt snapshots. The two defects found in this pass were both on the listing: a page read its topic once per card, and a search changed the address but not the cards. Both now have failing-then-passing tests and fixes. What remains is one missing listing feature pair (topic filter, matched-entry preview), the deliberately unimplemented Progress review-activity area, export coverage, an unbounded candidate payload for very large notebooks, and the usual evidence gaps (no screenshots, screen reader, real device, or production-build timing).

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

| Step / behaviour                                                                         | Covered by                                                                                                    |
| ---------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| Parent rules, scoped identity, comma-separated meanings, theme and note identities       | `NotebookStorageTest`                                                                                         |
| Creation validation, General default, rich description, retry and cancel                 | `NotebookCreationTest`                                                                                        |
| Board save/reload with formatting, blanks, duplicates, retries, two-tab conflict         | `NotebookBoardTest`                                                                                           |
| Listing fields, search by metadata/entry/note, scope isolation, flat query count         | `NotebookListingTest`, `NotebooksIndex.test.ts`                                                               |
| Collections sources, filters, scoped marks, panels, typo-following, mixed sorting        | `NotebookCollectionsTest`; shared Collections UI covered by `CollectionsIndex.test.ts`                            |
| Review candidates, 20/50 rules, selection, retries, fixed membership, snapshots, isolation | `NotebookReviewTest`, `NotebookReviewDialog.test.ts`                                                          |
| Daily/Quick for notebook-only learners, per-scope ratings, deleted sources, no session totals | `NotebookDailyReviewTest`                                                                                    |
| Deletion preview, stale confirmation, topic/other-source survival, reference pruning      | `NotebookDeletionTest`, `NotebookDeleteDialog.test.ts`                                                        |
| Listing search refresh and stale-page race                                               | `NotebooksIndex.test.ts` (added in this pass)                                                                 |
| Shared topic rename/delete protection                                                    | `TopicManagementTest` plus `NotebookCreationTest`, `NotebookCollectionsTest`                                  |

**Tested** means executed by an automated test or a measured request. **Reviewed** means read and reasoned about. **Not verified** means there is no evidence; the gaps are listed in section 3. In this pass the in-app browser loaded the listing and drove a real search; the tab is hidden, so screenshots are unavailable and the check used the page address and DOM text.

### 1.4 Method and confidence

| Area                    | Method                                                                | Confidence                                                              |
| ----------------------- | --------------------------------------------------------------------- | ----------------------------------------------------------------------- |
| Behaviour               | Pest feature tests through HTTP routes; Vitest component/page tests   | High for the covered journeys                                           |
| Accessibility structure | Component tests, DOM assertions, source review                        | Medium-high; no real assistive technology                               |
| Keyboard and focus      | Focus assertions in dialogs and tests, source review                  | Medium; no live Tab traversal or screen reader                          |
| Responsive layout       | Tailwind breakpoints and shared card/dialog patterns, source review   | Medium; no device or screenshot pass                                    |
| Light/dark              | Shared tokens and components, source review                           | Medium-high; no computed-colour check                                   |
| Speed                   | Query-count assertions and request measurement through the booted app | High for query growth; local timings on a tiny dataset                  |
| Data safety             | Stale-token tests, read-only preview test, rollback-style assertions  | High for this local database                                            |

---

## 2. Fixes made (by area)

### 2.1 The notebook listing reads its topic once per card (this pass, uncommitted)

- **Problem:** `NotebooksListing::paginate()` counted the three boxes in the database but did not eager-load the topic, and `NotebookCardResource` then called `loadMissing('topic')` for every card. A 25-row page ran **28 queries** where a 5-row page ran **8**.
- **Change:** the listing query now selects `topic` with the page (`->with('topic')` before the counts). The resource keeps its `loadMissing` fallback for any other caller.
- **Result:** the small and large pages now run the same number of queries. `NotebookListingTest` holds it with a page-size comparison rather than a brittle fixed count.

### 2.2 A search changed the address but not the list (this pass, uncommitted)

- **Problem:** Inertia visits use `preserveState: true`, so the page component stays alive. Its cards, total, cursor and has-more refs were copied from the first props once and never followed later visits. Searching `zzzz` moved the address, but the page kept showing the first visit's card and “1 notebook”; the empty state never appeared. A search that found a different notebook would likewise have shown the old cards under the new address.
- **Change:** `resources/js/pages/notebooks/Index.vue` now watches the page props: a new `notebooks` list replaces the cards, and `total`, `next_cursor`, `has_more` and `search` follow their props. The search box stays in step with the address for Back/Forward and restored visits.
- **Result:** a search replaces the cards and the count together; clearing it restores the list. Verified in the browser (`?q=zzzz` → empty state, clearing → the card returns) and pinned by five page tests.

### 2.3 A next page could arrive after a new search (this pass, uncommitted)

- **Problem:** “Load more” is a plain JSON request. If it was already in flight when a search brought a new first page, its older page was appended to the new list, mixing results from two searches.
- **Change:** the page keeps a list generation counter, raised whenever new notebook props arrive. A load-more response from an older generation is discarded.
- **Result:** the stale page never lands in the list; a page test resolves the in-flight request after the search and asserts only the new card remains.

### 2.4 Test coverage added or strengthened

| File                                             | Added or strengthened                                                                                  |
| ------------------------------------------------ | ------------------------------------------------------------------------------------------------------ |
| `tests/Feature/NotebookListingTest.php`          | “reads a page in the same number of queries whatever it holds” (5-row vs 25-row page)                   |
| `resources/js/pages/__tests__/NotebooksIndex.test.ts` | Five tests: prop-driven cards, empty search state, search box sync, count/cursor adoption, stale page |

No test was deleted or weakened. The new backend test was confirmed red before the eager load (**28 vs 8 queries**) and green after; the page tests were confirmed red before the watchers and the generation guard.

---

## 3. Issues not fixed

| #   | Severity | Issue                                                                                                                                                                                                                                                                                   | Recommendation                                                                                                                                              |
| --- | -------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 3.1 | Medium   | The listing has search but **no topic filter**. Requirement 3 asks for both, and requirement 56 asks that they combine. The topic picker on the page belongs to the create dialog only.                                                                                                   | Add a topic filter to the listing request, query and address, alongside search, and cover the combination with a feature test.                              |
| 3.2 | Medium   | Search can match through an entry while the card shows only name, topic and description. Requirement 55 asks for a meaningful matching-entry preview and a way to open that entry when the match is not visible metadata; requirement 54 also mentions safe highlighting.               | Return a small matched-entry snippet (plain text, formatting ignored) per card and render it with the existing highlight pattern used by Collections.       |
| 3.3 | Medium   | Progress still shows sample data; no review-activity area exists. Requirement 80 is explicitly blocked until the F5 measures are decided, but requirements 79 and 81 are only partly verified (no session totals or streak are invented).                                                | Record the review-activity measures in F5 first, then build the read model. Do not copy daily ratings into session totals or custom-attempt results.        |
| 3.4 | Medium   | Export does not represent notebooks at all — not even as an unchecked option — while the feature list promises all-data backup. This is open question 5 in the Notebooks document.                                                                                                      | Decide the export shape for notebook metadata, entries, scopes and marks before F7 ships; add notebook rows to the existing export options.                 |
| 3.5 | Medium   | The review-candidate read is unbounded by design: it loads every saved row of the notebook to build the selection screen, and the JSON sends every candidate. A notebook with thousands of rows produces a large response and PHP grouping work. Technical note 10 asks for paginated selection results. | Paginate the candidate payload (and the selection screen) above a threshold, or send compact identities plus content on demand. Keep the server-side revalidation. |
| 3.6 | Low      | Deleting a card from the listing removes the focused Delete button; focus falls to the document body. The dialog itself focuses Cancel and returns focus while its trigger exists.                                                                                                       | Move focus to the next card's open link, or to the search box when the list becomes empty.                                                                  |
| 3.7 | Low      | No screenshots, real-device pass, Firefox/Safari check, or screen-reader pass was possible in this round; the browser tab is hidden and screenshots fail. Mobile stacking, dark mode and announcements are covered by classes, tokens, tests and source review only.                        | Run one manual pass (Chrome at 375 px, one phone, NVDA/VoiceOver) before calling accessibility verified.                                                    |
| 3.8 | Low      | The dataset is tiny (one notebook, one word), so request sizes and timings in section 6 describe overhead, not scale. Query growth is tested; payload growth for very large notebooks is not measured.                                                                                   | Seed a scale fixture (hundreds of notebooks and entries) in a scratch database and measure listing, board, preview and candidate payloads.                  |
| 3.9 | Low      | Deletion preview loads all non-blank entries of the notebook to count rows and identities. This is bounded by one notebook (reasonable), but a notebook at the scale in 3.5 will produce a slow preview and dialog.                                                                     | Count rows in SQL and stream identities only if previews become slow; keep the read side-effect free.                                                       |

---

## 4. Differences from the documents (including decisions to verify)

| #   | Decision taken                                                                                         | What the documents say / imply                                                                                       | Why                                                                                                                          | Action for the product owner                                                    |
| --- | ------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------- |
| 4.1 | A renamed **General** topic is not reused; a later notebook created without a topic gets a new General. | Open question 4 asks how the default is resolved if the shared General is renamed or deleted while unused.            | The covered behaviour is “find or create General again after it was renamed”, which keeps creation predictable and writes only on create. | Confirm this is the intended rule, or define a stable default marker.            |
| 4.2 | Notebook practice appears in the Review page's recent-review list and never as a session total.         | F5 requirements 7–9, 34–35 and Notebooks 79–81.                                                                       | Matches the documents; notebooks feed daily/Quick and leave session measures alone.                                            | None.                                                                            |
| 4.3 | The listing shows counts of saved rows per kind, not distinct study items.                             | Requirement 2 asks for item counts; requirement 21 says repeated rows are one study identity.                        | The card is a source summary and the editor is where duplicates are resolved; deletion preview separately distinguishes rows from items. | Confirm whether the card should show “3 saved rows” or “2 items” when duplicates exist. |
| 4.4 | Editing a notebook updates its last-updated time; marking or practising does not.                      | Requirement 31.                                                                                                      | Matches; `last_changed_at` changes only on metadata/entry saves.                                                              | None.                                                                            |
| 4.5 | Notebook deletion is hard deletion; marks remain stored under the old scope and are not inherited.     | Requirements 82, 87 and 41.                                                                                          | Matches; a later notebook with the same name and words does not inherit them.                                                 | None.                                                                            |

---

## 5. Test results

| Check                    | Command                                                                                       | Result                                              |
| ------------------------ | --------------------------------------------------------------------------------------------- | --------------------------------------------------- |
| Rector                   | `composer rector`                                                                              | Passed, 0 errors                                    |
| Pint                     | `vendor/bin/pint --dirty --format agent`                                                       | Passed                                              |
| PHPStan                  | `composer types:check`                                                                         | Passed, 0 errors                                    |
| PHP test suite           | `php artisan test --compact`                                                                   | **955 passed, 8,840 assertions**, 45.8 s            |
| Notebook tests           | `php artisan test --compact tests/Feature/Notebook*Test.php`                                   | **93 passed, 1,228 assertions**, 4.9 s              |
| Frontend format and lint | `npm run check`                                                                                | Passed, 266 files formatted, 246 linted, no warnings |
| TypeScript               | `npm run types:check`                                                                          | Passed                                              |
| JavaScript test suite    | `npx vp test --run`                                                                            | **1,006 passed, 82 files**, 79.4 s                  |
| Notebook frontend tests  | `npx vp test resources/js/components/__tests__/Notebook*.test.ts resources/js/pages/__tests__/NotebooksIndex.test.ts --run` | **22 passed, 3 files**, 4.8 s                       |
| Production build         | `npm run build`                                                                                | Not run in this round (3.7)                         |

Failures seen and resolved while writing the fixes:

- `NotebookListingTest > reads a page in the same number of queries whatever it holds` failed first at **68 vs 8** (the test initially created notebooks while logging, which was corrected), then at **28 vs 8** for a real N+1, and passed after eager loading.
- `NotebooksIndex.test.ts` failed all four original tests before the prop watchers, and the stale-page test failed before the generation guard.

Regression coverage that matters for this step:

- One word in two notebooks and a session is three identities; duplicate rows do not inflate membership; grammar notes never merge.
- A stale deletion confirmation is refused with fresh numbers; a late save cannot recreate the notebook; a review losing its last member becomes Empty while snapshots remain.
- A notebook-only learner receives a daily suggestion; rating a notebook word leaves a same-spelling session word's schedule alone; a saved custom attempt never appears as review activity.
- The listing returns each notebook once and does not match through another notebook or a session.

---

## 6. Measurements

### 6.1 Request measurements through the booted app

Measured with `app()->handle()` in `php artisan tinker` on the development database, including routing, middleware, controller, resources and rendering:

| Route                               | Status | Queries | Body size |
| ----------------------------------- | ------ | ------- | --------- |
| `/notebooks` (JSON, 1 notebook)     | 200    | 7       | 889 B     |
| `/notebooks/1` (HTML board)         | 200    | 7       | 3,046 B   |
| Deletion preview for that notebook  | —      | 5       | —         |

The dataset has one notebook and one saved word, so these numbers describe fixed overhead, not scale. Payload and response-time growth were not measured on a large notebook (3.8).

### 6.2 Query growth

- `NotebookListingTest` compares a 5-row page with a 25-row page and asserts the same query count. Before the eager load the large page ran **28** queries; after it, both sides match.
- The listing does not read entries to count them: the three counts are correlated subqueries in the page query, plus one query for the page's topics and one for the total.
- The board and the deletion preview read one notebook's rows directly; their query counts do not depend on the number of notebooks.

### 6.3 What the numbers mean

- The listing is a bounded read: the fixed overhead is seven queries regardless of page size, and the per-card topic read is gone.
- The candidate selection is the one unbounded read (3.5): it is bounded by notebook size, not by a page.
- Local dev timings were not quoted because the dataset is too small to make them meaningful; the production build was not measured.

---

## 7. Open questions

1. Should the notebook listing get its topic filter and matched-entry preview before the feature is called complete (3.1, 3.2)?
2. Should the card's counts show saved rows or distinct study items when duplicates exist (4.3)?
3. Is re-creating General after a rename the intended default rule (4.1, Notebooks open question 4)?
4. What are the F5 review-activity measures, and when do they start (3.3, Notebooks open question 2)?
5. What is the export shape for notebooks, and does it ship with F7 (3.4, Notebooks open question 5)?
6. How large may a notebook grow before candidate selection must paginate (3.5)?

---

## 8. Suggested next steps

1. **Add the listing's topic filter** and cover search + topic together; this closes the largest documented gap (3.1).
2. **Add a matched-entry preview and link** so a search hit through an entry is explainable (3.2).
3. **Paginate review candidates** above a threshold, then measure a large notebook (3.5, 3.9).
4. **Record the F5 measures**, then build the separate review-activity area; do not touch session-derived numbers (3.3).
5. **Decide notebook export** before F7, so the all-data promise stays honest (3.4).
6. **One manual accessibility and device pass**, plus a production-build measurement (3.7, 3.8).
7. **Commit and review the two listing fixes** on the existing branch; they are small, test-pinned, and independent of the open decisions above.

---

_No product documents were edited. The two fixes in this pass are uncommitted at the time of writing; everything else follows the steps as specified._

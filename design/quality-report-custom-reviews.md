# Custom reviews — Step 6 Quality Report

| Item             | Detail                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| ---------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Feature          | Custom reviews (build a named set from chosen items, mark it Finished, practise it as many attempts as you like, keep the history)                                                                                                                                                                                                                                                                                                                                                                                                |
| Date             | 8 November 2026                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| Environment      | Windows, local Laravel app; PHP 8.5, Laravel 13.36, Inertia 3.0, Vue 3.5, Tailwind 4.1, shadcn-vue, SQLite                                                                                                                                                                                                                                                                                                                                                                                                                        |
| Sources of truth | `design/spec.md`, `design/features.md`, `design/feature-Custom reviews.md`                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| Dataset          | Local database for the browser pass (1 review, 3 items, 52 sessions, 0 attempts); a seeded in-memory SQLite database for the scale pass (5,000 items in one review, 300 reviews, 500 attempts × 5 items)                                                                                                                                                                                                                                                                                                                          |
| Result           | Every Step 6 behaviour that can be exercised in code is covered by passing tests, and exclusivity now has its own automated proof. Three small safe fixes were made (attempt wording, mobile touch targets, stepper label) plus two tests. Three larger defects were found while measuring and are reported rather than changed: starting a 5,000-item attempt fails in SQL, one INSERT runs per attempt item, and the item list and attempt history are unpaginated. No product document was edited and no dependency was added. |
| Repository       | The Step 6 polish is committed on this branch, with `tests/Feature/CustomReviewExclusivityTest.php` as its new test file. The temporary measurement harness was created, used, and deleted in this pass.                                                                                                                                                                                                                                                                                                                          |

**Verdict in one paragraph:** Custom reviews behaves as the feature document describes: reviews are exclusive, attempts are numbered ordinally and snapshot their items, statuses stay literal, and nothing inside a review leaks into Collections, Review, Progress or the schedule. The Step 6 concern — proving that — is now covered by an automated snapshot test that walks a whole learner journey in both directions. Accessibility, responsiveness and markup safety were checked in a real browser and fixed where they were short (44 px touch targets on phones, an attempt-specific stepper label). Measuring at the document's own scale of "about 5,000 items" exposed the one real defect: starting an attempt at that size dies with SQLite's "Expression tree is too large", and even where it works the write path costs one query per item while the review page ships 2.79 MB. Those are structural and are reported here rather than changed in a polish pass.

---

## 1. Summary

### 1.1 What was in scope

- The custom review listing (`/custom-reviews`), the review page, the attempt screen, and the attempt history they expose.
- Consistency of wording, headings and labels across those screens.
- Accessibility, responsiveness, light/dark mode, speed, safety and regression for those screens and their endpoints.
- Proof that the feature is exclusive: nothing done inside a custom review changes anything outside it.

### 1.2 What was out of scope

- New features, redesigns, and behaviour changes. Fixes in this pass are small and safe; larger ones are reported in section 4.
- Editing `design/spec.md`, `design/features.md`, or `design/feature-Custom reviews.md`. Every difference found is listed in section 5 instead.
- Multi-user accounts and authorisation: the app is for one learner and has no `user_id` anywhere.
- Export/backup (F7), which the feature document defers to a later feature.

### 1.3 What was verified, and how

| Area                    | Evidence                                                                                            |
| ----------------------- | --------------------------------------------------------------------------------------------------- |
| Exclusivity             | `tests/Feature/CustomReviewExclusivityTest.php` (3 tests, 215 assertions)                           |
| Attempt wording         | `CustomReviewAttemptLabelTest`, `CustomReviewStepFourTest`, `CustomReviewStepFiveTest`              |
| Markup safety           | `CustomReviewCard.test.ts` (name and description rendered as plain text)                            |
| Query-count flatness    | `CustomReviewStepThreeTest`, `CustomReviewsTest`, `CustomReviewTest`, `CustomReviewStepFourTest`    |
| Full regression         | `php artisan test --compact` (805 tests) and `npm test` (925 tests)                                 |
| Static analysis / style | Rector, Pint, PHPStan, `vp check`, `vue-tsc`                                                        |
| Browser, accessibility  | Lighthouse and console/overflow checks on `/custom-reviews` and `/custom-reviews/1`, light and dark |
| Scale                   | A temporary Pest harness that seeded 5,000 items and timed the real routes, then was deleted        |

**Tested** means executed by an automated test or a timed request. **Reviewed** means read and reasoned about. **Not verified** means there is no evidence; those gaps are in sections 4 and 8.

### 1.4 Method and confidence

| Area                    | Method                                                                                             | Confidence                                       |
| ----------------------- | -------------------------------------------------------------------------------------------------- | ------------------------------------------------ |
| Behaviour               | Pest feature tests through the HTTP routes; Vitest component tests                                 | High for the covered journeys                    |
| Exclusivity             | Full-table snapshots of every non-custom table plus page props, taken before and after each action | High; the strongest evidence in this pass        |
| Accessibility structure | Lighthouse audits, DOM assertions, source review                                                   | High for axe-detectable issues; no screen reader |
| Keyboard and focus      | Focus-visible source review and Tab attempts                                                       | Medium; see the limitation in section 4          |
| Speed                   | Timed kernel requests against a seeded database, plus query logs                                   | High for reads, high for the write-path finding  |

## 2. Exclusivity proof

`tests/Feature/CustomReviewExclusivityTest.php` keeps an automated proof in the suite. It takes a snapshot of the whole application — every row in `topics`, `practice_sessions`, `practice_questions`, `practice_feedback_entries`, `collection_item_marks`, `reviews`, `review_items`, `ratings`, `review_schedules`, `review_suggestion_declines`, plus the rendered props of Home, Library, Review Home, Collections, the suggestion panel, and the session-delete preview — and asserts it is byte-for-byte unchanged after every learner action inside a custom review.

Three tests:

1. **"changes nothing outside custom reviews, whatever the learner does inside one"** — one long journey through the real routes: create a review, add items, name and describe it, mark it Finished, start an attempt, rate, undo, rate again, finish early, read the attempt page and the review page, fill it to twenty items, trigger the size warning, confirm the crossing add, remove items, empty it, run a second attempt to completion, then delete the review. The world snapshot is compared after every single step (21 comparisons). The custom-review affordances on the Collections page (`review_target`, `review_context`, per-item `in_review`) are the only values excluded, because they are the feature itself, not a change to an item.
2. **"leaves custom reviews untouched when the rest of the app is used"** — the reverse direction: while a review holds a rated attempt, the learner marks a different item Learned, records a daily rating, declines a suggestion, and archives then restores the session. The four custom tables must be identical afterwards.
3. **"keeps a past attempt's snapshot when the item it named is corrected"** — the typo-fix case: correcting `teh` to `the` in the session editor moves the review's reference to the new identity, while the past attempt keeps `teh` as its snapshot text and is not marked Deleted, because a corrected spelling is not a deletion.

The three tables that hold the feature's data (`custom_reviews`, `custom_review_items`, `custom_review_attempts`, `custom_review_attempt_items`) are also snapshotted in test 2, so the proof runs in both directions.

## 3. Fixes made

All three are small, safe, and covered by tests. No behaviour outside wording and sizing changed.

1. **Attempt wording unified to the ordinal the document asks for.** The attempt number was shown as "Attempt 1" in the attempt page and history resource, but as "1st attempt" in headings and titles. `CustomReviewAttemptLabel::numberLabel(?int)` now owns the one wording (`"Not started"`, `"1st attempt"`, `"11th attempt"`), and the controller, the history resource, `number()` and `heading()` all delegate to it. `previous()` now says **"Previous attempt: Completed"** instead of "Previous: Completed", matching requirement 58. A unit test covers null, 1, 2, 3, 11 and 12; the Step Four and Step Five feature tests and the JS fixtures and types were updated with it.
2. **Mobile touch targets raised to 44 px.** Interactive controls on custom-review surfaces were 32–36 px tall on phones. They now use `min-h-11` / `size-11` below the `md` breakpoint and keep the compact `min-h-9` / `size-8` size on desktop: Back, Add items, See original, Remove, Show/Hide items, Clear and its dialog buttons in `Show.vue`; both "Try again" buttons, Load more and the empty-state button in `CustomReviewList.vue`; "See original session" in `CustomReviewAttemptItems.vue`; Cancel and Delete in `CustomReviewDeleteDialog.vue`; Cancel and Continue in `ReviewAddDialogs.vue`; and both states of `AddToReviewButton.vue`.
3. **Stepper label names the screen it is on.** `ReviewExerciseShell.vue` labelled the attempt stepper "Review steps"; it now says **"Attempt steps"** when `subject === 'attempt'`, so assistive technology is not told the wrong screen.
4. **Markup-injection test added** (`CustomReviewCard.test.ts`): a review named `<script>alert(1)</script>` with a description holding an `<img onerror>` must render as visible text with no `script` or `img` element created. This is the regression guard for the escaping the card already relied on.
5. **Exclusivity proof added** (section 2).

No product document, migration, model, route, or dependency was changed. No new library was added, dev-only or otherwise.

## 4. Issues not fixed

Reported rather than changed, because each is a structural or behaviour-affecting fix.

1. **Starting an attempt on a very large review fails outright.** With 5,000 items in one review, `POST custom-reviews/{review}/attempts` raises `SQLSTATE[HY000]: General error: 1 Expression tree is too large (maximum depth 1000)`. The cause is `app/Queries/ReviewCandidates.php` (around line 144), which builds one `OR` pair per item identity for the "has this identity been rated" check. The document says a review "has no maximum size" and that with about 5,000 items "attempts stay fast"; today the attempt simply cannot start. Fixing it means changing how that query is built (a join against a temporary set or a chunked/`whereIn` strategy), which is a behaviour-adjacent change to a shared query — out of scope for a polish pass.
2. **The attempt build writes one row at a time.** `App\Actions\CustomReviewAttempts\StartCustomReviewAttempt` inserts each attempt item separately: 512 queries to start a 500-item attempt, taking 648 ms on SQLite. At 5,000 items that would be thousands of round trips inside one request. A bulk insert is the obvious fix but changes the action's transaction and error handling, so it is reported.
3. **The review page is not paginated.** A review holding 5,000 items renders in 1.63 s and ships a **2.79 MB** Inertia payload, because every item, every group and every attempt is returned in one response. The listing paginates ("Load more"), but the item list and the attempt history do not.
4. **The attempt history is not paginated.** 500 attempts render in one response (123 ms, 2 queries — cheap, but the payload grows without bound). The document says of very large reviews that "the list is shown in pages", which the app does not do for either list.
5. **No ownership checks anywhere.** The app has no authentication and no `user_id`, so any route is reachable by anyone who can reach the server. This is consistent with the single-learner design of the whole app, not a custom-review regression; recorded so it is not mistaken for a passed security control.
6. **Keyboard-only traversal not observed live.** Focus-visible styles were reviewed in source and every visible control has one (the skip link uses `focus:` rather than `focus-visible:`, which is correct for a skip link), but Tab could not be delivered to the unfocused desktop window during this pass, so no end-to-end keyboard run is claimed.
7. **Screen readers not exercised.** Lighthouse and DOM assertions were used; no real assistive technology was driven.
8. **Local database has no attempt to click through.** The seeded local data holds one Draft review with three items and zero attempts, and writing to the developer's database to create one was deliberately avoided; attempt-screen browser checks therefore rely on the component tests and the feature tests rather than a live click-through.

## 5. Differences from documents

No document was edited. These are the differences found, listed for the document owner:

1. **"Very large reviews: an attempt carries every eligible item, is still built quickly, and the list is shown in pages."** (requirement, edge cases). The attempt cannot be built at all at 5,000 items (issue 4.1), and neither the item list nor the attempt history is paginated (issues 4.3, 4.4).
2. **"A custom review has no maximum size, and neither has an attempt."** True in the data model, but false in practice above roughly a few thousand items because of issue 4.1. The document's "about 5,000 items … attempts stay fast" claim is not currently reachable.
3. **"With about 5,000 items and many reviews, the listing, review pages, and attempts stay fast, with query counts that do not grow with the number of items."** Holds for every read path measured (listing 10 queries at 300 reviews, review page 14 queries at 5,000 items, history 2 queries at 500 attempts). It does not hold for the attempt write path, which is one query per item (issue 4.2).
4. **Attempt wording (requirements 45 and 58).** The app previously said "Attempt 1" and "Previous: Completed"; the document asks for "1st attempt" and "Previous attempt: Completed". This was a difference and has been fixed (section 3.1) rather than reported as an open deviation.
5. **Nothing else diverged.** Status wording, the "Too Many Items" threshold and tag, the exclusive-table rule, snapshot and Deleted-indicator behaviour, and the never-a-test tone all matched the document as written and as tested.

## 6. Test results

| Command                                    | Result                                              |
| ------------------------------------------ | --------------------------------------------------- |
| `composer rector`                          | Passed, 0 files changed                             |
| `vendor/bin/pint --dirty --format agent`   | Passed                                              |
| `composer types:check` (PHPStan)           | Passed, 0 errors                                    |
| `php artisan test --compact` (narrow)      | 46 passed, 844 assertions                           |
| `php artisan test --compact` (full suite)  | **805 passed, 7,203 assertions** (baseline was 801) |
| `npm run check` (`vp check`)               | 244 files formatted, 0 lint errors in 227 files     |
| `npm run types:check` (`vue-tsc --noEmit`) | Passed                                              |
| `npm test` (`vp test`)                     | **73 files, 925 tests passed**                      |

Nothing was removed or weakened to reach these numbers: four PHP tests were added (three exclusivity, one label unit) and one JS test (markup injection); existing assertions were only updated where the wording they assert changed.

## 7. Measurements

All timings below are kernel requests against a seeded in-memory SQLite database on the same Windows machine, including routing, controllers, resources and Inertia rendering, but not the browser or the network. Query counts come from Laravel's query log.

| Path                                                  | Time       | Queries | Payload     |
| ----------------------------------------------------- | ---------- | ------- | ----------- |
| Listing, 300 reviews (`GET /custom-reviews`)          | 413.5 ms   | 10      | 19,084 B    |
| Review page, 5,000 items (`GET /custom-reviews/{id}`) | 1,629.4 ms | 14      | 2,793,368 B |
| Attempt history, 500 attempts × 5 items (query class) | 122.9 ms   | 2*      | —           |
| Start attempt, 5,000 items                            | **failed** | 6†      | —           |
| Start attempt, 500 items                              | 648.5 ms   | 512     | 500 rows    |

\* Three queries were counted, one of which is the `findOrFail` on the review outside the history class itself; the history read is 2 queries, matching `CustomReviewStepFourTest` (12 attempts × 5 items in exactly 2 queries).
† The failure occurs inside the candidate query after six statements; the error is `SQLSTATE[HY000]: General error: 1 Expression tree is too large (maximum depth 1000)`.

Existing flat-query tests, re-run in the full suite: the listing reads in a fixed number of queries however many reviews there are (`CustomReviewStepThreeTest`); the review page issues the same queries at 3 items and at 40 (`CustomReviewsTest`); the custom-review pool counts in a bounded number of queries whatever its size, at 20 and at 200 rows (`CustomReviewTest`); the attempt history reads in exactly 2 queries (`CustomReviewStepFourTest`).

**Browser pass** (`php artisan serve` + `npm run dev`, Chrome):

| Check                                             | `/custom-reviews`                                                      | `/custom-reviews/1`  |
| ------------------------------------------------- | ---------------------------------------------------------------------- | -------------------- |
| Lighthouse accessibility                          | 100 (light and dark)                                                   | 100 (light and dark) |
| Lighthouse best practices                         | 100                                                                    | 100                  |
| Lighthouse SEO                                    | 100                                                                    | 100                  |
| Console errors                                    | none                                                                   | none                 |
| Horizontal overflow (`scrollWidth − clientWidth`) | 0                                                                      | 0                    |
| Heading order                                     | h1 → h2                                                                | h1 → h2              |
| Dark mode toggle                                  | works ("Switch to dark mode")                                          | works                |
| Focus-visible on visible controls                 | all, except the skip link (uses `focus:`) and the toggle-group wrapper |

The temporary harness `tests/Feature/TempStepSixMeasureTest.php` produced the scale table and was deleted before this report was written; it is not part of the suite.

## 8. Open questions

1. **How large is a real custom review?** The document names 5,000 items as the scale to hold. If real reviews stay in the low hundreds, issue 4.1 is theoretical and can wait; if not, it is the first thing to fix.
2. **Should the item list and attempt history paginate like the listing does?** The document says pages, the app returns everything, and the review page already ships 2.79 MB at 5,000 items.
3. **Is SQLite the production database?** The expression-tree limit is hit in SQLite; a different engine may fail later or differently, which changes how issue 4.1 should be fixed.
4. **Is a per-item INSERT acceptable at this size?** It is simple and transactionally clean today; at thousands of items it dominates the request.
5. **Does the single-learner assumption hold for the foreseeable future?** Ownership checks (issue 4.5) are cheap to add now and expensive to retrofit if accounts ever arrive.
6. **Should a live keyboard and screen-reader pass be scheduled?** Evidence for both is structural only (issue 4.6, 4.7).

## 9. Suggested next steps

1. Fix the candidate query so an attempt can start at large sizes (issue 4.1), with a regression test that starts an attempt on a review big enough to trip the current limit.
2. Bulk-insert attempt items in `StartCustomReviewAttempt` (issue 4.2) and keep the existing transaction and status guarantees.
3. Paginate the review page's item list and the attempt history (issues 4.3, 4.4), reusing the listing's "Load more" pattern so the wording and address behaviour stay consistent.
4. Run a real keyboard and screen-reader pass over the listing, review page and attempt screen (issues 4.6, 4.7), and click through an attempt in a browser once one exists in the local data (issue 4.8).
5. Commit this Step 6 work in small, conventional commits: wording, touch targets and labels; the exclusivity proof; the markup-injection test; then this report.

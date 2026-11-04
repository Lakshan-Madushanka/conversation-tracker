# Personal Collections — Step 6 Quality Report

| Item             | Detail                                                                                                                                                                                                                                                                                                                                                  |
| ---------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Feature          | F3 Personal collections (Vocabulary, Phrases, Grammar notes, My mistakes; open an item, mark it, find it, jump back to its session)                                                                                                                                                                                                                       |
| Date             | 4 November 2026                                                                                                                                                                                                                                                                                                                                          |
| Environment      | Windows, local Laravel app; PHP 8.5, Laravel 13.36, Inertia 3.0, Vue 3.5, Tailwind 4.1, shadcn-vue, SQLite                                                                                                                                                                                                                                              |
| Sources of truth | `design/spec.md`, `design/features.md`, `design/feature-F3-Personal collections.md`                                                                                                                                                                                                                                                                      |
| Dataset          | Seeded local database: 678 sessions (576 finished and not archived), 16 topics; 4,509 vocabulary rows (2,979 items), 4,486 phrase rows (2,994 items), 2,679 grammar notes, 4,662 mistake rows (2,746 items); 138 marks (89 starred, 44 learned, 62 still difficult, 65 with notes)                                                                        |
| Result           | Every Step 6 behaviour that can be exercised in code is covered by passing tests. One real performance defect was found while measuring and fixed: a page of Collections carried every appearance of every item, so the mistakes tab took seconds and sent most of a megabyte. It now sends the newest appearance per item, and the opened item keeps its full history. No product document was edited and no dependency or feature was added. |
| Repository       | Step 6 polish is commit `118c79f`; the performance and test work in this pass is `701d032`; this report is committed on its own. Unrelated F4 work in the working tree was left untouched.                                                                                                                                                              |

**Verdict in one paragraph:** Collections behaves as the F3 document describes: four live collections, one item per word, phrase or mistake, grammar notes never merged, marks that belong to the item, the shared "Needs attention" rule, search and filters in the address, and an opened item that links back to the exact row in the session. The step's own concern — staying in step with sessions — is covered by tests for finishing, editing, archiving, restoring, deleting, bulk actions and mark movement. Measuring 5,000-row collections exposed one defect worth fixing: list requests returned every appearance of every item (834 KB and 5–9 s for one page of mistakes). A page now returns only the newest appearance (24–32 KB, under a second), while an opened item still carries its history. What remains is mostly evidence (no live browser, device or screen-reader pass in this round), one cross-tab mark race, and the weight of an opened mistake seen hundreds of times.

---

## 1. Summary

### 1.1 What was in scope

- The Collections page (`/collections`): four tabs with live counts, lists, search, filters, sorting, pagination, empty/no-result/error states, and the page address.
- Opening an item (word, phrase, grammar note, mistake): full text, all distinct meanings/examples/reasons, the "Seen in" list, "See original session", star, status and notes.
- The shared rules the page depends on: item identity, the "study sessions" rule, the "Needs attention" rule, archive wording, and marks following an edited entry.
- Consistency, accessibility, responsiveness, light/dark mode, speed, reliability and data safety for those screens and their endpoints.

### 1.2 What was out of scope

- Building Review (F4) and Progress (F5); the working tree contains F4 work that this pass did not touch or commit.
- Import/export (F6/F7).
- Multi-user accounts and authorisation: the app is for one learner and has no `user_id` anywhere.
- Redesigning the Collections layout or adding features. This pass fixes correctness, consistency, accessibility, speed and safety only.

### 1.3 What was verified, and how

The Step 6 journeys are exercised through the route the learner uses, not only through classes:

| Journey / behaviour                                                       | Covered by                                                                            |
| ------------------------------------------------------------------------- | ------------------------------------------------------------------------------------- |
| Finishing, editing, archiving, restoring and deleting a session           | `CollectionStaysFreshTest`, `CollectionTest`, `CollectionBrowseTest`                   |
| Bulk archive/restore/delete giving the same result as one-by-one          | `CollectionStaysFreshTest`                                                             |
| A mark moving with an edited word, and staying put when it still appears  | `CollectionMarksFollowEditTest`, `CollectionMarksTest`                                 |
| Stars and statuses surviving archive and restore                          | `CollectionMarksTest`                                                                  |
| Notes with the formatting the app allows, and their plain-text copy       | `CollectionMarksTest` (PHP), `CollectionMarksSection` and `CollectionItemPanel` (JS)  |
| The opened item's full history and "See original session" targets         | `CollectionTest`, `CollectionBrowseTest`, `CollectionItemPanel`, `CollectionMistakePanel` |
| Search, filters, sorting, chips, and invalid values in the address        | `CollectionBrowseTest`, `collection-filters.test.ts`, `CollectionsIndex.test.ts`        |
| Archive and delete wording on every surface                               | `SessionDeleteDialog`, `SessionBulkDeleteDialog`, `LibraryArchive`, `Show` tests        |
| Focus after a mark removes an item, after closing the panel, after a miss | `CollectionsIndex.test.ts`, plus source review of `focusCollectionItem`                 |
| Query counts staying flat as items grow                                   | `CollectionTest` query-flatness tests, now including `meta=1`                          |

The measurements in section 6 were taken by sending real requests through the Laravel kernel with `app()->handle()` in `php artisan tinker`, using the seeded `database/database.sqlite`. That includes routing, middleware, controllers, resources and Inertia rendering, but not the browser or the network.

**Tested** means executed by an automated test or a timed request. **Reviewed** means read and reasoned about. **Not verified** means there is no evidence; the evidence gaps are listed in section 3.

### 1.4 Method and confidence

| Area                    | Method                                                              | Confidence                                                     |
| ----------------------- | ------------------------------------------------------------------- | -------------------------------------------------------------- |
| Behaviour               | Pest feature tests through the HTTP routes; Vitest component tests  | High for the covered journeys                                   |
| Accessibility structure | Component tests, DOM assertions, source review                      | Medium-high; no real assistive technology                       |
| Keyboard and focus      | Focus assertions and the focus helpers' source                      | Medium; no live Tab traversal this round                        |
| Responsive layout       | Tailwind breakpoints and the shared list/card patterns, source review | Medium; no device or iframe pass this round                     |
| Light/dark              | Shared tokens and components, source review                         | Medium-high; no live computed-colour check this round           |
| Speed                   | Nine interleaved timed rounds per route, plus `DB::getQueryLog()`   | High for this machine; local dev numbers, wide variance         |
| Data safety             | Transaction-rolled-back action timing, repeat-safe mark tests       | High for this local database                                    |

---

## 2. Fixes made (by area)

### 2.1 Archive wording (Step 6, committed in `118c79f`)

- **Problem:** the archive messages said only that a session was archived; the learner was not told that its items leave Collections nor that restoring brings them back.
- **Change:** the single and bulk archive toasts, the "This session is archived" banner, the Archived view and the delete warnings now use the wording the document asks for: archived sessions are hidden from the **Library and Collections** and can be restored any time; deleting removes the session from the **Library, Collections, and Review**.
- **Result:** the same calm sentence appears wherever archiving or deleting happens, matching requirement 54.

### 2.2 Marks stay fresh, and a failed mark is recoverable (committed in `118c79f`)

- **Problem:** a list refresh after an archive, restore, edit or delete left the tab counts, topic picker and "N need attention" count showing the old numbers. A failed star or status save showed nothing.
- **Change:** the first page of a refresh now also returns the surrounding counts (`meta=1`), and `useCollection` adopts them. The opened item disables both mark controls while a save is in flight and, on failure, shows "Your mark was not saved · Nothing changed on the server" with **Try again**, keeping the saved state on screen.
- **Result:** counts and lists never disagree after a session change, and a mark is either saved or visibly retryable.

### 2.3 The typo rule reads the stored identity (committed in `118c79f`)

- **Problem:** deciding whether an old item still appears elsewhere read and re-normalised every entry of that type.
- **Change:** the check now uses the `collection_key` stored on every row by the same shared normalisation the collections group by, so it is one indexed `exists()` query.
- **Result:** moving marks after an edit stays instant as the collections grow, with the same rule and the same outcomes (covered by `CollectionMarksFollowEditTest`).

### 2.4 Focus stays near the work (committed in `118c79f`)

- **Problem:** closing a panel or dismissing a "this item is gone" note dropped keyboard focus at the top of the page. On phone cards, the star/status corner floated over the row's open button, so the two controls competed for the same space.
- **Change:** closing a panel returns focus to the row it came from (when it still matches the filters); dismissing the missing-item note and clearing the search put focus on the search box; a mark that removes an item moves focus to its neighbour. The marks on phone cards moved into their own footer under a divider, so the open target is the whole card body.
- **Result:** focus lands on the next sensible control after every action, and the open and mark targets no longer overlap.

### 2.5 The list no longer carries every appearance (this pass, `701d032`)

- **Problem:** `TableCollection::buildForKeys()` read every row of every question an item appeared in, then grouped in PHP, sorted in PHP with a comparator that walked `question → session` for each comparison, and returned **every appearance of every item on the page**. A page of 30 mistakes in the seeded database produced 1,858 appearances, **834 KB of JSON**, and **5–9.5 seconds** per request; the list also carried meanings, examples and formatted corrections it never showed.
- **Change, in `app/Queries/TableCollection.php`:**
  - A page builds only the requested keys and only the **newest appearance** of each item; `find()` asks for the full history because that is what an opened panel shows.
  - Rows are ordered in SQL by session date, entry date and id, so the PHP comparator and its repeated relation walk are gone.
  - `entry_position` is computed by a correlated subquery that counts earlier rows of the same box, excluding blank rows exactly as the old in-PHP numbering did. A moved item still opens at the right row, and no other row of the question is read.
  - `count()` is a direct grouped join instead of `entryQuery()` plus `whereHas`; `attentionCount()` is one grouped query with the same rules (vocabulary: no meaning anywhere; phrase: a line that could not be split; mistake: the newest appearance still needs attention). The counts were cross-checked against the old implementation before replacing it (387 / 178 / 319, identical).
  - The opened mistake keeps **one appearance per question and reason**. A repeated row that adds neither a new place nor a new reason is dropped; every sighting and every distinct reason the panel shows is unchanged, but a mistake seen 265 times no longer ships 497 near-identical rows.
- **Result:** list payloads fell from 834 KB to **24–32 KB**, and the mistakes tab from 5–9.5 s to **0.6–1.0 s**. The opened mistake panel fell from 194 KB to **151 KB** and from ~1.5 s to ~1.2 s. Query counts are unchanged and flat (16 on a list page, 21 with an opened item).

### 2.6 Returning from a session restores the full item (this pass, `701d032`)

- **Problem:** the saved list copy of an item (newest appearance only) was preferred over the full `open_item` the server had just sent. Returning from a session could leave the panel with one appearance, hiding the other places it was seen.
- **Change:** `useCollection.restore()` prefers `initial.open_item` when it is the item being reopened, and only falls back to the saved list copy. A JS test covers the round trip with two appearances.
- **Result:** the panel keeps its whole history after "See original session" and Back.

### 2.7 The notes sign is announced (this pass, `701d032`)

- **Problem:** the "Has notes" sign was an icon with `aria-hidden="true"` and no accessible name, so a screen reader could not tell that an item had a note. The visual test asserted "Has notes" and failed.
- **Change:** `CollectionMarksCluster.vue` adds a visually hidden "Has notes" label inside the notes popover trigger.
- **Result:** the sign is named for assistive technology and the text assertion passes.

### 2.8 Test coverage added or strengthened

| File                                       | Added or strengthened                                                                                     |
| ------------------------------------------ | --------------------------------------------------------------------------------------------------------- |
| `tests/Feature/CollectionStaysFreshTest.php` | Freshness after finish, edit, archive, restore, delete and bulk actions                                   |
| `tests/Feature/CollectionTest.php`           | List carries one appearance while the panel carries every place; `meta=1` query flatness; mistake dedupe |
| `tests/Feature/CollectionBrowseTest.php`     | Topic filter keeps the full history on the opened item                                                     |
| `resources/js/composables/__tests__/useCollection.test.ts` | Restoring prefers the full opened item over the saved list copy                              |
| `resources/js/components/__tests__/CollectionList.test.ts` | Marks in their own footer; "Has notes" text still present                                  |

No test was deleted or weakened. Every list-appearance assertion that changed now asserts the smaller list **and** the full opened item, so the behaviour is still pinned at both ends.

---

## 3. Issues not fixed

| #   | Severity | Issue                                                                                                                                                                                                                                                                                                             | Recommendation                                                                                                                                                                              |
| --- | -------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 3.1 | Medium   | An opened mistake seen hundreds of times is still heavy: the heaviest seeded item sends ~151 KB and takes ~1.2 s, and its "Seen in" list has ~300 lines. The payload is bounded by sightings, not by the dataset, but it is the slowest screen in the feature.                                                   | Send one sighting per question plus a compact `reasons` list, or paginate "Seen in" inside the panel. Do not drop distinct reasons: the document promises each one with its sessions.           |
| 3.2 | Medium   | Star and status are saved together as a full `(starred, status)` pair. Two tabs that change **different** fields at the same moment can overwrite each other (last write wins). The existing tests deliberately assert this full-state behaviour.                                                                    | Send only the changed field (`PATCH`-style), or add a version/updated-at token and refuse a stale write with a calm message. Decide before Review writes stars or statuses too.               |
| 3.3 | Low      | Every full page load and every meta refresh recomputes all four tab counts and the attention count, about 0.3–0.4 s of the ~1 s mistakes page.                                                                                                                                                                    | Accept while it stays under a second. If it grows, cache the counts with explicit invalidation on session changes and mark writes; do not use a blind TTL, because collections must never be stale. |
| 3.4 | Low      | The star and status icon buttons are 32 × 32 px (`size-8`), below the project's 44 px touch goal but above WCAG 2.2 AA's 24 px minimum.                                                                                                                                                                          | Fix once across the app (shared `Button` sizes), not only on Collections.                                                                                                                   |
| 3.5 | Medium   | No live browser, real device, Firefox/Safari or screen-reader pass was run in this round. Focus, contrast and no-sideways-scroll behaviour are covered by tests and source review, not by observation.                                                                                                            | Run one manual pass (Chrome + one phone width + NVDA/VoiceOver) before calling accessibility verified.                                                                                        |
| 3.6 | Low      | Timings are local dev numbers taken through the booted app; this machine varied by ±40% between rounds, and the production build was not measured.                                                                                                                                                                 | Re-measure with `npm run build` + a production server before quoting numbers to a user.                                                                                                     |
| 3.7 | Low      | There is no authentication and no `user_id`; every mark and item belongs to the single learner. A guessed address reaches that learner's own data only.                                                                                                                                                            | Acceptable for a local personal app. Add user scoping before any hosted or shared use.                                                                                                      |
| 3.8 | Low      | A list item now carries only its newest appearance. Any future client code that reads `appearances` from a list item will see one place; the page always fetches `open_item` before opening. The type comment says so, but the shape is easy to misuse.                                                            | Keep the contract and document it, or add an explicit `sighting_count` to the list resource so intent is obvious.                                                                            |

---

## 4. Differences from the documents (including decisions to verify)

| #   | Decision taken                                                                                                     | What the documents say / imply                                                                                                       | Why                                                                                                                       | Action for the product owner                                                        |
| --- | ------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------- |
| 4.1 | A list item carries only its newest appearance; the opened item carries every place it was seen.                   | Requirement 8 says the list shows the most recent text and the opened item shows all distinct ones; it does not describe the payload. | Sending every appearance of every row on the page was the performance defect (2.5); the visible result is identical.        | Confirm the transport split is acceptable.                                          |
| 4.2 | An opened mistake keeps one appearance per question and reason.                                                    | Requirement 27 says one "Seen in" line per appearance; requirement 16 says a repeated row in the same box counts once.               | Both requirements are satisfied; duplicate rows added no line and no reason.                                                | Confirm, or ask for a literal row-by-row list (then 3.1 must be paginated).          |
| 4.3 | Star and status requests carry the whole desired state of both fields, never a toggle.                              | Requirement 32/33 say the request carries the desired state and two tabs cannot flip it.                                             | The tests pin last-full-state-wins; it is safe for retries but not for two tabs changing different fields (3.2).             | Decide whether per-field writes are needed before Review shares these marks.         |
| 4.4 | "Worth practicing" starts at three appearances, in one named constant.                                              | Requirement 23 says "three or more times" and that the threshold lives in one named setting.                                         | Matches.                                                                                                                   | None.                                                                                |
| 4.5 | Archive copy says archived sessions are hidden from the Library and Collections and can be restored any time.      | Requirement 54 asks for exactly this wording on the toasts, banner and Archived view.                                                | Old copy only said "archived"; corrected in `118c79f`.                                                                     | None.                                                                                |
| 4.6 | The delete warning names the Library, Collections and Review.                                                       | Requirement 54 and the F4 dependency ask for all three.                                                                              | Matches.                                                                                                                   | None.                                                                                |
| 4.7 | No `user_id`; "ownership" means the one learner's own data.                                                         | The assumptions state a single-learner app.                                                                                          | Adding accounts would be a feature, not polish.                                                                            | Confirm single-learner scope, or schedule user scoping before hosted use.            |
| 4.8 | Grammar notes keep their marks through any edit because the mark is tied to the note row.                           | Requirement 37 says a grammar note keeps its marks whenever its text is edited.                                                      | Matches; two identical notes still have separate marks.                                                                    | None.                                                                                |

---

## 5. Test results

| Check                    | Command                                  | Result                                                       |
| ------------------------ | ---------------------------------------- | ------------------------------------------------------------ |
| Rector                   | `composer rector`                        | Passed, 0 files changed, 0 errors                             |
| Pint                     | `vendor/bin/pint --dirty --format agent` | Passed                                                       |
| PHPStan                  | `composer types:check`                   | Passed, 0 errors                                             |
| PHP test suite           | `php artisan test --compact`             | **569 passed, 4,341 assertions**, 32.9 s                     |
| Collections tests        | `php artisan test --compact tests/Feature/Collection*.php` | **89 passed, 1,516 assertions**, 10.4 s   |
| Frontend format and lint | `npm run check`                          | Passed, 199 files formatted, 184 linted, no warnings         |
| TypeScript               | `npm run types:check`                    | Passed                                                       |
| JavaScript test suite    | `npm run test`                           | **750 passed, 58 files**, 96.5 s                             |
| Production build         | `npm run build`                          | Not run in this round (3.6)                                  |

One earlier JavaScript run failed `CollectionList > shows the chosen status and the note sign when they exist` because the notes sign had no accessible name. That was a real accessibility defect, fixed with the visually hidden label (2.7). The test now passes, including in the full suite recorded above.

Regression coverage that matters for this step:

- The list carries one appearance while the opened item carries every place: three for the vocabulary fixture, and two for the mistake fixture where two rows share a box and a reason; the topic-filter test checks both ends too.
- Query counts are flat: the same number of queries with one row and with many, including `meta=1`; `count()` and `attentionCount()` were also compared against the previous implementation before being replaced.
- Archive and restore keep the learner's marks, and the typo rule moves them only when the old item has no other appearance.
- The opened item survives a round trip to a session with its full history (JS test).

---

## 6. Measurements

### 6.1 Seeded dataset

| Item               | Count                                  |
| ------------------ | -------------------------------------- |
| Sessions           | 678 total, 576 finished and not archived |
| Topics             | 16                                     |
| Vocabulary         | 4,509 rows, 2,979 items                |
| Phrases            | 4,486 rows, 2,994 items                |
| Grammar notes      | 2,679 rows, 2,679 items                |
| Mistakes           | 4,662 rows, 2,746 items                |
| Marks              | 138 (89 starred, 44 learned, 62 still difficult, 65 with notes) |

### 6.2 Response time and payload (median of 9 interleaved rounds, local)

| Route                        | Median | Range       | Queries | HTML/JSON size |
| ---------------------------- | ------ | ----------- | ------- | -------------- |
| `/collections` (Vocabulary)  | 411 ms | 351–467 ms  | 16      | 26,230 B       |
| `?tab=phrases`               | 402 ms | 347–516 ms  | 16      | 26,823 B       |
| `?tab=grammar`               | 303 ms | 249–406 ms  | 15      | 21,351 B       |
| `?tab=mistakes`              | 1,020 ms | 892–1,881 ms | 16    | 31,760 B       |
| `?q=a` (search)              | 421 ms | 330–573 ms  | 16      | 26,249 B       |
| `?starred=1`                 | 501 ms | 422–745 ms  | 16      | 16,780 B       |
| `?status=learned`            | 429 ms | 364–514 ms  | 16      | 8,978 B        |
| `?topics=1`                  | 688 ms | 569–946 ms  | 16      | 26,327 B       |
| `?attention=1`               | 428 ms | 351–567 ms  | 16      | 19,378 B       |
| `?sort=az`                   | 406 ms | 377–494 ms  | 16      | 27,086 B       |
| `?sort=newest`               | 420 ms | 359–519 ms  | 16      | 26,257 B       |
| mistakes `?sort=most_seen`    | 917 ms | 768–1,465 ms | 16     | 31,775 B       |
| mistakes Load more (JSON)     | 299 ms | 214–382 ms  | 10      | 28,523 B       |
| open a word (HTML)            | 486 ms | 395–958 ms  | 21      | 26,991 B       |
| open a mistake (HTML)         | 1,231 ms | 1,084–2,521 ms | 21   | 150,852 B      |
| meta refresh (JSON)           | 792 ms | 726–1,243 ms | 17      | 28,589 B       |
| `/sessions/2` (reading page)  | 79 ms  | 59–105 ms   | 6       | 4,697 B        |
| `/` (Home)                    | 410 ms | 363–555 ms  | 21      | 3,479 B        |

Mark actions, measured inside a transaction that was rolled back: star + status **8.7–13.2 ms / 2 queries**; save notes **2.5–4.3 ms / 2 queries**.

### 6.3 Before and after the list-payload fix

| Measurement                        | Before        | After          |
| ---------------------------------- | ------------- | -------------- |
| Mistakes list JSON (30 items)      | 834,251 B     | 24–32 KB       |
| Mistakes tab response              | 5–9.5 s       | 0.6–1.0 s      |
| Appearances sent on a 30-item page | 1,858         | 30             |
| Opened mistake (265 sightings)     | 194,193 B / ~1.5 s | 150,852 B / ~1.2 s |

The list is now bounded by the page (30 newest appearances), not by how often each item was seen. The opened panel is bounded by sightings and distinct reasons, which is inherent to what it must show.

### 6.4 What the numbers mean

- Query counts do not grow with the number of items or appearances on a page (16 for a list, 10 for Load more, 21 with an opened item). They are asserted in tests, not only observed.
- The mistakes tab is the slowest because it groups 2,746 mistake items and computes the "needs attention" count for them; vocabulary and phrases stay around 0.4 s with 3,000 items each.
- Home and a plain session page set the local baseline at roughly 0.1–0.4 s including framework and Inertia rendering, so the Collections work is a few hundred milliseconds on top of that.

---

## 7. Open questions

1. Should the opened mistake panel paginate its "Seen in" list, or send a compact sighting list plus a separate reasons list (3.1)?
2. Should star and status be written per field, or keep the full-state write with an explicit conflict message (3.2)?
3. Is a short-lived cache for tab counts and the attention count acceptable if it is invalidated on every session and mark change (3.3)?
4. Is the 44 px touch goal worth an app-wide sizing pass, or is WCAG's 24 px enough for this dense personal app (3.4)?
5. Is single-learner scope final, or will Collections ever be hosted for more than one person (3.7)?

---

## 8. Suggested next steps

1. **Finish the opened mistake payload** — paginate or compact the sighting list, then re-measure the heaviest item (3.1).
2. **Decide the mark-conflict rule** before Review reads stars and statuses, so two features never disagree about who last wrote a mark (3.2).
3. **One manual accessibility and device pass** — Chrome at 375 px, one phone, and NVDA or VoiceOver; this closes the largest evidence gap (3.5).
4. **Re-measure on the production build** so the timings quoted to the learner are not dev-server numbers (3.6).
5. **Consider caching the meta counts** only if sessions grow well past the current seed, and only with explicit invalidation (3.3).
6. **Review the branch** — `118c79f` holds the Step 6 polish, `701d032` the performance and test work, and the report its own commit; unrelated F4 work remains uncommitted.

---

_No product documents were edited. The work is committed in logical conventional commits, without any new dependency or feature._

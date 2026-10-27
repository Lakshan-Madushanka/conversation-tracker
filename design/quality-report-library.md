# Library of Saved Sessions — Step 7 Quality Report

| Item             | Detail                                                                                                                                                                                                                                                       |
| ---------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Feature          | F2 Library of saved sessions (browse, filter, search, archive, restore, delete, bulk actions)                                                                                                                                                                |
| Date             | 27 October 2026                                                                                                                                                                                                                                              |
| Environment      | Windows, Chrome via the browser tools, local Laravel app; Laravel 13, PHP 8.5, Inertia 3, Vue 3.5, Tailwind 4, shadcn-vue, SQLite                                                                                                                            |
| Sources of truth | `design/spec.md`, `design/features.md`, `design/feature-F2 Library of saved sessions.md`, `design/quality-report-practice-sessions.md`                                                                                                                        |
| Dataset          | `LibrarySeeder`: 527 sessions (12 archived, 515 visible), 4,522 feedback entries                                                                                                                                                                             |
| Result           | The Library journey works end to end with no functional regressions. Seven small defects were fixed. Larger gaps, touch-target sizes, and product decisions that differ from the documents are reported in sections 3 and 4 rather than hidden.            |
| Working tree     | All changes are uncommitted. No product document was edited, no dependency was added, and no feature was added.                                                                                                                                               |

**Verdict in one paragraph:** The Library is ready for personal, local use. With 527 sessions it answers in about a fifth of a second, its query counts do not grow with the number of sessions or the size of a selection, and every destructive action is either confirmed or undoable. The work in this step was polish: one screen-reader announcer instead of two, focus that stays near the work after every archive, restore and delete, a stale topic filter that now disappears when its last session is deleted, a delete warning that names the right day, restore-specific wording in the Archived view, a theme crash when storage is blocked, and a Library link that is always reachable from Home. What remains is mostly outside F2: Home statistics and Today's review still use sample data (F4/F5), and several touch targets are below the project's 44 px goal even though they meet WCAG 2.2 AA.

---

## 1. Summary

### 1.1 What was in scope

- The Library page (`/sessions`) and its Archived view, including search, topic/date/content filters, sorting, cursor pagination, selection, bulk actions and the delete warning.
- The session reading page (`/sessions/{id}`) where archive, restore and delete also live.
- Home's recent-session list and the way into the Library.
- Consistency, accessibility, responsiveness, light/dark mode, speed, reliability and data safety for those screens.

### 1.2 What was out of scope

- Collections (F3), the real review engine (F4), progress calculations beyond the delete preview (F5), import/export (F6/F7).
- Multi-user accounts and authorisation, because this is a single-learner app with no `user_id` anywhere in the schema.

### 1.3 Journeys walked

All four journeys were walked in Chrome against the seeded 527-session database, on desktop and, where noted, at phone width.

**Journey A — tidying one session.**

- Desktop: opened session 526, archived it from the card menu. The reading page swapped to the archived banner, Edit disappeared, Restore appeared, focus landed on `session-heading`, and the toast offered Undo. Undo restored it; Edit came back and focus returned to the heading.
- The delete warning named the session's day ("from Oct 27"), listed its counts, and showed the progress effect (Words 791 → 790, Phrases 764 → 763, Mistakes 767 → 765).
- Phone (375 px iframe): the reading page had no sideways scroll; archive → banner → restore worked; the warning dialog measured 304 × 698 px and fitted without horizontal scroll; at 200 % root font size there was still no sideways scroll.

**Journey B — tidying several sessions.**

- Selected two finished sessions: the bar said "2 selected" and "All of these finished sessions can be archived." Archive removed both, the tab became Archived (14), the list became 513, the toast said "2 sessions archived" with Undo, and focus landed on the card that took their place.
- In the Archived view, a targeted search plus selection showed restore-specific copy, and restoring returned both sessions. Baseline was restored to 515 visible / 12 archived afterwards.
- With `status=unfinished`, "Select all" selected 76 sessions, Archive stayed disabled, and the bar explained "Unfinished sessions cannot be archived."

**Journey C — two tabs and a slow connection.**

- A delete warning was opened in tab 1, then the same session was archived in tab 2. Confirming in tab 1 was refused with "This session changed since you opened the warning… The numbers below are fresh." Nothing was deleted, and the session was restored afterwards.

**Journey D — progress numbers.**

- The delete preview's before/after totals decreased by exactly the session's contents (5 words, 2 phrases, 4 mistakes, 1 grammar note), matching `ProgressCalculator`. Progress totals include archived sessions, by design.

### 1.4 Method and confidence

| Area                        | Method                                                                                 | Confidence                                                  |
| --------------------------- | -------------------------------------------------------------------------------------- | ----------------------------------------------------------- |
| End-to-end journeys         | Real Chrome, DOM assertions, network inspection                                        | High for Chrome                                             |
| Accessibility structure     | Accessibility-tree snapshots, DOM measurement, source review                           | Medium-high; no real assistive technology                    |
| Keyboard                    | Focus assertions in tests and live DOM focus checks after actions                      | Medium; live Tab traversal could not be driven (see 3.5)    |
| Responsive layout           | Fixed-width same-origin iframes at 375 px, 768 px and desktop; 200 % root font size    | Medium-high; not real devices                                |
| Light/dark                  | Live toggle on the reading page at phone width; computed colours checked               | Medium-high                                                  |
| Speed                       | Five timed requests per route, plus `DB::getQueryLog()` query counts                   | High for this machine, local dev server                      |
| Data safety                 | Two-tab stale-warning test, repeat-delete tests, ownership checks, SQL cross-checks    | High for this local database                                 |

**Tested** means executed in the browser or by an automated test. **Reviewed** means read and reasoned about. **Not verified** means there is no evidence.

---

## 2. Fixes made (by area)

### 2.1 Screen-reader announcements

- **Problem:** two live regions announced the same list change, so a filter or search could be spoken twice. Unfiltered lists announced nothing at all, and loading another page announced a fresh count on every page.
- **Change:** `resources/js/pages/sessions/Index.vue` no longer has its own result live region; `resources/js/components/LibrarySessionList.vue` is the single announcer. It now says "N sessions." for an unfiltered list, "N sessions match." / "No sessions match." when filters are on, and stays quiet while a page is loading.
- **Result:** one calm sentence per settled result, no announcement while scrolling.

### 2.2 Focus after archive, restore and delete

- **Problem:** archiving, restoring or deleting removed the control that was pressed, dropping keyboard focus to the top of the document. Bulk actions always jumped to the first card, even when the work happened far down the list.
- **Change:** `Index.vue` gained `indexOfFirst()` and a single `focusAfterRemoval(index)` used by every single and bulk path (archive, restore, delete, delete-already-done, archive-instead-of-delete). Focus goes to the card that took the removed one's place, the card above it, the last remaining card, or the Library heading when the list is empty. `Show.vue` focuses its heading after archive, restore, undo, and archive-instead-of-delete.
- **Result:** focus stays near the work, and a screen reader lands on the new state rather than the page top.

### 2.3 Stale filters after the last matching session is deleted

- **Problem:** deleting the only session under a topic filter left the topic chip on screen with an empty list, because a refresh kept the client's old filter state.
- **Change:** `useLibrarySessions.refresh()` now adopts the filters, topics and time zone the server returns, so a filter the server can no longer honour disappears with its chip.
- **Result:** chips, counts and list always agree after a delete.

### 2.4 Delete warning named the wrong day

- **Problem:** the warning said "from {finished_at}", so a session recorded on one day and finished later named the finish day.
- **Change:** `SessionEditorResource` and `PracticeSessionData` now carry `session_date` (the day the practice happened), and `Show.vue` passes it to `SessionDeleteDialog`.
- **Result:** the warning names the day the learner would recognise. Progress days still follow the finished day, per the design decision.

### 2.5 Archived-view selection wording

- **Problem:** the selection bar told the learner "This finished session can be archived." while they were looking at the Archived view and the button said Restore.
- **Change:** `restoreAvailabilityLabel()` in `resources/js/lib/session-selection.ts` gives "This session can be restored." / "All of these sessions can be restored."; `LibrarySelectionBar.vue` chooses it when `archivedView` is true.
- **Result:** the bar describes the action actually on offer.

### 2.6 Theme choice crashed when storage is blocked

- **Problem:** `useAppearance.ts` read `localStorage` unguarded. In an embedded page or a browser with storage blocked this threw `SecurityError` and the page never mounted (reproduced in the iframe probes; the page rendered the Inertia JSON only).
- **Change:** the read and write are wrapped in `try`/`catch`; the system preference is the fallback and the choice still applies for the current page when it cannot be stored.
- **Result:** the Library and reading page now render in storage-blocked contexts where they previously stopped at the Inertia JSON, and the theme toggle still works.

### 2.7 Home hid the way into the Library

- **Problem:** the "Recent sessions" block, and with it "View all sessions", only existed once something was finished, so a new learner had no direct path to the Library.
- **Change:** `Home.vue` always shows the heading and link, and shows "Finished sessions will appear here once you complete one." when the list is empty.
- **Result:** the Library is always one step from Home, with a friendly empty state.

### 2.8 Test coverage for the gaps

| File                                                       | Added                                                                 |
| ---------------------------------------------------------- | --------------------------------------------------------------------- |
| `resources/js/pages/__tests__/Home.test.ts`                 | Link always present; empty message; cards listed when present         |
| `resources/js/pages/__tests__/Show.test.ts`                 | Delete dialog receives the practice day; heading focused after restore |
| `resources/js/composables/__tests__/useLibrarySessions.test.ts` | Server-tidied topic filter is adopted after a refresh              |
| `resources/js/composables/__tests__/useAppearance.test.ts`  | Blocked storage read falls back to system; blocked write does not throw |
| `resources/js/lib/__tests__/session-selection.test.ts`      | Restore availability wording                                          |
| `resources/js/pages/__tests__/LibraryDelete.test.ts`        | Refresh stub now returns the Archived view, matching the real server  |
| Fixtures in `FinishSessionSummary`, `SaveNotices`, `Create` | `session_date` added to match the resource                            |

No test was deleted or weakened.

---

## 3. Issues not fixed

| #   | Severity | Issue                                                                                                                                                                                                                                                                       | Recommendation                                                                                                                                                    |
| --- | -------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 3.1 | Medium   | Several mobile touch targets are below the project's 44 px goal: theme toggle 28 × 28, page-header buttons 36 px high, Library/Archived tabs 25 px, "Restore session" 32 px, "Back to Library" 20 px, bottom-nav "More" 34 px wide. All meet WCAG 2.2 AA's 24 px minimum (the inline links qualify for the inline exception). | Fix once across the app, not just here: raise `Button` `sm`/`icon-sm` and `TabsTrigger` heights at the mobile breakpoint. Changing them only on the Library would make the app inconsistent. |
| 3.2 | Low      | Desktop controls stay at about 32 px (`md:h-8`) rather than 44 px.                                                                                                                                                                                                          | Keep: 44 px targets on a mouse-driven desktop make dense lists feel clumsy. Record it as a deliberate choice.                                                    |
| 3.3 | High\*   | Home statistics and Today's review still render `@/lib/sample-data`.                                                                                                                                                                                                        | Out of F2 scope. Wire them to real queries in F4/F5; until then they must not be presented as real numbers. (\*High for the product, none for the Library.)       |
| 3.4 | Low      | There is no authentication and no `user_id`; every session belongs to the single learner. A guessed id reaches that learner's own data only.                                                                                                                                 | Acceptable for a local single-learner app. If it is ever hosted or shared, add user scoping and per-user queries before exposing it.                              |
| 3.5 | Medium   | Keyboard-only traversal could not be driven live: the browser tool's `press` did not deliver Tab to the Review-pane page (`activeElement` stayed `BODY`). Focus targets are covered by tests and DOM assertions, but real Tab order is not.                                  | Re-run a manual Tab/Shift+Tab pass, plus one screen-reader pass (NVDA or VoiceOver), before calling accessibility fully verified.                                   |
| 3.6 | Low      | `Index.vue` keeps the last selection announcement ("2 selected.") in its live region after selection ends. The text does not change, so screen readers do not re-announce it.                                                                                                | Cosmetic. Clear the string when selection ends if the region is ever reused for something else.                                                                   |
| 3.7 | Low      | Timings were measured against the local dev server with Vite dev, not the production build, so they are an upper bound.                                                                                                                                                      | Re-measure on the built app before quoting numbers to a user.                                                                                                     |
| 3.8 | Medium   | No real devices, no Firefox/Safari, no real screen reader were available.                                                                                                                                                                                                   | Covered by iframes and the accessibility tree only; a device pass remains.                                                                                        |

---

## 4. Differences from the documents (including decisions to verify)

The rows below cover every known decision to verify from this step: the ones taken while implementing the Library that the documents either do not state or state differently, plus the behaviour changes made during this polish pass.

| #   | Decision taken                                                                                                  | What the documents say / imply                                                                                   | Why                                                                                                                              | Action for the product owner                                                                                     |
| --- | --------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| 4.1 | A direct URL to an archived session shows its reading page with the archived banner, not a 404.                  | Archived sessions are "put away" and listed only in the Archived view; nothing states what a direct link does.    | The learner may have the link in history; showing read-only content with a Restore button is kinder than a dead end.               | Confirm. If 404 is preferred, it is a small controller change.                                                   |
| 4.2 | Home session cards have no action menu (`:show-menu="false"`).                                                   | Home lists recent sessions; the Library is where sessions are managed.                                            | Avoids duplicating archive/delete in two places and keeps the Home card a simple link.                                            | Confirm the Library is the only place for card actions.                                                          |
| 4.3 | Archived sessions count in progress totals, practice days and streaks.                                           | Explicitly decided in the F2 document.                                                                           | Archiving must never change the learner's history.                                                                                | Already agreed; kept and re-verified (Journey D).                                                                |
| 4.4 | The delete warning names the session's practice day (`session_date`), while progress days use the finished day.   | Cards show the session date; the practice day that counts for progress is the day the session was finished.       | The warning identifies the session the learner recognises; the progress rule is untouched.                                        | Confirm the two dates are meant to differ in this warning.                                                       |
| 4.5 | Unfinished sessions cannot be archived; "Select all" can select them but Archive stays disabled with an explanation. | Explicitly decided for archiving; bulk selection rules are not spelled out.                                  | Better to select and explain than to silently drop them from the selection.                                                       | Confirm the disabled-with-explanation pattern is preferred to filtering them out.                                 |
| 4.6 | The Archived view's selection bar talks about restoring, not archiving.                                          | The bar's archive wording is described for the Library.                                                          | The button on offer is Restore; archive wording there was simply wrong.                                                           | None expected; noting the copy change.                                                                           |
| 4.7 | Desktop controls stay around 32 px; mobile controls vary between 25 and 44 px.                                    | The project goal is 44 px touch targets; WCAG 2.2 AA requires 24 px.                                             | Shared shadcn-vue components are sized for desktop density; resizing them only here would break consistency.                      | Decide whether to do an app-wide mobile sizing pass (see 3.1).                                                   |
| 4.8 | "Ownership" means every session belongs to the one learner; no `user_id` is added.                               | Step 7 asks that a learner can open only their own sessions and a guessed address never shows anyone else's.      | The app has no accounts; adding multi-tenancy would be a feature, not polish.                                                      | Confirm single-learner scope, or schedule user scoping before any hosted use.                                     |
| 4.9 | Home's statistics and Today's review remain sample data.                                                          | F2 covers the Library; F4/F5 own those numbers.                                                                   | Out of scope for this step.                                                                                                       | Do not present them as real until F4/F5 land.                                                                     |

---

## 5. Test results

| Check                                  | Command                                    | Result                                                       |
| -------------------------------------- | ------------------------------------------ | ------------------------------------------------------------ |
| Rector                                 | `composer rector`                          | Passed, 0 files changed                                      |
| Pint                                   | `vendor/bin/pint --dirty --format agent`   | Passed                                                       |
| PHPStan                                | `composer types:check`                     | Passed, 0 errors                                             |
| PHP test suite                         | `php artisan test --compact`               | **309 passed, 1,646 assertions**, 11.8 s                     |
| Frontend format and lint               | `npm run check`                            | Passed, 159 files formatted, 147 linted, no warnings         |
| TypeScript                             | `npm run types:check`                      | Passed                                                       |
| JavaScript test suite                  | `npm run test`                             | **579 passed, 48 files**, 51.5 s                             |
| Production build                       | `npm run build`                            | Passed, 3,583 modules, 4.7 s                                 |

Build output after the final changes:

- `app` JS: 238.97 kB (82.65 kB gzip).
- Library `Index` chunk: 145.52 kB (40.27 kB gzip).
- `Create` chunk: 461.09 kB (143.19 kB gzip).
- `app` CSS: 138.56 kB (22.41 kB gzip).

Regression coverage that matters for this step:

- Query counts are asserted flat: the Library list runs the same number of queries with 1 and with 20 sessions; filters and search are flat too; a bulk delete preview for 500 sessions stays under 15 queries; the single delete preview is bounded.
- Delete is transactional and repeat-safe: a second delete reports "already deleted" without a second removal, and a stale token is refused.
- Stale warnings are refused with fresh numbers, and ownership checks confirm a guessed id cannot reach another learner's content (there is only one learner, so the check is structural).

---

## 6. Measurements

### 6.1 Response time (local dev server, five runs each, warm)

| Route                     | Average | Range               | HTML/JSON size |
| ------------------------- | ------- | ------------------- | -------------- |
| `/sessions` (Library)     | 217 ms  | 208–241 ms          | 15,226 B       |
| `/sessions/{id}` (reading) | 201 ms | 165–230 ms          | 10,910 B       |
| `/` (Home)                | 196 ms  | 177–205 ms          | 10,634 B       |
| `/sessions/selection`     | 185 ms  | 165–199 ms          | 3,691 B        |

Library list JSON payload: 5,903 B.

### 6.2 Query counts (527 sessions, 12 archived)

| Request                      | Queries |
| ---------------------------- | ------- |
| Library page (HTML)          | 11      |
| Library page (JSON)          | 11      |
| Selection endpoint           | 6       |
| Reading page                 | 7       |
| Delete preview               | 9       |
| Home                         | 8       |

None of these grow with the number of sessions on the page or in the selection; the flatness is enforced by tests rather than only observed.

### 6.3 Layout

- 375 px: `scrollWidth` 375 (no sideways scroll) on the Library, reading page and delete dialog.
- 768 px: `scrollWidth` 753 (no sideways scroll); sidebar navigation replaces the bottom bar.
- 200 % root font size at 375 px: `scrollWidth` 362, still no sideways scroll.
- Dark mode: `html.dark` applied, background `oklch(0.145 0 0)`, no sideways scroll.

---

## 7. Open questions

1. Should a direct link to an archived session show the read-only page (current behaviour) or a 404 (4.1)?
2. Should the delete warning use the practice day (current) or the finish day (4.4)?
3. Is the 44 px mobile target goal worth an app-wide component pass, or is WCAG's 24 px enough for this personal app (3.1)?
4. When "Select all" includes unfinished sessions, is a disabled Archive with an explanation better than excluding them (4.5)?
5. Is single-learner scope final, or will this ever be hosted for more than one person (3.4)?

---

## 8. Suggested next steps

1. **Manual accessibility pass** — Tab/Shift+Tab through the Library and reading page with a real keyboard, then one screen-reader pass (NVDA or VoiceOver). This closes the largest evidence gap (3.5).
2. **Mobile sizing decision** — either accept WCAG 2.2 AA and record it, or schedule one cross-app pass on `Button` and `TabsTrigger` heights (3.1).
3. **Re-measure on the production build** so the timings quoted to users are not dev-server numbers (3.7).
4. **Resolve the four product decisions** in section 7 before the feature is described as final; each is a small, isolated change.
5. **F4/F5** — replace Home's sample statistics and Today's review with real queries, so no screen shows invented numbers (3.3).
6. **Commit the work** in logical conventional commits: screen-reader announcements; focus after actions; stale-filter refresh; delete-warning date; archived-view wording; theme storage guard; Home Library link; tests.

---

*No product documents were edited. All changes are uncommitted in the working tree.*

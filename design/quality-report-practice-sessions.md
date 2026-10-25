# Practice Sessions — Step 8 Quality Report

| Item | Detail |
| --- | --- |
| Feature | F1 Practice sessions (copy and paste entry) |
| Date | 25 October 2026 |
| Environment | Windows, Chrome via the browser tools, local Laravel app; Laravel 13.36, PHP 8.5, Inertia 3, Vue 3.5, Tailwind 4, Tiptap 3, SQLite |
| Sources of truth | `design/spec.md`, `design/features.md`, `design/feature-Practice sessions (copy and paste entry).md` |
| Result | The F1 journey works end to end. No functional regressions were found. Automated checks, Lighthouse and data-integrity checks pass. Small defects were fixed. Larger gaps and design differences are reported in section 8, not hidden. |
| Working tree | All changes are uncommitted. `.env` is changed locally but is gitignored; `.env.example` is tracked. |

**Verdict in one paragraph:** The practice-session feature is ready for personal, local use on Chrome, with the caveats in section 8. The recording journey, autosave, finishing, reading, editing, guided mode and collections all work. The main remaining risks are not in the recording flow itself: several other screens (Library search, Collections star/status, Review, Progress, Home statistics, Import and Export) still show placeholder or sample data, and Export currently reports success without producing a file. Those must not be presented as finished features until they are wired up.

---

## 1. Scope, method and evidence

### 1.1 What was in scope

- The F1 feature and the screens it directly feeds:
  - Home entry points and the continue card.
  - Library list and session reading page.
  - New/Edit session, normal and guided modes.
  - Finish summary, empty-session warning and discard.
  - Collections, where saved feedback appears.
- The 12 application routes plus the 404 page:
  - `/`, `/sessions`, `/sessions/create`, `/sessions/{id}/edit`, `/sessions/{id}`
  - `/collections`, `/review`, `/review/session`, `/progress`
  - `/settings`, `/settings/import`, `/settings/export`
- Consistency, accessibility, responsiveness, light/dark mode, speed, reliability and data safety for those screens.

### 1.2 What was explicitly out of scope

The following are later features or placeholders. They were inspected only enough to judge whether F1's promises were affected; they were not implemented:

- Library search, topic/date/content filters, archive and delete (F2).
- Collections starring, personal notes and learned/difficult status (F3).
- The real review engine and real progress calculations (F4/F5).
- Real spreadsheet import and export (F6/F7).

### 1.3 How evidence was gathered

| Area | Method | Confidence |
| --- | --- | --- |
| End-to-end journey | Real Chrome session against `http://localhost:8000`, with DOM assertions, network inspection and screenshots | High for Chrome |
| Page titles and meta description | `document.title`, DOM inspection and a route sweep | High |
| Accessibility | Lighthouse (accessibility, best practices, SEO), plus DOM and source review | Medium-high; no real assistive technology |
| Responsive layout | Same-origin iframe probes at 375 px and 320 px, checking `scrollWidth` vs `clientWidth` and overflowing descendants | Medium-high; not real devices |
| Light/dark mode | Lighthouse runs in both themes plus visual checks | Medium-high |
| Keyboard and focus | Source review of labels, focus rings, dialogs, shortcuts and focus targets | Medium; Tab simulation did not move focus in this environment |
| Screen reader | Not run | Not verified |
| Safari, Firefox, iOS Safari, Android Chrome | Not run | Not verified |
| Human timing | Not measured with a stopwatch or a person | Not verified |
| Autosave failure, retry and two-tab conflict | Unit tests plus code review | Medium; no live network failure or two-tab reproduction in the browser |
| Data integrity | Read-only SQL against the local SQLite database | High for this local database |

**Tested** below means executed in the browser or by an automated test. **Reviewed** means read and reasoned about, without executing that exact interaction. **Not verified** means there is no evidence.

### 1.4 Baseline and final automated evidence

| Check | Before Step 8 | Final |
| --- | --- | --- |
| PHP tests | 201 tests, 1,016 assertions | 201 tests, 1,017 assertions |
| JS tests | 310 tests, 24 files | 316 tests, 24 files |
| Rector | Passed | Passed, 0 files changed |
| Pint | Passed | Passed |
| PHPStan (`composer types:check`) | Passed | Passed |
| Frontend format/lint (`npm run check`) | Passed | Passed, 109 files formatted, 99 linted, no warnings |
| TypeScript (`npm run types:check`) | Passed | Passed |
| Production build | Passed | Passed, 3,414 modules |
| Lighthouse | 1.0 accessibility / 1.0 best practices / 1.0 SEO | 1.0 / 1.0 / 1.0 with zero failures, including a final re-run on Collections after the last edits |

Build output after the final changes:

- `Create` chunk: 462.56 kB (143.52 kB gzip).
- Main app chunk: 238.42 kB (82.39 kB gzip).
- CSS: 114.08 kB (18.85 kB gzip).

No new runtime or development dependency was added. `package.json` was not changed.

---

## 2. End-to-end journey

### 2.1 Scenario walked

A five-question session was created through the real UI, with every feedback type used at least once, then finished, read back, edited, found in the library and checked in collections.

1. Opened a new session from the app.
2. Chose a topic and entered five questions and answers.
3. Added mistakes, vocabulary, phrases, a grammar note and a natural version to the questions.
4. Watched autosave create the session and move the page to its edit address without a reload.
5. Opened the previously crashing legacy example row (`menu` / “Could I see the menu?”) and confirmed it opens safely as an editable list entry.
6. Checked duplicate detection, removal and undo, and the “needs attention” messages.
7. Finished the session and read the summary: 5 questions, 2 words, 1 phrase, 1 mistake, 1 note.
8. Read the session back at `/sessions/7`.
9. Confirmed the session appears newest-first in the Library.
10. Confirmed its vocabulary, phrase and mistake appear in Collections, and that searching for `strong` found `brew` through its example.
11. Reopened the finished session and confirmed editing still autosaves.
12. Walked guided mode (`?guided=1&question=1&step=question`) through question, answer and review steps, including Next, Back, Change, Add another question and Finish.
13. Tried to finish an empty session and confirmed the warning offers “Keep editing” and “Discard session”.

All 13 steps passed on the fixed code. The autosave payload contained all five questions and their list examples.

### 2.2 Step 8 acceptance checks

| Step 8 test | Status | Evidence |
| --- | --- | --- |
| Five questions with all feedback types recorded in a few minutes | Partial | The full flow completed in one browser sitting; no human stopwatch test was run. Autosave waits 1.5 s after a pause and never reloads the page, so the mechanical path is fast. |
| Complete flow: new, add questions, paste feedback, finish, library, collections | Pass | Browser walkthrough above; session 7 in the local database. |
| Text readable, buttons easy to tap, keyboard navigation and focus indicators | Pass with caveat | Lighthouse accessibility 1.0, contrast passes, target sizes pass, labels and focus rings reviewed. Real Tab traversal and a screen reader were not available. |
| Encouraging wording; mistakes as learning opportunities | Pass | “Mistakes and corrections”, “Learning opportunities”, “Your common mistakes are fading”, “Nice work!”, “Keep it going!”. No blaming language. |
| Friendly empty, loading and error states | Pass | Library empty state, Collections empty state and spinner, finish warning, save-failure notices, 404 page. |
| Light and dark modes on every screen, including toolbar and formatted text | Pass with caveat | Lighthouse in both themes; visual checks of editor, reading and collections. No pixel-diff visual regression suite. |
| Formatted text in reading, collections and review | Partial | Reading and Collections tested. Review still uses sample data, so real session formatting is not shown there yet. |
| No sideways scrolling at phone width | Pass | 375 px and 320 px probes across all pages; only Import’s table and Settings’ tab strip scroll inside their own containers. |
| Every destructive action confirms or can be undone | Pass | Question with content: confirmation dialog. Empty question: immediate. Entries: undo notice and Ctrl/Cmd+Z. Empty session: discard confirmation. |

### 2.3 Timing honesty

The “few minutes” success measure was **not measured with a person or a stopwatch**. The browser walkthrough was performed by an agent and included screenshots, DOM checks and deliberate pauses, so its wall-clock time is not a valid human measure. What can be said is:

- There are no page reloads in the recording flow.
- Saving is automatic after a 1.5 s pause.
- Questions and feedback are pasted, not typed character by character.
- The editor keeps all five questions on one page, with long sessions collapsing cards.

A single timed run by a real learner on a phone is still recommended before calling the five-minute measure proven.

---

## 3. Consistency pass

### 3.1 What was checked

- Page titles and the title format.
- Product name in the logo, header and browser title.
- Terminology for sessions, questions and the five feedback boxes.
- Button labels and capitalization.
- Date and count formatting.
- Icon use and decorative-icon hiding.
- Empty-state wording and next actions.
- Spelling variants (British vs American English).
- Dead code, stale comments and redundant conditions.

### 3.2 Fixed in this pass

| Issue | Fix |
| --- | --- |
| Most pages had no document title, so browser tabs and history showed only the app name | Added `<Head>` to Home, Library, New/Edit session, Collections, Review, Review session, Progress, Preferences, Import, Export and 404. Titles follow `Page - App`. The reading page uses the topic name. |
| No global meta description | Added a description to `resources/views/app.blade.php`. |
| App name was still `Laravel` in the environment files | Set `APP_NAME="Spoken English Companion"` in `.env` and `.env.example`; `VITE_APP_NAME` inherits it, so titles and the logo now agree. |
| Mixed British/American spelling: “Re-practise”, “nudge to practise” | Changed to “Re-practice” and “practice”, matching the design documents and the rest of the app. |
| The Collections search box had only a placeholder | Added an explicit `aria-label` that follows the active tab. |
| Save icon in the list editor was the wrong icon | Switched to the check icon, matching the table editor’s “done” action. |
| Reading page did not show vocabulary/phrase examples saved as lists | Flattened list examples in `reading.ts`; the reading page now shows every example. |
| Example column in the feedback table crashed on legacy string examples | Added `exampleParts`/`exampleEntries` helpers and a safe string/list rendering path. |
| Collections showed only legacy string examples | Added formatted list rendering and a plain-string fallback. Mistake corrections now keep their formatting too. |
| Reading page’s inline details ran together for screen readers | Added screen-reader-only separators. |
| Stale `FeedbackBox` docblock and a redundant `v-if` in `RichTextEditor` | Removed. |

### 3.3 Remaining consistency notes

- The design spec calls the product “Spoken English Practice Companion”; the app and logo say “Spoken English Companion”. This is a deliberate shorter brand, but it is still a difference from the spec.
- “Finish session” is the button; the summary says “Your practice is saved”. “Finish” and “saved” are used for different ideas, which is reasonable, but a single glossary would help future copy.
- The Collections empty state uses an exclamation mark (“No vocabulary found!”), while the rest of the app is calmer. It is friendly enough, but “No vocabulary yet” would match the tone better.
- The Library only lists finished sessions. Unfinished sessions live on Home, and only the most recent one is shown. This is a discoverability gap, not just a wording issue; see section 8.

---

## 4. Accessibility (WCAG 2.2 AA)

### 4.1 Automated result

Lighthouse reported **1.0 Accessibility, 1.0 Best Practices and 1.0 SEO with zero failures** across the feature routes in both light and dark themes, including the 404 and review-session pages. A final re-run on Collections after the last edits also returned 1.0/1.0/1.0 with no failures.

This covers the machine-checkable part of WCAG 2.2 AA: accessible names, contrast, heading order, landmarks, language, link names, form labels, target size, and obvious ARIA misuse.

### 4.2 What was fixed

| Area | Fix |
| --- | --- |
| Page structure | Added a visible-on-focus skip link target (`main` with `tabindex="-1"`), one `h1` per page, and a screen-reader-only `h1` on the review session. |
| Form controls | Named the dark-mode and reminder switches, the level select, the reminder time input and the Import column selects. The reminder time is disabled when reminders are off. |
| Feedback table | Every cell has an accessible name including its column and row; the example column is labelled; edit/done/remove buttons name the row they act on. |
| Error and status messages | Validation and save errors use `role="alert"`; paste, removal and undo use a polite live region; the “needs attention” note is focusable and addressable. |
| Contrast | Darkened the light-theme muted foreground so secondary text passes AA. |
| Motion | Added a global `prefers-reduced-motion` rule that removes non-essential animation. |
| Target size | Enlarged the Collections star hit area with an invisible inset target; icon buttons in the table editor are at least 44 × 44 px. |
| Form semantics | The reading page’s label/value pairs use a definition list; section labels are real text, not color alone. |
| Icons | Decorative icons are `aria-hidden`; icon-only buttons have text alternatives. |

### 4.3 Keyboard and focus review

Reviewed in source and through Lighthouse, but not truly tabbed through in this environment:

- All interactive controls are native buttons, links, inputs, selects or checkboxes, or shadcn-vue components built on them.
- Focus-visible rings are present on links, cards, table controls, the star and the skip target.
- Dialogs use the shared alert-dialog component, which traps focus and restores it.
- Enter confirms a table cell; Escape leaves edit mode; Ctrl/Cmd+Z undoes the last paste or removal while the notice is visible.
- The editor toolbar is reachable by keyboard, and the rich-text fields have labels.
- Focus is moved deliberately after finishing to the first row that still needs attention.

**Caveat:** the browser tool’s Tab simulation did not move focus in this environment, so keyboard order and focus visibility were verified by code review and Lighthouse, not by a real Tab walk. A short manual keyboard pass is still recommended.

### 4.4 Screen-reader status

**Not verified with a real screen reader.** The markup includes labels, landmarks, headings, live regions and focus management, but no NVDA, JAWS, VoiceOver or TalkBack session was run. This is the largest accessibility evidence gap.

---

## 5. Responsive and light/dark pass

### 5.1 Responsive evidence

Because the browser tools do not expose viewport emulation, pages were loaded in same-origin iframes at 375 px and 320 px, hydrated, and measured:

- `document.documentElement.scrollWidth === clientWidth` on every page.
- No descendant overflowed the page box except the Import table and the Settings tab strip, which scroll inside their own containers by design.
- The editor was checked specifically at 375 px: the document was 360 px wide in a 360 px viewport, the description ellipsized, the collapse chevron stayed visible, and no child overflowed.

Fixed in this pass:

- `PageHeader` action rows could overflow on phones because actions were forced not to shrink. They now wrap and can shrink.
- The editor form and question list used an implicit grid track that a wide feedback-table header could size beyond the viewport. Both containers now use `grid-cols-1`, so the track cannot exceed the page.

### 5.2 Light and dark mode

- Lighthouse passed in both themes on every route.
- The editor toolbar, formatted question/answer text, feedback tables, reading page and collections were checked visually in both themes.
- The light-theme muted text contrast fix is the only color change; no dark-mode colors were weakened.
- The theme switch is on Preferences and uses the shared switch component with an explicit label.

### 5.3 Not verified

- Real iOS Safari, Android Chrome, Firefox and Safari.
- Real phone hardware, touch targets under a finger, and on-screen keyboard behavior.
- Pinch zoom and text resizing beyond the browser defaults.
- Landscape orientation on a phone.

The CSS uses mobile-first layouts and the probes found no page-level horizontal scroll, but these remain code-and-emulation findings, not device findings.

---

## 6. Speed, reliability and data safety

### 6.1 Speed

- Production build succeeds: 3,414 modules, `Create` 462.56 kB (143.52 kB gzip), app 238.42 kB (82.39 kB gzip), CSS 114.08 kB (18.85 kB gzip).
- The editor is the heaviest route because it includes Tiptap; it is code-split from the rest of the app.
- Autosave waits 1.5 s after the last change, runs one save at a time, and queues changes made during a save.
- No page reload occurs when the first save creates a session; the address is replaced in place.
- No performance benchmark was run; the build sizes and interaction timings are the only speed evidence.

### 6.2 Reliability

- Autosave retries with growing waits (2 s, 5 s, 10 s, 20 s, 30 s).
- The indicator distinguishes needs-topic, saving, saved, retrying, failed and conflict.
- On failure, the text stays on screen, a message explains what happened, and Retry is offered.
- A second tab that saves first produces a conflict warning with “Load latest” and “Keep mine”, rather than silently overwriting.
- The first save is idempotent through a client key, so a retried create cannot make two sessions.
- The finish action and the empty-session warning were exercised in the browser.
- The 404 page is friendly and returns the learner Home.

**Not verified:** a real lost connection, a server 500 during autosave, or two live tabs. These paths are covered by unit tests and code review, but not by a live failure injection.

### 6.3 Data safety and integrity

Read-only checks against the local SQLite database:

| Check | Result |
| --- | --- |
| Sessions | 7 |
| Questions | 16 |
| Feedback entries | 29 |
| Topics | 6 |
| Orphan questions | 0 |
| Orphan feedback entries | 0 |
| Duplicate question positions in a session | 0 |
| Failed jobs | 0 |
| Questions with blank answers | 8, all in pre-existing seed/sample sessions 1–5; none in the session created during this pass |

Session 7 is the finished five-question session created in the walkthrough. Sessions 2–6 are unfinished and predate this pass.

**Logs:** `storage/logs/laravel.log` was last written on 22 October, before this pass; its entries are stale test-time `AppServiceProvider` exceptions. No new Laravel errors were produced during Step 8. The browser console showed only Vite debug entries. The only network “failure” was the expected 404 document for `/missing-page`.

### 6.4 Data-safety caveats that matter

- **Export is not a backup yet.** The Download button shows a success toast but does not create a file, and “Last backup: 12 Oct” is hardcoded. Do not rely on it.
- **Import is not wired up.** It shows a sample preview and does not persist anything.
- **Unfinished sessions are only partly discoverable.** Home shows the most recent unfinished session; older unfinished sessions are not listed in the Library. They remain in the database and can be opened by direct URL, but a learner cannot find them from the UI.
- **Content typed before a topic is chosen is not saved.** The indicator says a topic is needed, but a refresh at that point loses the pasted questions. This follows “a session must have a topic”, but conflicts with “nothing is lost on refresh”.
- **Table cells are capped at 1,000 characters and 200 rows per box.** A longer single line is kept in the paste box with an explanation, so it is not silently lost, but it cannot be saved as one entry. The design says a very long line should be accepted as one entry.

---

## 7. Regression and automated checks

### 7.1 Commands run

For PHP, in the required order:

1. `composer rector` — passed, 0 files changed.
2. `vendor/bin/pint --dirty --format agent` — passed.
3. `composer types:check` — passed.
4. `php artisan test --compact` — 201 passed, 1,017 assertions.

For the frontend:

1. `npm run check` — 109 files correctly formatted, 99 files linted, no warnings.
2. `npm run types:check` — passed.
3. `npm run test` — 316 passed in 24 files.
4. `npm run build` — passed, 3,414 modules.

The final edits after the last full PHP run were frontend-only, so the PHP checks remain valid. The final frontend checks and tests were re-run after the last edit.

### 7.2 Tests added or strengthened

- Reading page: single markup example and list-of-examples rendering.
- Feedback table: editing a legacy string example, and rendering a formatted list example.
- Collections: formatted list examples, legacy string fallback, and formatted mistake corrections.
- Backend: the plain-text `example` copy used for search is asserted after saving a list example.
- Test setup: a global `Head` mock so pages can mount without Inertia’s head provider.

### 7.3 Main files changed

| Area | Files |
| --- | --- |
| Example normalisation | `resources/js/lib/example.ts` (new), `resources/js/lib/reading.ts`, `resources/js/types/practice.ts` |
| Editor and feedback | `FeedbackBox.vue`, `FeedbackTable.vue`, `EntryList.vue`, `RichTextEditor.vue`, `PageHeader.vue`, `Create.vue` |
| Reading and collections | `sessions/Show.vue`, `collections/Index.vue` |
| Layout and accessibility | `AppLayout.vue`, `Preferences.vue`, `Import.vue`, `review/Session.vue`, `app.css`, `app.blade.php` |
| Titles and copy | Home, Library, Create, Collections, Review, Progress, Preferences, Import, Export, 404 |
| Backend | `app/Support/FeedbackTable.php`, `tests/Feature/PracticeSessionQuestionsTest.php` |
| Test setup | `resources/js/test-setup.ts`, the reading, feedback-table and collections tests |

### 7.4 Regression assessment

- All previously passing PHP tests still pass.
- All previously passing JS tests still pass; six new tests cover the fixes.
- The browser walkthrough found no regression in the recording, finishing, reading, editing, guided or collections flows.
- No earlier behaviour was intentionally weakened. The only behaviour-adjacent changes are the safer legacy-example rendering, the added labels, the disabled reminder time when reminders are off, and the responsive container changes.

---

## 8. Findings, spec differences and limitations

### 8.1 Defects fixed in Step 8

| Severity | Defect | Fix |
| --- | --- | --- |
| High | A legacy string example in a vocabulary/phrase row could crash the feedback table | Safe string/list helpers; string examples open as editable list entries; regression tests |
| High | Reading page hid vocabulary/phrase examples saved as lists | Flattened examples in the reading data; examples now render |
| High | Several pages had no document titles; no meta description | Added per-page `<Head>`, topic title on the reading page, and a global description |
| Medium | Editor could overflow at 375 px because of an implicit grid track | `grid-cols-1` on the form and question list |
| Medium | Page header actions overflowed on phones | Wrapping, shrinkable action row |
| Medium | Several controls had no accessible name | Labels for switches, selects, reminder time and Import mappings |
| Medium | Light-theme muted text failed contrast | Darkened the muted foreground |
| Medium | Collections did not show formatted examples or formatted corrections | Formatted list examples, string fallback, and formatted mistake corrections |
| Medium | Mobile save icon was inconsistent | Check icon in the list editor |
| Low | Reading inline details ran together for screen readers | Screen-reader-only separators |
| Low | Collections search relied on its placeholder | Explicit `aria-label` |
| Low | Star hit area was too small | Invisible inset target |
| Low | No reduced-motion support | Global `prefers-reduced-motion` rule |
| Low | Missing `h1` on the review session | Screen-reader-only `h1` |
| Low | Mixed spelling and stale comments | “practice” spelling; removed dead docblock/redundant condition |

### 8.2 Differences from the design documents

| Area | Design | Implemented | Assessment |
| --- | --- | --- | --- |
| Question count | “Any number of questions” | 50-question cap, enforced in the browser and the request | Deliberate safety limit; document it. The UI explains the limit. |
| Topic editing | Topic name in the top bar, tap to change | Topic field with suggestions at the top of the form | Functionally equivalent; the top-bar label is not editable in place. |
| Guided progress | Progress dots row | Progress bar in the focus layout | Visual difference only; progress is still visible. |
| Guided layout | Distraction-free, no main navigation | Focus layout with no main navigation | Matches. |
| Mistakes box | Mistakes and corrections | Three columns: what I said, correction, optional why; correction is formatted | Richer than the spec, and needed by Collections’ “why”/explanation. |
| Vocabulary and phrases | Plain list entries | Three-column table: item, meaning/use, optional formatted example | Richer than the spec; supports Collections’ meaning and example. |
| List pasting | One line becomes one entry | One line becomes one row; separators split it into columns; a pasted table keeps its columns | Compatible and more forgiving. |
| Legacy examples | Not specified | A string example is shown as one entry; editing it saves a list | Reported limitation: editing a legacy string converts it to a list shape. |
| Library | All sessions, search, filters, archive, delete | Finished sessions only; search and filter controls are inert; no archive/delete | F2 gap. F1 only requires finished sessions to appear, which works. |
| Unfinished sessions | Kept and continuable | Most recent unfinished session on Home; older ones only by direct URL | F1 edge-case gap; see 8.3. |
| Collections | Star, notes, learned/difficult | Star is inert and always shown filled/pressed; “difficult” and “5” badges are hardcoded | F3 gap; must not be presented as working. |
| Review | Real exercises from saved content | Sample review items and ratings | F4 gap; formatting in review is therefore unverified. |
| Progress | Real streak, totals, mistake trend | Sample statistics and chart | F5 gap. |
| Home statistics | Real streak, practice days, today’s review | Sample data | F5 gap. |
| Import | File picker, column matching, preview, persistence | Sample mapping/preview only; no file is read or saved | F6 gap; F1 requirement 24 (imported sessions editable) is unverifiable. |
| Export | Real spreadsheet download and last-backup date | Success toast only; hardcoded “Last backup: 12 Oct” | F7 gap and a data-safety risk. |
| Product name | “Spoken English Practice Companion” | “Spoken English Companion” | Brand shortening; align the documents or the app name. |
| Offline use | Open question, not assumed | Requires the server | Matches the assumption. |
| Markdown conversion | Open question, not assumed | Raw markdown stays plain text | Matches the assumption. |
| Two tabs | Warn rather than silently overwrite | Conflict warning with Load latest / Keep mine | Matches. |
| Very long line | Accepted as one entry | 1,000-character cell cap; text is kept and explained | Deviation; consider raising the cap or documenting it. |

### 8.3 Findings not fixed (out of scope or behaviour-changing)

| Severity | Finding | Why it was not fixed | Recommendation |
| --- | --- | --- | --- |
| Critical for trust | Export shows “Your export is ready” without creating a file, and shows a hardcoded last-backup date | F7 is a placeholder; implementing export is a feature, not polish | Disable or clearly label Export as “coming soon” until it writes a real file. Never imply a backup exists. |
| High | Library search box and filter labels do nothing | F2; wiring search/filters is a feature | Hide or disable them, or implement them before presenting the Library as searchable. |
| High | Collections star button does nothing but is always pressed/filled; “difficult” and “5” badges are hardcoded | F3; starring/status is a feature | Disable and label the star, or implement it. Remove fake counts. |
| High | Home, Review and Progress show sample statistics and review items as if they were the learner’s | F4/F5; real aggregation is a feature | Mark them as sample data or hide them until wired. |
| High | Only the most recent unfinished session is discoverable | Fixing it means listing unfinished sessions in the Library or a dedicated section; a behaviour change | Add unfinished sessions to the Library (or a “Continue” list) before calling unfinished work safely recoverable. |
| Medium-high | Content entered before a topic is chosen is lost on refresh | Topic is required; a local draft or a default topic is a design decision | Add a local draft (for example, localStorage) or create a topic-less draft that can be completed later. |
| Medium | Review uses sample data, so real formatted session content is not exercised there | F4 | Verify formatting in review once the real review engine exists. |
| Medium | Import does not persist anything, so imported sessions cannot be edited “the same way” (requirement 24) | F6 | Implement import before claiming requirement 24. |
| Medium | Library lists finished sessions only; no archive/delete | F2 | Implement archive/delete with confirmation, as the spec requires. |
| Medium | 1,000-character table-cell cap conflicts with “a very long line is accepted” | Safety limit; changing it affects validation and layout | Raise the cap or document the limit in the UI. |
| Low | Legacy string example editing converts the shape to a list | The list shape is the current model; a migration would be needed to preserve the exact shape | Accept and document; or migrate legacy examples on read. |
| Low | Product name differs from the spec | Brand choice | Align the spec title or the app name. |
| Low | Collections empty state is terser and more exclamatory than the rest of the copy | Copy polish | “No vocabulary yet” plus a clear next action. |

### 8.4 Verification limitations

- **No real screen reader.** NVDA, JAWS, VoiceOver and TalkBack were not run.
- **No real cross-browser testing.** Chrome was the only engine. Safari, Firefox, iOS Safari and Android Chrome were not run.
- **No real device testing.** Responsive evidence comes from same-origin iframe probes at 375 px and 320 px, not phones or tablets.
- **No true keyboard walk.** Tab simulation did not move focus in this environment; keyboard behavior is code-reviewed and Lighthouse-checked.
- **No human timing.** The five-minute success measure is not proven by a stopwatch.
- **No live failure injection.** Offline, server errors and two-tab conflicts are unit-tested, not reproduced live.
- **No visual regression suite.** Light/dark and responsive checks are visual and Lighthouse-based, not pixel diffs.
- **No performance benchmark.** Only build size and interaction design are evidence.
- **Local data only.** The database is a local SQLite file with seed/sample data; production data behavior was not tested.
- **Placeholder features were not fixed.** The report distinguishes them from F1 so they are not mistaken for finished work.

### 8.5 Release recommendation

**F1 is ready for personal, local use on Chrome**, provided the learner understands the limitations above. The recording journey, autosave, finishing, reading, editing, guided mode and collections are coherent and tested.

**Do not present F2–F7 as complete.** In particular, do not rely on Export for backups, do not treat the Collections star/status as functional, and do not read the Home/Review/Progress numbers as real statistics. Before a wider release, the highest-value follow-ups are:

1. Make Export write a real file and remove the fake backup date.
2. Make unfinished sessions discoverable.
3. Protect pre-topic work with a local draft.
4. Hide or implement the inert Library and Collections controls.
5. Run one timed human session, one real screen-reader pass, and one real phone/browser pass.

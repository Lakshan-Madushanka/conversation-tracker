# Library of saved sessions

## Summary

The Library is the one place where the learner can find every practice session they have ever recorded. It lists sessions newest first, lets the learner narrow the list by topic, date, content type, and status, and lets them **search for any word, phrase, or mistake ever saved**. From the Library the learner can open a session to read it again, edit it, archive it, or delete it, and can **select several sessions at once** to archive, restore, or delete them together.

Practice is only useful if it can be found again. The Library turns a growing pile of saved conversations into something the learner can browse and search quickly, so they can revisit exactly what they want to practice.

### Assumptions

- The app is for **one learner** (personal use). Everything in the Library belongs to that learner.
- The **Practice sessions** feature already exists. Sessions have a topic, a session date, questions, feedback entries, and a status of **finished** or **unfinished**. Counts of questions, words, phrases, mistakes, and grammar notes are available for each session.
- A **reading page** already exists for a saved session, with an Edit button. Finished sessions open there, and unfinished sessions open directly in the editor (from Practice sessions, Step 6).
- The Home screen already shows a "Continue where you left off" card (unfinished sessions) and a small "Recent sessions" list (finished sessions). The Library does not replace them. It is the complete view behind them.
- The **session date** is the day the practice happened. For sessions recorded in the app this is the day the session was created. Sessions imported from spreadsheets in the future keep their original date where available.
- The Library lists **both finished and unfinished sessions**, each clearly labelled with its status.
- Searching covers **everything saved** in sessions, not just topics: tutor's questions, answers, mistakes, vocabulary, phrases, grammar notes, and natural versions.
- Search ignores capitalization, accents, extra spaces, and formatting.
- **Archiving** is a gentle, reversible way to hide a **finished** session the learner no longer wants to see. **Unfinished sessions cannot be archived.** They can be continued, finished, or deleted. Because a finished session never goes back to unfinished, every archived session is a finished one. **Deleting** is permanent and is available for both finished and unfinished sessions.
- Archived sessions are hidden from the main Library and from the Home lists, and are **not used** by Collections and Review. They stay available in an Archived view and can be restored.
- Archived sessions **still count in Progress totals** (for example total words learned) and are **included in exports**, with their archived status kept. Archiving never removes anything from the learner's history or data.
- **Totals, practice days, and streaks follow the content.** They are worked out from the **finished sessions that exist**, including archived ones, using the day each session was **finished**. Unfinished sessions never count. Archiving or restoring a session changes nothing. **Deleting a finished session lowers the totals by what it contained, and removes its practice day if no other finished session was finished on that day, which can lower the streak.** Deleting an unfinished session changes nothing.
- The practice day that counts for a session is the day it was **finished**, not the day it was started.
- Totals of saved content (such as total words learned, phrases, mistakes, and grammar points) are worked out the same way. They can be higher than what Collections and Review show, because archived sessions count in totals but are hidden from Collections and Review.
- The learner can select **several sessions at once** and archive, restore, or delete them together. Selecting works the same way in the main Library and in the Archived view.
- Selection is cleared whenever the filters or search change, so the learner never acts on sessions they can no longer see.
- The learner needs an internet connection to use the app (offline use is an open question in the spec).

### Dependencies

- **Practice sessions:** the source of all sessions, their statuses, dates, counts, the reading page, and the editor.
- **Base app layout:** the main navigation (with a Library link), the page header, empty-state, and session card pieces.
- **Home screen:** gains a "View all sessions" link, and its lists must stay correct when sessions are archived or deleted.
- **Personal collections and Review:** must exclude archived and deleted sessions. They are built later, but the rule is set here.
- **Progress and motivation:** totals, practice days, and streaks are worked out from the finished sessions that exist, including archived ones. Archiving changes nothing. Deleting a finished session lowers totals and can lower the streak, so Progress must be able to preview the exact effect of a deletion for the delete warning.
- **Import from spreadsheets:** imported sessions must appear in the Library exactly like any other session.
- **Export and backup:** includes archived sessions and keeps their archived status in the exported data.

### Related notes

- **Experience principles that apply:** simple first, calm and encouraging, quick to use on any device, and distraction-free.
- **Spec goal:** keep everything **organized and searchable**. This feature is the main way that goal is met.
- **Success measures that depend on it:** the learner practices review several times a week, and stops needing spreadsheets. Both depend on being able to find past material quickly.
- **User flow in the feature list:** open the Library, see a list of sessions with topic, date, and a short summary, narrow it with filters, search for something specific, open a session, then edit, archive, or delete it (delete asks for confirmation).
- **Screen in the feature list:** a search box and filter chips on top, a list of session cards below showing topic, date, and short counts such as "6 questions · 12 words · 4 mistakes," and a friendly empty state with a "Start your first session" button.
- **Existing visual reference:** the layout mockups show the general look of cards, chips, and the sidebar and bottom navigation. A specific mockup of the Library screen has not been made yet.
- **Not part of this feature:** Collections (browsing individual words, phrases, grammar notes, and mistakes), Review exercises, Import, and Export. The Library is about sessions, with search reaching into their contents.

## Goals

- Let the learner find any past session in a few seconds, however many sessions have been saved.
- Let the learner find any word, phrase, or mistake they have ever saved, and jump straight to it.
- Let the learner narrow the list by topic, date, content type, and status, in any combination.
- Let the learner tidy their sessions safely, one at a time or several at once, with a reversible Archive option and a clearly warned Delete.
- Keep the Library fast, calm, and easy to use on a phone as well as a computer.

## Requirements

### Viewing the Library

1. The Library is reachable from the main navigation and from a "View all sessions" link on the Home screen.
2. The Library lists every session that is not archived, **finished and unfinished**, with the newest session (by session date) first.
3. Each session is shown as a card with its **topic**, **date**, a short summary of **counts** (questions, words, phrases, and mistakes), and a clear **status label** ("Finished" or "Unfinished") shown as text, not by color alone. When a session was last edited on a later day than its session date, the card also shows when it was **last edited** (for example "Edited 2 days ago"), with the exact date available to screen readers and on hover or long press.
4. Counts on each card are always correct, including after the session is edited.
5. The Library shows how many sessions are listed (for example "42 sessions"), and updates this number as filters and search change.
6. Long lists load progressively, so the page stays quick with hundreds of sessions.
7. Opening a card opens the session: a finished session opens on its reading page, and an unfinished session opens in the editor.
8. Returning to the Library from a session brings the learner back to the same place, with the same search, filters, sort, and scroll position as before.
9. If there are no sessions at all, a friendly empty state explains what the Library is for and offers a **Start your first session** button.

### Filtering and sorting

10. The learner can filter by **topic**, choosing one or several of the topics they have used.
11. The learner can filter by **date**, using quick choices (for example today, the last 7 days, the last 30 days) or a custom range. The filter uses the session date.
12. The learner can filter by **content type**: mistakes, vocabulary, phrases, grammar notes, or natural versions. A session matches when it has at least one non-empty entry of the chosen type. Several types can be chosen.
13. The learner can filter by **status**: all, finished, or unfinished.
14. Filters can be combined. Sessions must match every kind of filter chosen (topic, date, type, status), and match any of the topics chosen within the topic filter.
15. Active filters are visible as chips that can be removed one at a time, with a **Clear all** action, so the learner always knows why the list looks the way it does.
16. The learner can sort the list by **newest first** (the default), **oldest first**, or **recently edited**.
17. When no session matches the filters, a friendly message says so and offers to clear them.

### Searching

18. A search box sits at the top of the Library. Results appear as the learner types, after a short pause, and once at least two characters have been entered.
19. Search covers **everything saved in sessions**: topics, tutor's questions, answers, mistakes (what I said, the correction, and the reason), vocabulary (word, meaning, and example), phrases (phrase, meaning or use, and example), grammar notes, and natural versions.
20. Search ignores capitalization, accents (so "cafe" finds "café"), extra spaces, and formatting. It matches parts of words.
21. When several words are typed, all of them must appear together in the same entry or field to match.
22. Results show the **matching sessions**, and beneath each one the **matching items**, each labeled with the section it came from (using the same color tag and text label as elsewhere) and with the matching text highlighted. Highlighting never relies on color alone.
23. Selecting a matching item opens that session and takes the learner directly to that item, which is briefly highlighted.
24. Search works together with the filters. For example, with the content type "mistakes" chosen, only mistakes are searched.
25. A clear button empties the search and returns to the normal list.
26. When nothing matches, a friendly message says so and suggests checking the spelling or removing filters.

### Managing sessions

27. Each session card, and the session's reading page, offers a small menu with **Edit**, **Archive**, and **Delete**. For an **unfinished** session, **Archive** is shown as unavailable with a short explanation (for example "Finish this session to archive it"), while **Edit** and **Delete** work normally.
28. **Edit** opens the session in the editor.
29. **Archive** hides the session from the main Library, from the Home lists, and from Collections and Review. It happens immediately, with a brief message that offers **Undo**. Archive is available **only for finished sessions**. Archiving an unfinished session is not possible.
30. Archived sessions **still count in Progress totals** (for example total words learned) and are **included in exports**, with their archived status kept, so archiving never removes anything from the learner's history or data.
31. Archiving and restoring a session **never change** Progress totals, practice days, or streaks. Practice days come only from finished sessions, and unfinished sessions never count toward them.
32. An **Archived** view lists archived sessions, searchable and filterable in the same way, each with **Restore** and **Delete** actions.
33. **Restore** returns the session to the Library exactly as it was, with its original status, dates, and content.
34. **Delete** permanently removes the session and everything in it, and always asks for confirmation first. The safe choice (Cancel) is the default. What the confirmation says depends on the session's status:
    - **Unfinished session:** a clear confirmation that names the session (topic and date), says how much will be removed, and states that this cannot be undone. It says that progress totals and streaks will not change, because unfinished sessions do not count. Archive is not suggested, because unfinished sessions cannot be archived.
    - **Finished session:** a **prominent warning**, visually distinct from other confirmations, in plain and serious wording (for example "Delete this finished session? This cannot be undone."). It names the session (topic and date), lists exactly what will be permanently removed (questions, words, phrases, mistakes, and grammar notes), and says the session will no longer appear in the Library, Collections, or Review. It shows the **exact effect on the learner's progress**: the totals before and after (for example "Words learned: 180 → 168") and, when it applies, the effect on the practice days and streak (for example "Your streak will drop from 6 days to 2 days"). When nothing changes, it says so plainly. It suggests **Archive** as a gentler, reversible alternative that keeps everything and can be chosen directly from the dialog. The delete button is labeled **Delete permanently** and stays disabled until the learner ticks "I understand this cannot be undone."
35. After a delete, the session disappears everywhere, including Home, search results, and Collections and Review. The learner sees a brief message and stays in the Library.
36. Deleting a **finished** session **lowers** the Progress totals by the words, phrases, mistakes, and grammar points it contained, and removes its practice day if no other finished session was finished on that day, which can lower the streak. Deleting an **unfinished** session changes nothing, because unfinished sessions never count.
37. Archiving, restoring, or deleting a session never changes any other session. Topics that are no longer used by any session simply stop appearing in the topic filter.
38. If a session is archived or deleted while its card or page is open elsewhere, opening it shows a calm "We couldn't find that session" page with a link back to the Library.

### Selecting several sessions

39. A **Select** button in the Library header (and in the Archived view) turns on selection mode. In selection mode, each card shows a checkbox, tapping a card selects or unselects it instead of opening it, and the single-session menu is hidden.
40. A selection bar shows how many sessions are selected (for example "3 selected") with **Select all** and **Cancel**, plus the available actions: **Archive** and **Delete** in the Library, and **Restore** and **Delete** in the Archived view. The actions are unavailable while nothing is selected, and **Archive** is also unavailable when only unfinished sessions are selected. The bar shows how many of the selected sessions can be archived.
41. **Select all** selects every session that matches the current filters and search, not only the ones loaded so far, and the count shows exactly how many are selected.
42. The learner can select and unselect individual sessions. On a computer, holding Shift while selecting selects a range. When search results are shown, whole sessions can be selected, but not individual matching items.
43. Selection is cleared when the filters or search change, or when selection mode ends, so the learner never acts on sessions they cannot see. Loading more sessions does not clear the selection.
44. **Archive** (several) happens immediately, with one brief message such as "3 sessions archived" that offers **Undo**. Undo restores all of them. Unfinished sessions in the selection are skipped, and the message says how many were archived and how many were skipped because unfinished sessions cannot be archived. Each archived session follows the same archive rules as a single session.
45. **Restore** (several), in the Archived view, returns all selected sessions to the Library exactly as they were.
46. **Delete** (several) always asks for confirmation first, and the safe choice (Cancel) is the default. The confirmation states how many sessions will be deleted, names the first few (topic and date) followed by "and N more," says how much will be removed in total, and states clearly that this cannot be undone.
    - If **any finished session** is included, the confirmation shows the same **prominent warning** as for a single finished session: it says how many finished sessions are included, what will be permanently removed, and that they will leave the Library, Collections, and Review. It shows the **combined effect on progress** (totals before and after, and the effect on practice days and the streak, worked out for all of them together). It suggests **Archive** for those finished sessions, and the **Delete permanently** button stays disabled until the learner ticks "I understand this cannot be undone."
    - If **only unfinished sessions** are selected, a clear confirmation is enough. It does not suggest Archive and does not need the tick, and it says that progress totals and streaks will not change.
47. If some selected sessions cannot be changed (for example, they were already archived or deleted in another tab, or, for Archive, they are unfinished), the rest are still processed, and the learner is told clearly how many were changed and how many were skipped. No session is left half-changed, and Undo covers only the sessions that were actually changed.
48. After a bulk action, selection mode ends, the list, the result count, and the Home lists update, and a brief message confirms what happened.
49. Selecting works with the keyboard and a screen reader: each checkbox is labeled with its session's topic and date, the selected count is announced calmly, and the selection bar is reachable. On a phone, the selection bar stays within easy reach of the thumb.

### Experience

50. The Library works comfortably on a phone, tablet, and computer. On a phone, filters open in a panel, and cards are easy to tap.
51. Everything can be done with a keyboard, with visible focus. Search results and the result count are announced to screen readers in a calm way.
52. Wording is friendly and encouraging. Nothing about the Library makes the learner feel judged for the number or state of their sessions.

### Edge cases

- **Very many sessions:** hundreds of sessions list and search quickly, without freezing the page.
- **Sessions with no content:** an unfinished session that was started but left empty appears with zero counts and an "Unfinished" label. It can be opened, continued, or deleted, but not archived.
- **Very long topic names or text:** cards stay tidy, with long topics shortened visually and shown in full when opened.
- **Special characters in search:** quotation marks, brackets, symbols, and non-English letters are treated as ordinary text and never cause errors or odd results.
- **Search matching in formatted text:** words inside bold, italic, or list text are found, and the formatting itself is never searched.
- **Search in rows that need attention or are duplicates:** these rows are searched like any other row.
- **A search term in many places:** a word that appears in many sessions or many times in one session still gives a readable, grouped result, with a sensible limit on how many items are listed per session and a "show more" option.
- **Same word in different sections:** shown as separate items, each labeled with its section.
- **Filters that exclude everything:** the empty message offers **Clear all**, and never leaves the learner with a blank page.
- **Dates:** sessions near midnight and across time zones appear on the day the learner experienced them. A custom date range with the end before the start is explained or corrected, not silently ignored.
- **Returning to the Library:** after editing a session, archiving, or deleting, the list reflects the change when the learner returns, without needing a refresh.
- **Archived and deleted while another tab is open:** the other tab shows correct information after its next load, and opening an affected session shows the friendly "couldn't find that session" page.
- **Undo after archive:** if the learner navigates away before pressing Undo, the session simply stays archived and can be restored from the Archived view.
- **Deleting the only session:** the Library shows the empty state again.
- **Deleting an unfinished session shown on Home:** the "Continue where you left off" card updates and no longer shows it.
- **Deleting a finished session that was the only one finished that day:** that day stops counting and the streak can drop. The warning showed the exact effect first.
- **Deleting a finished session:** its words, phrases, mistakes, and grammar points leave the Progress totals, Collections, and Review. The warning showed the new totals first.
- **Deleting a finished session that was edited after it was finished:** the totals drop by the content the session had at the moment it is deleted.
- **Deleting an unfinished session:** nothing was counted in the totals, so nothing changes.
- **An unfinished session, with or without content:** it never counts toward a practice day, so deleting it never affects streaks.
- **An unfinished session that is later finished:** from then on it counts like any finished session, on the day it was finished, until it is deleted.
- **Archiving a finished session:** its practice day keeps counting, because archiving never changes streaks.
- **Exporting with archived sessions:** archived sessions appear in the export, marked as archived, alongside active ones.
- **Imported sessions:** sessions with plain text, missing fields, or original dates in the past appear and behave like any other session.
- **Selecting everything:** with hundreds of sessions, Select all and the bulk actions finish quickly, and the count is exact.
- **Archiving or deleting all sessions:** the Library shows the empty state afterward.
- **Filters change while sessions are selected:** the selection is cleared, and the learner is told, so nothing hidden can be affected.
- **Selection across loaded pages:** sessions selected before loading more stay selected.
- **A selected session changes elsewhere:** if it was archived, deleted, or edited in another tab, bulk actions apply to its current state and the confirmation shows current numbers. Sessions that no longer exist are skipped and reported.
- **Mixed finished and unfinished sessions in a bulk delete:** only the finished ones change totals and streaks, and the warning shows the combined result.
- **Several finished sessions deleted together, from the same day or across a streak:** the warning works out the combined effect accurately, not session by session.
- **A deletion that makes the streak much shorter or zero:** the warning states the numbers plainly and keeps Archive visible as the alternative.
- **Totals higher than Collections:** Progress totals can be higher than what Collections and Review show, because archived sessions count in totals but are hidden from them. This is by design.
- **Pressing an action twice:** a bulk action runs once and causes no error.
- **Starting a bulk action with nothing selected:** not possible, because the actions are unavailable.
- **Bulk archive that includes unfinished sessions:** the unfinished ones are skipped and reported, and the finished ones are archived.
- **Bulk archive with only unfinished sessions selected:** Archive is unavailable and the learner is told why.
- **A session is finished while the Library is open:** its Archive option becomes available the next time the Library loads.
- **Deleting a finished session by mistake:** the prominent warning, the required tick, the Cancel default, and the Archive alternative make an accidental delete unlikely.
- **Choosing Archive from the delete warning:** the session is archived instead, nothing is deleted, and the learner is told.
- **Slow or failed loading:** the learner sees a clear loading state and, on failure, a calm message with a way to try again, and no work is ever lost.

## Technical

This describes the intended approach at a high level, using the agreed stack (Laravel, Inertia, Vue, Tailwind, shadcn-vue). It is a plan to refine, not a final design.

1. **Build on what exists.** Use the sessions, topics, statuses, dates, counts, and the reading page from Practice sessions. Reuse the reusable counting piece so cards, the Library, and later features always agree.
2. **Add the archived state.** Give sessions a way to be marked archived, with the date it happened, so archived sessions can be hidden, listed, and restored without losing anything. Only finished sessions can be archived, and this rule is enforced on the server as well as in the screens.
3. **Create the Library page.** A Vue page in the main app layout with a page header, a search box, a row of filter controls, the session cards, and a "load more" area. Use existing shared pieces (page header, session card, empty state, status chip) and **shadcn-vue components wherever possible** (see "UI components" below).
4. **Load the list efficiently.** Load sessions a page at a time, with counts worked out in bulk rather than one session at a time, so the page stays fast with many sessions.
5. **Add filtering and sorting.** Apply topic, date, content type, status, and sort on the server, combining them as described. Keep the chosen filters in the page address, so refreshing, the browser's Back button, and returning from a session keep the learner's place.
6. **Add search.** Search the plain-text versions of everything saved, ignoring capitalization, accents, and formatting, and group matches by session and section. Keep results fast and bounded, and keep the matching logic in one place so Collections can reuse it later.
7. **Show matches and jump to them.** Return enough information to highlight the matching text and to open the reading page at the matching item.
8. **Add the session menu.** Add Edit, Archive, and Delete to cards and to the reading page. Archive appears as unavailable, with a short reason, for unfinished sessions, and works with Undo for finished ones. Delete shows a simple confirmation for unfinished sessions, and for finished sessions a prominent warning with an "I understand" tick, an Archive alternative, and a "Delete permanently" button.
9. **Add the Archived view and Restore.** The same list, search, and filters, applied to archived sessions.
10. **Keep other parts of the app correct.** Make Home's lists, counts, and later features follow the include and exclude rules: archived sessions are excluded from Collections and Review, and included in Progress totals and exports.
11. **Add selection and bulk actions.** Add a selection mode to the Library and the Archived view, with checkboxes, a selection bar, Select all across everything that matches the current filters, and range selection. Apply archive, restore, and delete to many sessions in one request, reusing the single-session rules and reporting how many were changed or skipped.
12. **Work out progress from existing finished sessions.** Have Progress work out totals, practice days, and streaks from the finished sessions that exist (including archived ones), using the day each was finished. Provide a way to **preview** the effect of deleting one or several sessions (the new totals and the new streak) without changing anything, so the delete warning shows exact numbers and they match what Progress shows afterward.
13. **Make it work everywhere.** Test on phone, tablet, and computer, in light and dark mode, with the keyboard and a screen reader, and with large numbers of sessions.

### UI components: use shadcn-vue wherever possible

All interface pieces in the Library should come from **shadcn-vue**, the component library already chosen for the app. Only build a custom piece when shadcn-vue has nothing suitable, and then build it by **composing shadcn-vue parts with Tailwind**. Do not add any other component library.

| Library need | shadcn-vue component to use |
|---|---|
| Search box | Input (with an icon and a clear button) |
| Topic filter (one or several topics) | Combobox, or Popover with Command and Checkbox |
| Date filter (quick choices and custom range) | Select for the quick choices, plus Popover with Range Calendar for a custom range |
| Content type and status filters | Toggle Group, or Select and Checkbox in a Popover |
| Sort control | Select |
| Active filter chips and status labels ("Finished," "Unfinished," "Archived") | Badge (with text, never color alone) |
| Library and Archived view switch | Tabs |
| Session cards | Card |
| Single-session menu (Edit, Archive, Delete) | Dropdown Menu |
| Explanation for the unavailable Archive option | Visible helper text, with Tooltip only as an extra (never the only place the reason appears) |
| Selection checkboxes and the "I understand this cannot be undone" tick | Checkbox |
| Selection bar actions and all buttons | Button |
| Delete confirmations (simple and prominent warning) | Alert Dialog, with Alert for the warning callout |
| Filters panel on phones | Sheet |
| Messages with Undo ("3 sessions archived") | Sonner (toast) |
| Loading states | Skeleton |
| Empty and no-results states | Empty-state piece built from Card, Button, and existing icons |
| "Load more" and result counts | Button, and plain text |
| Dividers and layout details | Separator, Scroll Area |

Where a shadcn-vue component needs small changes (for example the destructive look of the finished-session warning), change its **variants and Tailwind classes** instead of replacing it, so the Library stays visually consistent with the rest of the app, in light and dark mode, and keeps the accessibility built into the components.

## Implementation Steps

The steps are ordered so each one leaves a usable, reviewable piece of the feature.

### Step 1: The Library list

**What it delivers:** A Library page, reachable from the main navigation and from Home, that lists all non-archived sessions newest first as cards with topic, date, counts, and status. Cards open the right page. Long lists load progressively, and empty states are in place.

**Tests**
- The Library link in the main navigation and the "View all sessions" link on Home both open the Library.
- Finished and unfinished sessions are both listed, newest session date first.
- Each card shows the topic, date, counts (questions, words, phrases, mistakes), and a text status label.
- A card shows "Edited ..." only when the session was last edited on a later day than its session date, and the exact date is available as text for screen readers.
- Editing a session updates its last edited text on the card.
- Counts are correct, and update after a session is edited.
- The total number of sessions is shown and is correct.
- Opening a finished session opens its reading page, and opening an unfinished session opens the editor.
- Returning to the Library from a session brings the learner back to the same scroll position.
- With 300 sessions, the page loads quickly and more sessions load as the learner reaches the end, with no duplicates or missing sessions.
- With no sessions, the empty state and "Start your first session" button appear, and the button starts a new session.
- A very long topic name stays tidy on the card.
- A session with no content shows zero counts and an "Unfinished" label.
- The page works on phone, tablet, and computer, in light and dark mode, with no sideways scrolling.

### Step 2: Filters and sorting

**What it delivers:** Topic, date, content type, and status filters, plus sorting, with active filters shown as removable chips, a Clear all action, and filter state kept in the page address.

**Tests**
- Choosing one topic shows only that topic's sessions, and choosing several shows sessions from any of them.
- The date filter works for each quick choice and for a custom range, using the session date.
- A custom range with the end before the start is explained or corrected.
- Each content type filter shows only sessions with at least one non-empty entry of that type, and choosing several types works.
- The status filter shows all, finished only, or unfinished only.
- Combining topic, date, type, and status shows only sessions that match all of them.
- Active filters appear as chips, each chip removes only its own filter, and Clear all removes every filter.
- Sorting by newest first, oldest first, and recently edited all give the correct order.
- The result count updates with every change.
- When nothing matches, a friendly message with Clear all appears, never a blank page.
- Refreshing the page, using the browser's Back button, and returning from a session all keep the same filters, sort, and scroll position.
- Topics no longer used by any session do not appear in the topic filter.
- Filters work on a phone, using a panel that is easy to open and close, and can be used with the keyboard.
- Sessions near midnight appear on the correct day.

### Step 3: Search

**What it delivers:** A search box that finds any word, phrase, or mistake saved in any session, shows matching sessions with their matching items, highlights the match, and opens the session at that item.

**Tests**
- Results appear as the learner types, after a short pause, and not before two characters.
- Search finds text in topics, tutor's questions, answers, mistakes (what I said, correction, reason), vocabulary (word, meaning, example), phrases (phrase, meaning or use, example), grammar notes, and natural versions.
- Search ignores capitalization, accents ("cafe" finds "café"), and extra spaces, and matches parts of words.
- Words inside bold, italic, and list text are found, and formatting itself is not searched.
- Typing several words finds only entries where all of them appear together.
- Results are grouped by session, with each matching item labeled by section, using the same tag and text label as elsewhere, and the matching text highlighted without relying on color alone.
- The same word in different sections appears as separate labeled items.
- A word with many matches shows a sensible number of items per session, with a "show more" option.
- Selecting a matching item opens the reading page at that item, scrolled into view and briefly highlighted.
- Search works with filters. For example, with the "mistakes" content type chosen, only mistakes are searched, and with a topic filter, only that topic's sessions are searched.
- Clearing the search returns to the normal list.
- When nothing matches, the friendly message and suggestions appear.
- Quotation marks, brackets, symbols, and non-English letters never cause errors or strange results.
- Rows marked "Needs attention" and rows flagged as duplicates are searched like any other row.
- Search stays fast with hundreds of sessions and thousands of entries.
- Result counts and changes are announced to screen readers calmly, without announcing every keystroke.
- The search box and results work with the keyboard and on a phone.

### Step 4: Archive and restore

**What it delivers:** A session menu with Edit and Archive on cards and on the reading page, an Archived view with Restore, and the rules for what archiving hides.

**Tests**
- The menu on a card and on the reading page offers Edit, Archive, and Delete (Delete is added in Step 5), and works with the keyboard and touch.
- Edit opens the editor for that session.
- Archiving a finished session works immediately, with a message that offers Undo.
- For an unfinished session, Archive is shown as unavailable with the explanation "Finish this session to archive it," and cannot be triggered.
- Trying to archive an unfinished session directly (for example with a hand-made request) is refused with a clear message and changes nothing.
- After an unfinished session is finished, its Archive option becomes available.
- The Archived view lists only finished sessions.
- Pressing Undo brings the session back, exactly as it was.
- An archived session disappears from the main Library, from Home's "Recent sessions" list, and from search results.
- The Archived view lists archived sessions with search and filters working as in the main Library.
- Restore returns a session to the Library with its original status, date, and content, and it appears in the correct sorted position.
- Navigating away before pressing Undo leaves the session archived, and it can be restored from the Archived view.
- Archiving never changes any other session or any topic.
- Opening an archived session's address from another tab shows the friendly "couldn't find that session" page, or opens it from the Archived view with a clear "Archived" label (the chosen behavior is applied consistently).
- Archived sessions are excluded by the shared rule that Collections and Review will use, and included by the shared rule that Progress totals and Export will use. A test confirms both rules exist in one place.
- Archiving and restoring a session never change Progress totals, practice days, or streaks.
- An archived session is still included when all data is exported, with its archived status.
- The Archived view's empty state is friendly and clear.

### Step 5: Delete

**What it delivers:** Permanent deletion from cards, the reading page, and the Archived view, with a simple confirmation for unfinished sessions and a prominent warning for finished sessions. The finished-session warning shows the exact effect on progress totals and the streak before the learner confirms, and the deletion then lowers them accordingly.

**Tests**
- Delete always asks for confirmation first.
- For an unfinished session, the confirmation names the session (topic and date), says how much will be removed, and states that it cannot be undone. It does not suggest Archive.
- For a finished session, a prominent warning appears, visually distinct from other confirmations, with plain and serious wording.
- The finished-session warning names the session, lists exactly what will be permanently removed (questions, words, phrases, mistakes, grammar notes), and says it will leave the Library, Collections, and Review.
- The finished-session warning offers Archive as a reversible alternative that keeps everything.
- Choosing Archive from the warning archives the session and deletes nothing.
- The **Delete permanently** button stays disabled until the learner ticks "I understand this cannot be undone," and the tick works with the keyboard.
- The warning is readable on a phone and in light and dark mode, and its heading and message are announced by a screen reader.
- Cancel is the default choice, and cancelling changes nothing.
- Confirming removes the session and everything in it permanently, with a brief message, and the learner stays in the Library.
- The deleted session disappears from the Library, the Archived view, Home, and search.
- Deleting an unfinished session that is shown on Home updates the "Continue where you left off" card.
- Deleting the only remaining session shows the empty state.
- Deleting from the reading page returns the learner to the Library.
- Opening a deleted session's address shows the friendly "couldn't find that session" page with a link to the Library.
- Deleting does not change any other session, and topics no longer used stop appearing in the topic filter.
- Pressing Delete's confirm button twice quickly deletes the session once and causes no error.
- Deleting a finished session that was the only one finished that day removes that day, and the streak drops accordingly.
- Deleting an unfinished session, with or without content, never changes the streak, practice days, or Progress totals.
- Deleting a finished session lowers the totals that Progress uses by exactly the words, phrases, mistakes, and grammar points it contained.
- If a finished session was edited after finishing, deleting it lowers the totals by the content it had at the moment of deletion.
- A deleted session's content disappears from Collections, Review, and the Progress totals.
- The finished-session warning shows the totals before and after, and the effect on practice days and the streak when it changes, and says plainly when nothing changes.
- The numbers shown in the warning match what Progress shows after the deletion.
- The unfinished-session confirmation says that progress totals and streaks will not change.
- A session that was unfinished, then finished, then deleted counts while it exists and stops counting after it is deleted.
- Archiving and restoring never change totals or streaks.
- The dialog works with the keyboard, traps focus, closes with Escape, and is announced by a screen reader.

### Step 6: Selecting several sessions

**What it delivers:** A selection mode in the Library and the Archived view, with Select all, range selection, and bulk Archive, Restore, and Delete.

**Tests**
- The Select button turns on selection mode, showing a checkbox on every card, and Cancel turns it off.
- In selection mode, tapping a card selects or unselects it instead of opening it, and the single-session menu is hidden.
- The selection bar shows the correct count, and the actions are unavailable when nothing is selected.
- Individual sessions can be selected and unselected, and holding Shift selects a range on a computer.
- Select all selects every session matching the current filters and search, not only the loaded ones, and the count is exact.
- Sessions can be selected in search results, and individual matching items cannot.
- Changing the filters or the search clears the selection and tells the learner.
- Loading more sessions keeps the existing selection.
- Archiving several finished sessions happens immediately, shows one message such as "3 sessions archived," and Undo restores all of them.
- When the selection includes unfinished sessions, Archive archives only the finished ones, and the message says how many were archived and how many were skipped.
- When only unfinished sessions are selected, Archive is unavailable and the learner is told why.
- The selection bar shows how many of the selected sessions can be archived.
- Restoring several archived sessions returns them with their original status, dates, and content.
- Deleting several sessions always asks for confirmation, stating the number of sessions, naming the first few followed by "and N more," how much will be removed, and that it cannot be undone. Cancel is the default.
- When any finished session is included, the prominent warning appears, says how many finished sessions are included, shows the combined effect on totals and streaks, suggests Archive for them, and requires the "I understand" tick before Delete permanently is enabled.
- When only unfinished sessions are selected, a clear confirmation without an Archive suggestion is shown, and no tick is required.
- Archiving several sessions never changes totals or streaks. Deleting several sessions lowers totals and streaks by exactly the finished sessions removed, and unfinished sessions change nothing.
- The bulk delete warning shows the combined before-and-after totals and the combined effect on the streak, and these match what Progress shows afterward.
- If some selected sessions were archived or deleted in another tab, the rest are processed, and the learner is told how many were changed and how many were skipped. Undo covers only the changed ones.
- After a bulk action, selection mode ends, and the list, result count, and Home lists update.
- Archiving or deleting all sessions shows the empty state.
- Pressing an action twice runs it once with no error.
- Selecting all and archiving or deleting several hundred sessions finishes quickly.
- Each checkbox is labeled with its session's topic and date, the selected count is announced calmly, and everything works with the keyboard.
- On a phone, the selection bar stays within easy reach, and selection mode works in light and dark mode with no sideways scrolling.

### Step 7: Polish and quality checks

**What it delivers:** The whole Library is checked end to end for speed, clarity, consistency, and accessibility.

**Tests**
- A full journey works: open the Library, filter by topic and date, search for a word, open the match, edit the session, return to the same place, archive it, restore it, and delete it, and also select several sessions and archive, restore, and delete them together.
- Wording is friendly and consistent with the rest of the app, and nothing makes the learner feel judged.
- All screens work on a phone, tablet, and computer, with no sideways scrolling, and at 200 percent zoom.
- All text, chips, tags, highlights, and focus outlines have good contrast in light and dark mode.
- Everything is reachable and usable with the keyboard alone, and key changes are announced by a screen reader.
- Loading, empty, no-results, and error states are friendly and tell the learner what to do next.
- Filtering and search stay fast with 500 sessions.
- No query is run once per session where one query for the page would do.
- Interface pieces come from shadcn-vue wherever a suitable component exists, any custom piece is built from shadcn-vue parts, and no other component library has been added.
- Every destructive action has a confirmation or an Undo.
- The Library, Home lists, and counts stay consistent after every kind of change (edit, archive, restore, delete, finish).
- Imported-style sessions (plain text, missing fields, old dates) appear, filter, and search correctly.
- A learner can open only their own sessions, and a guessed or old address never shows anyone else's content.

## Decisions Made

- **Archived sessions count in Progress totals.** Archiving never removes anything from the learner's history.
- **Unfinished sessions cannot be archived.** They can be continued, finished, or deleted. Only finished sessions can be archived, so the Archived view contains only finished sessions.
- **Cards show the last edited date** (when it differs from the session date), in addition to the "recently edited" sort.
- **Deleting a finished session shows a prominent warning**, with the details of what will be removed, the exact effect on progress, an Archive alternative, and an "I understand this cannot be undone" tick before **Delete permanently** is enabled. Deleting an unfinished session needs only a simple confirmation.
- **Totals and streaks follow the content.** Progress totals, practice days, and streaks are worked out from the finished sessions that exist, using the day each was finished. Archived sessions count, unfinished sessions never count, and archiving changes nothing. Deleting a finished session lowers totals and can lower the streak.
- **The practice day that counts is the day a session was finished**, not the day it was started.
- **Delete warnings show the exact effect.** The warning for a finished session (single or several) shows the totals before and after and the effect on the streak, so the learner knows exactly what will change before confirming.
- **Exports include archived sessions**, with their archived status kept.
- **The learner can select several sessions at once** to archive, restore, or delete them together, in the Library and in the Archived view.

## Open Questions

None at the moment.

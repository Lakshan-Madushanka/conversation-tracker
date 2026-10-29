# Personal collections

## Summary

Personal collections turn many separate practice conversations into **one personal study notebook**. The app automatically gathers everything the learner has saved in their finished sessions into four collections: **Vocabulary**, **Phrases and expressions**, **Grammar notes**, and **My common mistakes**. The learner can browse each collection, **star** the items that matter most, mark items as **learned** or **still difficult**, add **personal notes**, and jump from any item back to the session it came from.

Collections fill themselves. The learner does nothing extra after recording a session: finishing it is enough. The point is to make it easy to see, at a glance, what they have learned, what keeps going wrong, and what is worth practicing next.

### Assumptions

- The app is for **one learner** (personal use). Everything in Collections belongs to that learner.
- The **Practice sessions** and **Library** features already exist. Sessions have a topic, a date, questions, and feedback entries, and a status of finished or unfinished. Sessions can be archived, restored, and deleted.
- **Only finished sessions that are not archived feed Collections.** (This is the "study sessions" rule set in the Library feature.) Unfinished sessions never appear. Archived sessions are hidden, and come back if restored. Deleted sessions' content is gone.
- Collections are built from these entries: **vocabulary** (word, meaning, example), **phrases** (phrase, meaning or use, example), **grammar notes** (formatted notes), and **mistakes** (what I said, the correction, and the reason). The **more natural versions** and the tutor's questions and answers are **not** part of Collections.
- If a build still has vocabulary, phrases, or mistakes as single-line entries instead of tables, Collections works with whatever text the entries contain.
- **Collections show the learner's saved content as it is in the sessions.** To change the text of a word, phrase, note, or mistake, the learner edits the session it came from. Collections let the learner change only their own **star, status, and personal notes**.
- The **same word, phrase, or mistake can appear in several sessions** (for example the same word learned twice). Collections show it **once**, with how often it was seen, rather than repeating it.
- **Words, phrases, and mistakes** are the same item when their main text matches, ignoring capitalization, accents, extra spaces, and formatting. For vocabulary this is the word, for phrases the phrase, and for mistakes the pair of "what I said" and "the correction". **Grammar notes are never merged.** Every grammar note is its own item, even if two notes have identical text.
- **Star, status, and personal notes belong to the item**, not to one session, so they stay the same wherever a word, phrase, or mistake appears. For a grammar note, they belong to **that note itself**.
- **"Learned" and "Still difficult" are the learner's own labels, and only the learner ever sets them.** The app never changes a status automatically. They do not change Progress totals, and Progress totals do not change them.
- Collections need an internet connection (offline use is an open question in the spec).

### Dependencies

- **Practice sessions:** the source of all entries, and the **reading page** and **open at item** behavior used to jump to the original session. Finishing a session is what makes its entries appear.
- **Library:** the **study sessions** rule (finished and not archived), the shared text matching that ignores capitalization, accents, and formatting, and archive, restore, and delete, which must be reflected in Collections straight away.
- **Base app layout:** the main navigation (with a Collections link), the page header, tabs, and empty-state pieces.
- **Review and re-practice (F4):** will use the collections, and the learner's stars and statuses, to build exercises. The star and status choices made here are the ones Review will read. Review may later **suggest** a status change (for example "You got this right 5 times. Mark it as learned?"), but the learner always confirms it.
- **Progress and motivation (F5):** counts words, phrases, and grammar points from finished sessions. These are separate from the learner's "Learned" label.
- **Export and backup (F7):** the learner's stars, statuses, and personal notes should be included in the data they can download.
- **Import from spreadsheets (F6):** imported sessions, once finished, feed Collections like any other session.

### Related notes

- **Experience principles that apply:** simple first, calm and encouraging (mistakes are learning opportunities, never failures), quick to use on any device, distraction-free, and rewarding.
- **Spec goal:** make re-practicing easy and enjoyable. Collections are where the learner sees what to practice.
- **User flow in the feature list:** open Collections from the main menu, choose a collection, see every item with its meaning and example, star the ones to focus on, open one to add a note and mark it "still difficult", jump to the original session if more context is needed, and in My common mistakes see which errors appear most often.
- **Screen in the feature list:** four tabs (Vocabulary, Phrases, Grammar, My mistakes). A list of items, each with the item, its meaning or correction, a star, and a small status label. Sorting and a "starred only" choice. An item opens in a panel with the full explanation, an example, a notes box, status buttons, and a "See original session" link. In My mistakes, items are grouped with the most frequent first and a counter such as "Seen 5 times".
- **Existing visual reference:** the layout mockups show the general look of cards, tabs, and navigation. A specific mockup of the Collections screen has not been made yet.
- **Not part of this feature:** Review exercises, the Progress screen, editing the text of items, the more natural versions, and starring or marking several items at once (to be reconsidered after Review is built).

## Goals

- Let the learner see everything they have learned and every mistake they have made, in one organized place, without any extra work.
- Make it easy to find a particular word, phrase, note, or mistake, and to focus on what matters most (starred and difficult items).
- Show which mistakes keep coming back, so the learner knows what to work on.
- Let the learner keep their own marks (star, learned, still difficult) and notes on items, safely and permanently.
- Keep Collections always in step with the learner's sessions: finished, edited, archived, restored, or deleted.
- Keep it calm, fast, and easy to use on a phone as well as a computer.

## Requirements

### The Collections page

1. Collections is reachable from the main navigation.
2. It has four collections, shown as tabs in this order: **Vocabulary**, **Phrases**, **Grammar notes**, and **My mistakes**. Each tab shows how many items it contains.
3. The learner's last chosen tab is kept in the page address, so a refresh or a bookmark opens the same collection.
4. Collections are built **automatically** from the finished sessions that are not archived. The learner never adds items by hand and never has to refresh.
5. If the learner has no finished sessions yet, a friendly empty state explains that collections fill up automatically once a session is finished, and offers a button to start a session. If one collection is empty, it shows its own friendly message.
6. Items from unfinished sessions never appear.

### Items in a collection

7. Each **word, phrase, and mistake** is shown once, even if it appears in several sessions, and is identified by its main text (the word, the phrase, or the pair of what was said and the correction), ignoring capitalization, accents, extra spaces, and formatting. **Grammar notes are never merged**: each note is shown as its own item.
8. A **vocabulary** item shows the **word**, its **meaning**, and its **example**, if there is one.
9. A **phrase** item shows the **phrase**, its **meaning or use**, and its **example**, if there are any.
10. A **grammar note** item shows its formatted text, shown as a short preview in the list and in full when opened. Each note is its own item, and notes with identical text stay separate items.
11. A **mistake** item shows **what I said**, **the correction**, and **the reason** when there is one.
12. Every item shows a **star**, a clear **status label** ("Learned" or "Still difficult", shown as text, not by color alone, and nothing shown when no status is set), and, when the item appears in more than one session, how many times it was seen.
13. Items whose meaning, correction, or similar needed part is missing are still listed, with a clear **"Needs attention"** marker and a short reason (the same wording and look used elsewhere in the app) and a way to open the original session to fix them. They are never hidden.
14. When the same word or phrase has **different meanings or examples** in different sessions, the list shows the most recent one, and the opened item shows all of them, each with the session it came from.
15. When an item appears more than once in the **same box of the same session** (a repeated row that was flagged as a duplicate), it counts as one appearance in that session.

### My common mistakes

16. **My mistakes** groups the learner's mistakes by what they said and the correction, so the same mistake across sessions appears as one item.
17. Each group shows how often it was seen, for example **"Seen 5 times"**, counting each appearance in a finished session that is not archived (a repeated row in the same box counts once).
18. By default the most frequent mistakes are listed first, with ties ordered by the most recent.
19. Opening a group shows the correction, the reason, and **each place it appeared**, with the session's topic and date.
20. Mistakes without a correction are grouped by what was said alone and carry the "Needs attention" marker.
21. The tone is encouraging. The wording presents frequent mistakes as the best things to practice, never as failures.

### Opening an item

22. Opening an item shows it in full: all its text, all meanings or examples, the reason (for mistakes), the learner's star, status, and personal notes, and the list of sessions where it appeared.
23. **"See original session"** opens the session's reading page **at that item**, scrolled to it and briefly highlighted, as when opening a search result in the Library. When the item appears in several sessions, each session is listed with its topic and date, and each opens at its own occurrence.
24. Only sessions that currently feed Collections are listed. Archived sessions and deleted sessions never appear as places an item came from.
25. Returning from a session brings the learner back to the same collection, with the same sorting, filters, search, scroll position, and opened item.

### Star, status, and personal notes

26. The learner can **star** and **unstar** any item, from the list and from the opened item.
27. The learner can mark an item **Learned** or **Still difficult**, or clear the mark. An item has at most one status. The status can be changed from the list and from the opened item. **Only the learner sets the status.** The app never changes it automatically.
28. The learner can write **personal notes** on any item, with simple formatting (bold, italic, bulleted and numbered lists) and the same safe handling of pasted text as elsewhere in the app. Notes are saved automatically, with a visible saved indicator, and are never lost.
29. Star, status, and notes belong to the **item**, so they are the same wherever a word, phrase, or mistake appears. For a **grammar note**, they belong to **that note itself**.
30. **Fixing a typo in the session keeps the learner's star, status, and notes.** When the text of a word, phrase, or mistake is edited so that it becomes a different item, the learner's marks and notes follow it when it was the only appearance of the old item. When the old item still appears elsewhere, it keeps its marks, and the edited text is treated as its own item. A **grammar note** keeps its star, status, and notes whenever its text is edited, because they belong to the note itself.
31. If an item disappears (for example every session it appeared in is deleted or archived), the learner's star, status, and notes for it are **kept quietly**, and **return** if the item appears again. For a grammar note in an archived session, they return when the session is restored.
32. Changing a star or status never changes any session, and never changes any Progress total.

### Finding and focusing

33. Each collection has a **search box** that finds items by their text (including meanings, examples, reasons, and the learner's notes), ignoring capitalization, accents, extra spaces, and formatting, and matching parts of words. When several words are typed, all must appear in the same item.
34. The learner can show **starred items only**, and can filter by **status**: all, not marked, learned, or still difficult.
35. The learner can show **only the items that need attention**, in Vocabulary, Phrases, and My mistakes. The collection shows how many items need attention (for example "3 need attention"), and the count disappears when none do. The filter appears as a removable chip like the others.
36. The learner can filter by **topic** of the sessions an item appeared in.
37. The learner can sort by **newest** (the most recent session an item appeared in), **A to Z**, and **most often seen**. The default is newest, and for My mistakes it is most often seen.
38. Active filters are shown as removable chips with a **Clear all**, and the list shows how many items match (for example "12 of 180 words"). When nothing matches, a friendly message offers to clear the filters.
39. The chosen collection, search, filters, and sorting are kept in the page address, and a refresh or a return from a session keeps them.
40. Long collections load progressively and stay quick with thousands of items.

### Staying up to date

41. Collections update **straight away** when a session is finished, edited, archived, restored, or deleted. New items appear, edited items change, archived or deleted items disappear, and restored items return, without a refresh.
42. Counts on the tabs and in the lists are always correct.
43. A session that is edited **after** it is finished is reflected. An item added later appears. An item removed from a session no longer appears, unless it also appears elsewhere.
44. Archiving never affects Progress totals, but it does remove items from Collections. The learner should understand this difference. The archived banner and archive messages can mention that archived sessions are hidden from Collections.

### Experience

45. Collections work comfortably on a phone, tablet, and computer. On a phone, items appear as easy-to-tap cards, the tabs scroll sideways if needed, and an opened item takes the whole screen or a bottom panel.
46. Everything works with the keyboard and a screen reader, with visible focus, clear names for stars and status buttons that include the item, and calm announcements for changes (starring, status, saved notes, result counts).
47. Wording is friendly and encouraging. Nothing about Collections makes the learner feel judged by the number of mistakes or difficult items.

### Edge cases

- **Very many items:** thousands of words, phrases, and mistakes list, search, and filter quickly.
- **Same word with different capitalization or accents:** treated as one item (for example "Café" and "cafe").
- **Same word with different meanings:** one item, listing all meanings and the sessions each came from.
- **Same mistake with different reasons:** one item, listing each reason with its session.
- **Same correction for different mistakes:** separate items, because the pair is what identifies a mistake.
- **A mistake or word missing its meaning or correction:** listed with "Needs attention", and it can be opened and marked, starred, and annotated like any other item.
- **Very long notes, meanings, or grammar notes:** accepted, tidy in the list (shortened), and complete when opened.
- **Special characters and non-English letters:** treated as ordinary text in the list, in search, and in notes.
- **An item that appears in a session that is later archived:** it disappears if that was its only appearance, its place in other sessions is unaffected, and it returns, with the learner's marks, when the session is restored.
- **An item whose only session is deleted:** it disappears, and the learner's marks and notes are kept quietly.
- **A session finished after an item was starred:** the star is already there if the item matches, because marks belong to the item.
- **A session edited so an item changes:** handled by the typo rule above.
- **A grammar note edited:** it keeps its star, status, and notes, because they belong to the note itself, however much its text changes.
- **Two grammar notes with identical text:** two separate items, each with its own star, status, and notes.
- **Two tabs open:** changing a star or status in one tab shows correctly in the other after its next load, and notes use the same careful saving as elsewhere, with a calm message if they conflict and nothing lost.
- **Unfinished session finished later:** its items appear in Collections when it is finished.
- **No finished sessions at all:** the friendly empty state is shown.
- **Opening a session for an item whose session was changed or removed:** the session opens normally with a calm note, or a friendly "couldn't find that session" page if it no longer exists.
- **Slow or failed loading:** a clear loading state, and on failure a calm message with a way to try again.

## Technical

This describes the intended approach at a high level, using the agreed stack (Laravel, Inertia, Vue, Tailwind, shadcn-vue). It is a plan to refine, not a final design.

1. **Build on what exists.** Use the entries saved by Practice sessions, the "study sessions" rule from the Library (finished and not archived), the shared text matching from the Library's search, the reading page with its "open at item" behavior, the shared labels and color tags, and the "Needs attention" marker.
2. **Define what an item is.** Decide, in one place, how entries are identified as the same item: the normalized main text for words and phrases, and the pair for mistakes, with a repeated row in one box counting once. **Grammar notes are never merged**, so each note keeps its own identity. Keep this identification separate from the screens so every part of Collections, and later Review, uses the same definition.
3. **Gather items from sessions.** Build each collection from the entries of the study sessions, grouping them into items with how often they were seen, their meanings, examples, reasons, and the places they came from. Work this out efficiently in bulk, with a bounded number of database queries however many items there are, and keep it correct as sessions change.
4. **Store the learner's own marks.** Add a place to keep the learner's star, status, and notes. For words, phrases, and mistakes, tie them to the item's identity, keep them when an item disappears (so they return with it), and let them follow an item when its text is edited under the typo rule. For grammar notes, tie them to the note itself. Status is only ever set by the learner.
5. **Create the Collections page.** A Vue page in the main app layout with a page header, four tabs with counts, and a list area per collection. Use shadcn-vue components wherever possible (see "UI components" below).
6. **Create the item list.** Build one reusable list that adapts to each collection (vocabulary, phrases, grammar notes, mistakes), showing the item, its meaning or correction, the star, the status label, and the seen count, with loading, empty, no-results, and error states.
7. **Create the opened item.** A panel or screen with the full details, the sessions the item came from with "See original session" links, the star, the status buttons, and a personal notes editor reusing the app's rich text editor and saving behavior.
8. **Group mistakes.** Add the grouping, frequency counts, default ordering by most seen, and the occurrences view for My mistakes.
9. **Add search, filters, and sorting.** Reuse the Library's text matching and filter patterns for search, starred only, status, topic, and sorting, and keep the choices in the page address.
10. **Keep Collections in step.** Make sure finishing, editing, archiving, restoring, and deleting a session update Collections straight away, and that counts are always correct, without keeping separate copies that can go out of date.
11. **Connect to other features.** Expose the items, stars, and statuses in a stable form for Review, include the learner's marks and notes in future exports, and keep Progress separate from the learner's "Learned" label.
12. **Make it work everywhere.** Test on phone, tablet, and computer, in light and dark mode, with the keyboard and a screen reader, and with large numbers of items.

### UI components: use shadcn-vue wherever possible

All interface pieces in Collections should come from **shadcn-vue**, the component library already chosen for the app. Only build a custom piece when shadcn-vue has nothing suitable, and then build it by **composing shadcn-vue parts with Tailwind**. Do not add any other component library.

| Collections need | shadcn-vue component to use |
|---|---|
| The four collection tabs with counts | Tabs |
| Item rows and cards | Card, or Table for wider screens with stacked cards on phones |
| Star button | Toggle (with an accessible label naming the item) |
| Status label and "Needs attention" marker | Badge (text, never color alone) |
| Status choice (Learned, Still difficult, clear) | Toggle Group, or Dropdown Menu in the list |
| Opened item | Sheet (side panel on larger screens, bottom or full-screen on phones), or Dialog |
| Search box | Input with a clear button |
| Starred only, status, and topic filters | Toggle, Toggle Group, Select, and Combobox |
| "Needs attention only" filter and its count | Toggle, with Badge for the count |
| Sort control | Select |
| Active filter chips | Badge with a small Button to remove each |
| Personal notes | The app's existing rich text editor, with Button for formatting |
| Saved indicator and messages | Badge or text with Sonner (toast) |
| "Show more" for long text or sessions list | Collapsible |
| Loading, empty, and error states | Skeleton, Alert, and the existing empty-state piece |
| Dividers and layout details | Separator, Scroll Area |

Where a shadcn-vue component needs small changes, change its **variants and Tailwind classes** instead of replacing it, so Collections stays visually consistent with the rest of the app, in light and dark mode, and keeps the accessibility built into the components.

## Implementation Steps

The steps are ordered so each one leaves a usable, reviewable piece of the feature.

### Step 1: The Collections page and the Vocabulary collection

**What it delivers:** A Collections page with the four tabs and counts, the automatic gathering of items from finished, non-archived sessions, and the Vocabulary collection as a read-only list with an opened item view and "See original session" links. The other three tabs show a friendly "coming soon in the next step" state only if needed during development, and are completed in the next steps.

**Tests**
- The Collections link in the main navigation opens the page, and the tab choice is kept in the address and survives a refresh.
- Vocabulary items come only from finished sessions that are not archived. Unfinished sessions and archived sessions never contribute.
- Each word appears once, even when it appears in several sessions, ignoring capitalization, accents, and spaces ("Café" and "cafe" are one item).
- Each item shows the word, meaning, and example, and the seen count when it appears in more than one session.
- The same word with different meanings shows the most recent in the list and all meanings, each with its session, when opened.
- A repeated row in the same box of the same session counts as one appearance.
- Items missing a meaning are listed with "Needs attention" and a short reason, and can be opened.
- Opening an item shows all its details and the sessions it came from, with topic and date.
- "See original session" opens the reading page at that item, scrolled and briefly highlighted, for each session listed.
- Returning from a session brings the learner back to the same tab and scroll position.
- With no finished sessions, the friendly empty state with a "Start a session" button appears. With finished sessions but no vocabulary, the tab shows its own friendly message.
- The tab count matches the number of items, and the list loads progressively without duplicates or gaps.
- With 5,000 vocabulary items, the page loads quickly and the number of database queries does not grow with the number of items.
- The page works on a phone, tablet, and computer, in light and dark mode, with no sideways scrolling.

### Step 2: Phrases and Grammar notes

**What it delivers:** The Phrases collection (phrase, meaning or use, example) and the Grammar notes collection (each note as its own item, with a short preview in the list and the full formatted text when opened), using the same list, item view, and links as Vocabulary.

**Tests**
- Phrases come only from finished, non-archived sessions, each phrase appears once with its meaning or use and example, and the seen count when it appears more than once.
- A phrase without a meaning or use is shown normally and is **not** marked "Needs attention", because that is normal for phrases.
- Grammar notes appear as separate items with a short plain preview in the list and the full formatted text (bold, italic, lists) when opened.
- Two grammar notes with identical text stay as two separate items.
- Very long grammar notes are shortened in the list and complete when opened.
- "See original session" opens the reading page at the right phrase or note.
- The tab counts match the number of items in each tab.
- Each tab has its own friendly empty and error states.
- Phrases and grammar notes match Vocabulary in layout, wording, and behavior, using the same reusable list.
- Special characters and non-English letters appear correctly.
- The pages work on a phone, tablet, and computer, in light and dark mode, with the keyboard and a screen reader.

### Step 3: My common mistakes

**What it delivers:** The My mistakes collection, grouping repeated mistakes, showing how often each was seen, ordering the most frequent first, and an opened view with every place the mistake appeared.

**Tests**
- The same mistake in several finished, non-archived sessions appears once, as one group, identified by the pair of what was said and the correction, ignoring capitalization, accents, and spaces.
- Each group shows "Seen N times", counting each appearance, with a repeated row in the same box of the same session counted once.
- The default order is most frequent first, with ties ordered by the most recent session.
- The same mistake with different reasons is one group, and the opened view lists each reason with its session.
- The same correction for different mistakes gives separate groups.
- A mistake without a correction is grouped by what was said alone and shows "Needs attention. Add the correction."
- Opening a group shows the correction, the reason, and each place it appeared with topic and date, and "See original session" opens the right session at the right row.
- The wording is encouraging, presenting frequent mistakes as the best things to practice.
- Archiving a session lowers a group's count by its appearances, and the group disappears if that was its only appearance. Restoring the session brings it back.
- Deleting a session removes its appearances in the same way.
- The tab count matches the number of groups, and the list loads progressively without duplicates or gaps.
- With 5,000 mistakes, the page stays fast and the number of database queries does not grow with the number of groups.
- The page works on a phone, tablet, and computer, in light and dark mode, with the keyboard and a screen reader.

### Step 4: Stars, status, and personal notes

**What it delivers:** The learner's own marks on items: a star, a status (Learned or Still difficult), and personal notes with simple formatting, saved automatically and kept safely, including the rules for edited text and disappearing items.

**Tests**
- A star can be added and removed from the list and from the opened item, and shows correctly everywhere the item appears.
- An item can be marked Learned or Still difficult, or cleared, from the list and from the opened item, and has at most one status at a time. The status label is text, not color alone.
- Starring or changing status never changes any session and never changes Progress totals.
- Personal notes can be written with bold, italic, bulleted lists, and numbered lists, and pasted text is cleaned in the same safe way as elsewhere in the app. Unsafe content is never saved or shown.
- Notes save automatically, a saved indicator is shown, a failed save keeps the text on screen with a calm message, and a refresh shows everything that was saved.
- Star, status, and notes are the same for an item wherever it appears.
- Fixing a typo in a word in a session keeps the item's star, status, and notes when it was the only appearance.
- A grammar note keeps its star, status, and notes when its text is edited, even a lot, and two grammar notes with identical text have separate marks.
- The status is only ever changed by the learner, and never changes automatically.
- Editing an entry so it becomes a different item, when the old item still appears elsewhere, leaves the old item with its marks and treats the edited text as its own item.
- When an item disappears (its only session is archived or deleted), the star, status, and notes are kept quietly and return when the item appears again.
- Two tabs changing the same star or status show correctly after the next load, and two tabs editing the same notes show a calm message with nothing lost.
- Marks and notes work on items marked "Needs attention" and on every collection.
- Star and status controls have clear accessible names that include the item, work with the keyboard, and announce changes calmly.
- Everything works on a phone, tablet, and computer, in light and dark mode.

### Step 5: Search, filters, and sorting

**What it delivers:** Search within each collection, "starred only," status and topic filters, sorting, removable filter chips, result counts, and the whole state kept in the page address.

**Tests**
- Search finds items by their text, meanings, examples, reasons, and the learner's notes, ignoring capitalization, accents, extra spaces, and formatting, and matching parts of words.
- Typing several words finds only items where all of them appear together.
- Special characters, very long text, and non-English letters never cause errors or odd results.
- "Starred only" shows only starred items.
- The status filter shows all, not marked, learned, or still difficult items.
- The topic filter shows items that appeared in sessions with the chosen topic, and lists only topics that currently feed Collections.
- "Needs attention only" shows only items marked with "Needs attention" in Vocabulary, Phrases, and My mistakes, and the header shows how many (for example "3 need attention"), disappearing when none do.
- Adding the missing meaning or correction in the session removes the item from the "Needs attention only" list straight away.
- Newest, A to Z, and most often seen each give the correct order, with a consistent order for ties. My mistakes defaults to most often seen, and the other collections default to newest.
- Filters combine correctly, appear as removable chips, and Clear all removes them all.
- The result count is correct (for example "12 of 180 words") and is announced calmly.
- When nothing matches, a friendly message with Clear all appears, never a blank page.
- The collection, search, filters, and sorting are kept in the page address, survive a refresh, and are restored when returning from a session. Invalid values in the address are ignored quietly.
- Search and filters stay fast with thousands of items, with a bounded number of database queries.
- The controls work on a phone, tablet, and computer, in light and dark mode, with the keyboard and a screen reader.

### Step 6: Staying in step, and polish

**What it delivers:** Collections that always follow the learner's sessions, plus a full check of speed, wording, accessibility, and consistency.

**Tests**
- Finishing a session makes its items appear straight away, and an unfinished session never contributes.
- Editing a finished session updates the affected items, and adding or removing entries is reflected.
- Archiving a session removes its items from Collections (unless they appear elsewhere), restoring brings them back with the learner's marks, and deleting removes them.
- Archived and deleted sessions never appear in an item's list of places it came from.
- Counts on the tabs and in the lists are always correct after every kind of change.
- Collections are never stale and never mix in an out-of-date result, and only the latest search or filter updates the screen.
- Progress totals are unaffected by archiving, while Collections hide archived items, and the archive messages make that clear.
- A full journey works: finish a session, find its words in Vocabulary, star one, mark one as still difficult, write a note, open the original session, edit the word's typo, return, see the star, status, and note kept, then archive the session and see the item leave and return on restore.
- Wording is friendly and consistent with the rest of the app, and nothing makes the learner feel judged.
- All screens work on a phone, tablet, and computer, with no sideways scrolling, and at 200 percent zoom.
- All text, labels, badges, stars, and focus outlines have good contrast in light and dark mode, and no meaning relies on color alone.
- Everything is reachable and usable with the keyboard alone, and key changes are announced by a screen reader without being chatty.
- Loading, empty, no-results, and error states are friendly and tell the learner what to do next.
- Every page and action stays fast with 5,000 items, and no query runs once per item where one query for the page would do.
- A learner can reach only their own items, marks, and notes, and saved text can never inject anything into the page.
- Interface pieces come from shadcn-vue wherever a suitable component exists, any custom piece is built from shadcn-vue parts, and no other component library has been added.

## Decisions Made

- **Marks are kept when an item disappears.** A star, status, and notes are kept quietly and return when the item comes back (for example when an archived session is restored).
- **Grammar notes are never merged.** Every grammar note is its own item, and its star, status, and notes belong to the note itself, so editing the note keeps them.
- **Only the learner sets "Learned" and "Still difficult."** The app never changes a status automatically. Review may later suggest a change, and the learner confirms it.
- **There is a "Needs attention only" filter**, with a count, in Vocabulary, Phrases, and My mistakes.
- **No bulk starring or marking for now.** A one-tap star and a quick status choice on each row are enough. This can be reconsidered after Review is built.

## Open Questions

None at the moment.

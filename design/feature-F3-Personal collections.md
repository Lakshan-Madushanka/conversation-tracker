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
- An item's **most recent appearance** is the one in the session with the latest session date, ties broken by the one created last, and the list shows text from it.
- The learner's marks are stored as **one record per item**, with a plain-text copy of notes for searching. Marks for items that disappear are kept and never deleted automatically.
- **Collections hide archived sessions, while Progress totals still count them.** The learner is told this wherever archiving happens.
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
2. It has four collections, shown as tabs in this order: **Vocabulary**, **Phrases**, **Grammar notes**, and **My mistakes**. Each tab shows its **total number of items** (for example "Vocabulary (180)"). The total never changes with search or filters. Vocabulary is the default tab, and the tabs work with the keyboard (arrow keys).
3. The chosen tab, search, filters, sort, and opened item are kept in the **page address**, so a refresh or a bookmark opens the same view (details under "Finding and focusing" and "Opening an item").
4. Collections are built **automatically** from the finished sessions that are not archived. The learner never adds items by hand and never has to refresh.
5. There are three different empty situations, each with its own friendly message: **no finished sessions at all** (a page-level message with a button to start a session), **a collection with no items** (its own message, for example "No words yet. Words you save in finished sessions will appear here."), and **no results** for the current search or filters.
6. Items from unfinished sessions never appear.

### Items in a collection

7. Each **word, phrase, and mistake** is shown once, even if it appears in several sessions, and is identified by its main text (the word, the phrase, or the pair of what was said and the correction), ignoring capitalization, accents, extra spaces, and formatting. **Grammar notes are never merged**: each note is shown as its own item, even when two notes have identical text.
8. An item's **most recent appearance** is the one in the session with the **latest session date**, ties broken by the one created last. The list shows the spelling, meaning, example, and reason from the most recent appearance that has them. The opened item shows all distinct ones.
9. A **vocabulary** item shows the **word**, its **meaning**, and its **example**. On wide screens the list is a table (Word, Meaning, Example), and on phones a card.
10. A **phrase** item shows the **phrase**, its **meaning or use**, and its **example**. A phrase **without a meaning** shows a quiet text such as "No meaning added" and is **not** marked "Needs attention", because that is normal for phrases.
11. A **grammar note** item shows a short **plain-text preview** in the list (about 160 characters, formatting removed, with an ellipsis when cut), the session's **topic**, and the session's **date**. On wide screens the list is a table (Note, Topic, Date). The **full formatted text** is shown when the note is opened.
12. A **mistake** item shows **what I said**, **the correction** (with its light formatting shown safely), and **the reason**, or quiet text such as "No reason added" when there is none. On wide screens the list is a table (What I said, Correction, Why, Seen).
13. Every item shows a **star**, a clear **status label** ("Learned" or "Still difficult", as text, and nothing when no status is set), a **"Has notes"** indicator when the learner has written notes, and a **"Seen N times"** label. For words and phrases the label appears **only when the item has more than one appearance**. For mistakes it is **always shown**, with correct singular wording ("Seen 1 time").
14. The **"Needs attention"** marker always comes from the **one shared rule used across the app**, with the same wording and look as elsewhere. A word is marked when **none** of its appearances has a meaning ("Add the meaning") or when its row had more than one separator ("More than one separator found. Please split it yourself."). A phrase is marked **only** when its row had more than one separator. A mistake is marked when it has no correction ("Add the correction") or its row had more than one separator. Grammar notes are never marked. Marked items are **never hidden** and offer a way to open the original session to fix them.
15. When the same word or phrase has **different meanings or examples** in different sessions, the list shows the most recent, and the opened item shows all of them, each with the sessions it came from.
16. An item that appears more than once in the **same box of the same question** (a repeated row flagged as a duplicate) counts as **one appearance**. The same item in two different questions or sessions counts as **two**.
17. Long meanings, examples, reasons, and notes are shortened in the list and complete when opened.
18. Lists load **30 items at a time**, with a **Load more** button always available and more loading as the learner reaches the end. The order is stable, so loading more never repeats or skips an item. The default order is **newest first** (the most recent session date in which the item appeared, with consistent tie-breaks, and for grammar notes the note's position in its session), and for **My mistakes** it is **most often seen**.

### My common mistakes

19. **My mistakes** groups the learner's mistakes by the **pair** of what they said and the correction, so the same mistake across sessions appears as one item.
20. **The same mistake with different reasons is one group**, and the opened item lists every distinct reason with its sessions. **The same correction for different mistakes is separate groups.** A row **without a correction** is grouped by what was said alone, and is a **separate group** from the same text that has a correction elsewhere.
21. "Seen N times" counts each appearance in a finished session that is not archived, with a repeated row in the same box of the same question counted once.
22. By default the most frequent mistakes come first, with ties ordered by the most recent session date, then alphabetically by what was said.
23. Groups seen **three or more times** carry a quiet, friendly **"Worth practicing"** label, shown in the list and in the opened item. It is text, never color alone, and the threshold lives in one clearly named setting.
24. On phones each mistake is a card with clearly labeled lines ("What I said", "Correction", "Why"), never relying on an arrow alone.
25. The tone is **encouraging**. The tab introduces itself in friendly words (for example "These are the mistakes you see most often. They're the best things to practice, and spotting them is how you improve."), the empty state is positive, and nothing on the tab calls a mistake a failure.

### Opening an item

26. An item opens in a **panel** (a side panel on larger screens, a bottom panel or full screen on phones). The panel focuses its heading, traps focus, closes with Escape or a close button, and returns focus to the item.
27. The opened item shows all its text, all distinct meanings, examples, or reasons (each with the sessions where it appeared), the "Seen N times" and "Worth practicing" labels where they apply, the learner's star, status, and personal notes, the "Needs attention" marker when it applies, and a **"Seen in"** list with one line per appearance: the session's **topic and date**, the **question number**, and a **"See original session"** link.
28. **"See original session"** opens the session's reading page **at that item**, scrolled to it and briefly highlighted, using the same "open at item" behavior as the Library's search results. When the item appears in several sessions, each opens at its own occurrence.
29. The "Seen in" list shows **only sessions that currently feed Collections**. Archived and deleted sessions never appear as places an item came from.
30. The opened item is part of the page address. **Opening it adds a history entry**, so the browser's **Back button closes the panel**. If the item no longer exists when the address is opened, the page opens normally with a calm note and no error.
31. **Returning** from a session brings the learner back to the same tab, search, filters, sort, loaded items, scroll position, and opened item.

### Star, status, and personal notes

32. **Star:** every item has a star control in the list and in the opened item. In the list it is a **separate control from the row's open action**, so starring never opens the item and opening never stars it. It has an accessible name that includes the item, and its state is announced. It updates immediately, and the request carries the **desired state** (never "toggle"), so retries and two tabs cannot flip it. If saving fails, it returns to its previous state with a calm message.
33. **Status:** the learner can mark an item **Learned** or **Still difficult**, or clear it. An item has at most one status. In the list, a small quick menu on each row changes it. In the opened item, a two-option group does, and choosing the active option again clears it. It updates immediately, carries the desired state, and returns to its previous state with a calm message if saving fails. **Only the learner ever sets the status.** The app never changes it automatically.
34. **Personal notes:** the opened item has a notes area with simple formatting (bold, italic, bulleted and numbered lists, and line breaks) and the same safe handling of pasted text as elsewhere in the app. The server cleans the formatted text itself and stores a plain-text copy for searching. Notes **save automatically** with a visible indicator ("Saving…", "Saved", "Not saved. Retrying…"), retries, and a quiet Retry action. A failed save keeps the text on screen. Leaving the page makes a best effort to save first and warns only when something could not be saved. If the same notes are changed in another tab, a calm message offers **load the latest version** or **keep what I have**, and nothing is lost until the learner chooses. A note with only blank space counts as no note. Notes allow a generous length (about 10,000 characters), and beyond it the learner is told clearly.
35. Items with notes show a small **"Has notes"** indicator in the list.
36. **Marks belong to the item.** For words, phrases, and mistakes, star, status, and notes belong to the item's identity, so they are the same wherever it appears. For a **grammar note** they belong to **that note itself**.
37. **Fixing a typo in a session keeps the item's marks.** The rule applies when an entry in a **finished, non-archived session** is edited so that its identity changes. If the **old item has no other appearance**, the marks **move** to the new identity. If the **old item still appears elsewhere**, it **keeps** its marks and the edited text is its own item with none. If the **new identity already has marks**, the existing marks are **kept**, nothing is overwritten, and the old marks stay quietly stored under the old identity. Changes only to capitalization, accents, spacing, or formatting move nothing. Edits in **unfinished** sessions move nothing. The rule runs in the **same save** as the session edit and is safe to repeat. A **grammar note** keeps its marks whenever its text is edited.
38. When an item **disappears** (its only session is archived or deleted, or the entry is removed), the learner's marks are **kept quietly** and **return** when the item appears again, for example when an archived session is restored. For a grammar note in an archived session, they return when the session is restored. Marks for a permanently deleted note simply stay stored and unused.
39. Changing a mark **never changes any session, tab count, or Progress total.** There is at most **one record per item**, even if requests repeat or arrive together, and a learner can reach only their own marks and notes.
40. Changing a star or status in one tab shows correctly in another tab after its next load, with no flipping.

### Finding and focusing

41. Each collection has a **search box**. Results appear **as the learner types, after a short pause**, once at least **two characters** have been entered (with fewer, a gentle hint appears). Very long queries are limited and the learner is told. **Escape** clears the box. Only the **latest** search updates the screen. Search works **within the active collection** only.
42. Search covers **all the text of an item**: for words the word and **every** meaning and example, for phrases the phrase and every meaning or use and example, for mistakes what I said, the correction, and **every** reason, for grammar notes the note's text, and, for every item, the **learner's own notes**. It ignores capitalization, accents, extra spaces, and formatting, matches parts of words, and treats symbols and punctuation as ordinary text. When several words are typed, **all must appear in the same item**.
43. Matching text is **highlighted** in the list and the opened item, in a way that does not rely on color alone and is built safely from plain text. When an item matches only in text that is not visible in its row (another meaning, another example, another reason, or the learner's notes), the row shows a quiet **"Matched in …"** line saying where.
44. **Filters:** **Starred only**; **Status** (all, not marked, learned, still difficult); **Needs attention only**, in Vocabulary, Phrases, and My mistakes, with a **"N need attention"** count (for example "3 need attention") that covers the **whole collection**, ignores other filters and search, and disappears when none do; and **Topic**, where the learner can choose **one or several topics** and an item matches when **any of its appearances** is in a session with any chosen topic. The topic list contains only topics of sessions that currently feed Collections **and** have items in the active collection, each with its number of items, alphabetically and searchable.
45. Filters **combine**: an item must match every kind of filter that is set and the search, and within topics it matches any chosen topic.
46. Active filters appear as **removable chips** (one for starred only, one for a status other than all, one for needs attention only, and one for each chosen topic). **Clear all** removes only the filters, and the search box's own clear button removes only the search. Focus moves sensibly after either.
47. The learner can **sort** by **newest**, **A to Z** (by the item's main text, ignoring capitalization and accents), and **most often seen**. The defaults are newest for Vocabulary, Phrases, and Grammar notes, and most often seen for My mistakes. **Grammar notes do not offer "most often seen"**, because they are never merged. Equal values are ordered consistently, and sorting creates no chip.
48. The **result count** above the list reads like "12 of 180 words" with filters or search active and "180 words" without, using the right noun for each collection (words, phrases, notes, mistakes) and correct singular wording. It is always correct and announced calmly once. When **nothing matches**, a friendly message offers **Clear all** (when filters are active) and **Clear search** (when there is a search). Loading shows skeletons with no layout jump, and a failure shows a calm message with Try again.
49. When a star or status change **in the list** makes an item stop matching the active filters, it **leaves the list immediately**, the count updates, and focus moves to the next row. Changes made **inside the opened item** update the list when the panel closes.
50. **Address behavior:** search, filters, and sort **replace** the current address instead of adding history entries. **Switching tabs** starts the new tab with its own defaults. Changing the search, a filter, or the sort starts a fresh list from the top. Unknown, outdated, or invalid values in the address are ignored quietly, with no error page.
51. On phones, the search box stays at the top, and the filters and sort open in a **panel** from a **Filters** button that shows how many filters are active, with Done and Clear all.

### Staying up to date

52. Collections update **straight away** when a session is finished, edited, archived, restored, or deleted, one at a time or in bulk. New items appear, edited items change (including an item moving to a different item or group when its text changes), archived or deleted items disappear, and restored items return, without a refresh. No part of Collections keeps a copy that can go out of date.
53. Counts on the tabs, in the lists, and on the labels are always correct.
54. Archiving never affects Progress totals, but it does hide items from Collections, and the learner is told so. The archive confirmation messages (for one or several sessions), the "This session is archived" banner, and the Archived view say, calmly and reassuringly, that archived sessions are **hidden from the Library and Collections** and can be restored any time. The delete warning says that the session will leave the Library, Collections, and Review.

### Experience

55. Collections work comfortably on a phone, tablet, and computer. On a phone, items appear as easy-to-tap cards, the tabs scroll sideways if needed, and an opened item takes a bottom panel or the whole screen. Nothing scrolls sideways, and the bottom navigation never covers the end of a list.
56. Everything works with the keyboard and a screen reader, with visible focus, names for stars and status controls that include the item, "What I said" and "Correction" read as words, and calm announcements for changes (starring, status, saved notes, result counts, items leaving the list).
57. Wording is friendly and encouraging. Nothing about Collections makes the learner feel judged by the number of mistakes or difficult items.

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
- **A phrase without a meaning:** shown with quiet "No meaning added" text and no marker.
- **A word or phrase whose row had more than one separator:** marked "Needs attention" with the reason "More than one separator found", using the one shared rule.
- **The same mistake text with and without a correction:** two separate groups, only the one without a correction marked.
- **The typo rule when the new identity already has marks:** the existing marks are kept, nothing is overwritten, and the old marks stay stored under the old identity.
- **An edit in an unfinished session:** moves no marks.
- **Unstarring while "Starred only" is on:** the item leaves the list immediately, and the count and focus update.
- **Changing a mark inside the opened item:** the list updates when the panel closes.
- **Search matching only hidden text** (another meaning, example, reason, or the learner's notes): the row shows a quiet "Matched in …" line.
- **Switching tabs:** the new tab starts with its own defaults, so a filter for one collection never hides items in another.
- **Invalid values in the address** (a missing topic, a misspelled sort, "Needs attention only" on Grammar notes, a too-short search): ignored quietly, with no error page.
- **Marks for a permanently deleted grammar note:** stay stored and unused, with no errors.
- **Two tabs editing the same notes:** a calm conflict message with "load the latest version" or "keep what I have", and nothing lost.
- **Archiving a session:** items leave Collections but still count in Progress totals, and the archive messages say so.
- **Slow or failed loading:** a clear loading state, and on failure a calm message with a way to try again.

## Technical

This describes the intended approach at a high level, using the agreed stack (Laravel, Inertia, Vue, Tailwind, shadcn-vue). It is a plan to refine, not a final design.

1. **Build on what exists.** Use the entries saved by Practice sessions, the "study sessions" rule from the Library (finished and not archived), the shared text matching from the Library's search, the reading page with its "open at item" behavior, the shared labels and color tags, and the "Needs attention" marker.
2. **Define what an item is.** Decide, in one place, how entries are identified as the same item: the normalized main text for words and phrases, and the pair for mistakes, with a repeated row in one box counting once. **Grammar notes are never merged**, so each note keeps its own identity. Keep this identification separate from the screens so every part of Collections, and later Review, uses the same definition.
3. **Gather items from sessions.** Build each collection from the entries of the study sessions, grouping them into items with how often they were seen, their meanings, examples, reasons, and the places they came from. Work this out efficiently in bulk, with a bounded number of database queries however many items there are, and keep it correct as sessions change.
4. **Store the learner's own marks.** Add a place to keep the learner's star, status, and notes, with **one record per learner and item**. For words, phrases, and mistakes, tie them to the item's identity, keep them when an item disappears (so they return with it), and let them follow an item when its text is edited under the typo rule, which runs **inside the same save** as the session edit. For grammar notes, tie them to the note itself. Store notes as cleaned formatted text plus a normalized plain-text copy for searching. Status is only ever set by the learner. Star and status requests carry the desired state, so they are safe to repeat.
5. **Create the Collections page.** A Vue page in the main app layout with a page header, four tabs with counts, and a list area per collection. Use shadcn-vue components wherever possible (see "UI components" below).
6. **Create the item list.** Build one reusable list that adapts to each collection (vocabulary, phrases, grammar notes, mistakes), showing the item, its meaning or correction, the star, the status label, and the seen count, with loading, empty, no-results, and error states.
7. **Create the opened item.** A panel or screen with the full details, the sessions the item came from with "See original session" links, the star, the status buttons, and a personal notes editor reusing the app's rich text editor and saving behavior.
8. **Group mistakes.** Add the grouping, frequency counts, default ordering by most seen, and the occurrences view for My mistakes.
9. **Add search, filters, and sorting.** Reuse the Library's text matching and filter patterns for search (over all of an item's text and the learner's notes, with safe highlighting and "Matched in" hints), starred only, status, needs attention only, topic (through each item's appearances), and sorting. Keep all choices in one filter state per collection, in the page address, and put the rules for which items match in one place on the server, shared by the list and the result count.
10. **Keep Collections in step.** Make sure finishing, editing, archiving, restoring, and deleting a session update Collections straight away, and that counts are always correct, without keeping separate copies that can go out of date.
11. **Share the "Needs attention" rule and keep the wording right.** Use the one shared needs-attention rule for words, phrases, and mistakes, and update the archive messages, the archived banner, and the Archived view to say that archived sessions are hidden from Collections.
12. **Connect to other features.** Expose the items, stars, and statuses in a stable form for Review, include the learner's marks and notes in future exports, and keep Progress separate from the learner's "Learned" label.
13. **Make it work everywhere.** Test on phone, tablet, and computer, in light and dark mode, with the keyboard and a screen reader, and with large numbers of items.

### UI components: use shadcn-vue wherever possible

All interface pieces in Collections should come from **shadcn-vue**, the component library already chosen for the app. Only build a custom piece when shadcn-vue has nothing suitable, and then build it by **composing shadcn-vue parts with Tailwind**. Do not add any other component library.

| Collections need                                | shadcn-vue component to use                                                      |
| ----------------------------------------------- | -------------------------------------------------------------------------------- |
| The four collection tabs with counts            | Tabs                                                                             |
| Item rows and cards                             | Card, or Table for wider screens with stacked cards on phones                    |
| Star button                                     | Toggle (with an accessible label naming the item)                                |
| Status label and "Needs attention" marker       | Badge (text, never color alone)                                                  |
| Status choice (Learned, Still difficult, clear) | Toggle Group, or Dropdown Menu in the list                                       |
| Opened item                                     | Sheet (side panel on larger screens, bottom or full-screen on phones), or Dialog |
| Search box                                      | Input with a clear button                                                        |
| Starred only, status, and topic filters         | Toggle, Toggle Group, Select, and Combobox                                       |
| "Needs attention only" filter and its count     | Toggle, with Badge for the count                                                 |
| Sort control                                    | Select                                                                           |
| Active filter chips                             | Badge with a small Button to remove each                                         |
| Personal notes                                  | The app's existing rich text editor, with Button for formatting                  |
| Saved indicator and messages                    | Badge or text with Sonner (toast)                                                |
| "Show more" for long text or sessions list      | Collapsible                                                                      |
| Loading, empty, and error states                | Skeleton, Alert, and the existing empty-state piece                              |
| Dividers and layout details                     | Separator, Scroll Area                                                           |

Where a shadcn-vue component needs small changes, change its **variants and Tailwind classes** instead of replacing it, so Collections stays visually consistent with the rest of the app, in light and dark mode, and keeps the accessibility built into the components.

## Implementation Steps

The steps are ordered so each one leaves a usable, reviewable piece of the feature.

### Step 1: The Collections page and the Vocabulary collection

**What it delivers:** A Collections page with the four tabs and counts, the automatic gathering of items from finished, non-archived sessions, and the Vocabulary collection as a read-only list with an opened item view and "See original session" links. The other three tabs show a friendly "coming soon in the next step" state only if needed during development, and are completed in the next steps.

**Tests**

- The Collections link in the main navigation opens the page, and the tab choice is kept in the address and survives a refresh.
- The four tabs appear in order, arrow keys move between them, and the tabs not built yet show a calm "on its way" message with no count and no fake data.
- The first 30 words load, more load when reaching the end and with Load more, with no duplicates or gaps.
- The opened word is kept in the address, Back closes the panel, and an address for a word that no longer exists opens the page with a calm note and no error.
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
- A phrase without a meaning shows the quiet "No meaning added" text, and a phrase whose row had more than one separator is marked "Needs attention" by the shared rule.
- Vocabulary uses the same shared needs-attention rule, so a word whose row had more than one separator shows the right reason.
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
- The "Seen N times" label is always shown for mistakes, with correct singular wording ("Seen 1 time").
- Groups seen three or more times show the "Worth practicing" label as text, and the threshold is one named setting.
- The same mistake text without a correction in one session and with a correction in another gives two separate groups.
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
- The star in a list row is a separate control from the row's open action, and its accessible name includes the item.
- Star and status requests carry the desired state, so repeated or simultaneous requests never flip them, and a failed save returns them to the previous state with a calm message.
- The list's quick status menu and the opened item's status group both work, and choosing the active option in the opened item clears it.
- An item can be marked Learned or Still difficult, or cleared, from the list and from the opened item, and has at most one status at a time. The status label is text, not color alone.
- Starring or changing status never changes any session and never changes Progress totals.
- Personal notes can be written with bold, italic, bulleted lists, and numbered lists, and pasted text is cleaned in the same safe way as elsewhere in the app. Unsafe content is never saved or shown.
- Notes save automatically, a saved indicator is shown, a failed save keeps the text on screen with a calm message, and a refresh shows everything that was saved.
- Two tabs editing the same notes show a calm message with "load the latest version" and "keep what I have", and nothing is lost until the learner chooses.
- The server cleans pasted notes itself, a plain-text copy is stored, notes with only blank space count as no note, and the notes length limit is enforced with a clear message.
- Items with notes show a "Has notes" indicator in the list.
- Star, status, and notes are the same for an item wherever it appears.
- Fixing a typo in a word in a session keeps the item's star, status, and notes when it was the only appearance.
- A grammar note keeps its star, status, and notes when its text is edited, even a lot, and two grammar notes with identical text have separate marks.
- The status is only ever changed by the learner, and never changes automatically.
- Editing an entry so it becomes a different item, when the old item still appears elsewhere, leaves the old item with its marks and treats the edited text as its own item.
- When the new identity already has marks, the existing marks are kept, nothing is overwritten, and the old marks stay stored.
- Changing only capitalization, accents, spacing, or formatting moves nothing, and editing an entry in an unfinished session moves nothing.
- The typo rule runs in the same save as the session edit, and running it twice changes nothing more.
- There is at most one record per learner and item, even with repeated or simultaneous requests.
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
- Search covers every meaning, example, and reason of an item and the learner's own notes, and an item that matches only in hidden text shows the correct "Matched in …" line.
- Matching text is highlighted without relying on color alone, and saved text can never inject markup.
- Special characters, very long text, and non-English letters never cause errors or odd results.
- "Starred only" shows only starred items.
- The status filter shows all, not marked, learned, or still difficult items.
- The topic filter shows items that appeared in sessions with the chosen topic, and lists only topics that currently feed Collections.
- The topic list contains only topics of sessions that currently feed Collections and have items in the active collection, with item counts, alphabetically and searchable, and an item matches when any of its appearances is in any chosen topic.
- The "N need attention" count covers the whole collection, ignores other filters and search, and "Needs attention only" is not shown for Grammar notes.
- "Needs attention only" shows only items marked with "Needs attention" in Vocabulary, Phrases, and My mistakes, and the header shows how many (for example "3 need attention"), disappearing when none do.
- Adding the missing meaning or correction in the session removes the item from the "Needs attention only" list straight away.
- Newest, A to Z, and most often seen each give the correct order, with a consistent order for ties. My mistakes defaults to most often seen, and the other collections default to newest.
- Filters combine correctly, appear as removable chips, and Clear all removes them all.
- Clear all removes only the filters, and the search box's clear button removes only the search.
- "Most often seen" is not offered for Grammar notes, and sorting creates no chip.
- Unstarring in the list while "Starred only" is on removes the item immediately with sensible focus, and changes made in the opened item update the list when the panel closes.
- Switching tabs starts the new tab with its own defaults.
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
- The archive messages (for one and for several sessions), the "This session is archived" banner, and the Archived view all say, calmly, that archived sessions are hidden from the Library and Collections and can be restored.
- The delete warning says the session will leave the Library, Collections, and Review.
- Every kind of session change, including bulk actions, gives the same Collections result as doing each one by one.
- Library search and Collections search find the same text the same way.
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
- **Items are identified by normalized text.** Words and phrases by their text, mistakes by the pair of what was said and the correction, and each grammar note by itself. The list shows the most recent appearance, and the opened item shows everything.
- **"Seen N times" counts appearances.** A repeated row in the same box of the same question counts once. Mistakes always show the count, and words and phrases only when seen more than once.
- **"Worth practicing"** is a quiet text label for mistakes seen three or more times, with the threshold in one named setting.
- **"Needs attention" comes from one shared rule.** A phrase without a meaning is not flagged. A word is flagged when none of its appearances has a meaning.
- **Star and status requests carry the desired state**, and the star and status controls are separate from a row's open action.
- **The typo rule** moves marks only when the old item has no other appearance, never overwrites existing marks, ignores unfinished sessions, and runs in the same save as the session edit.
- **Search covers every meaning, example, reason, and the learner's notes**, requires all words in the same item, highlights matches safely, and shows "Matched in …" for hidden matches. It works within one collection.
- **The topic filter** lists only topics with items in the active collection. The **"N need attention" count** covers the whole collection.
- **Address behavior:** search, filters, and sort replace the address. Opening an item adds a history entry so Back closes the panel. Switching tabs resets to that tab's defaults.
- **Marks changed in the list** make a no-longer-matching item leave the list immediately. Changes in the opened item apply when the panel closes.
- **Archived sessions are hidden from Collections but still count in Progress**, and the archive messages, banner, and Archived view say so.

## Open Questions

None at the moment.

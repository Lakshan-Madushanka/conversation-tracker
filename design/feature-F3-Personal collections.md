# Collections

## Summary

Collections are the **combined study index** for eligible practice sessions and saved [Notebook](feature-Notebooks.md) entries, not an editable notebook container. The app automatically gathers content into four collections: **Vocabulary**, **Phrases and expressions**, **Grammar notes**, and **My common mistakes**. Notebooks contribute vocabulary, phrases, and grammar notes only. The learner can browse each collection, **star** the items that matter most, mark items as **learned** or **still difficult**, add **personal notes**, and jump from any item back to its session or notebook.

Collections fill themselves: finishing a non-archived session or successfully saving notebook entries is enough. The point is to make it easy to see, at a glance, what the learner has collected, what keeps going wrong, and what is worth practicing next. Matching text in different notebooks or in a notebook and sessions remains separate study items.

**Status:** Notebook integration below is a planned requirement, not verified implementation. Resolve the linked open decisions before implementing the affected behaviour; acceptance coverage here is not a record of checks run.

### Assumptions

- The app is for **one learner** (personal use). Everything in Collections belongs to that learner.
- The **Practice sessions** and **Library** features already exist. Sessions have a topic, a date, questions, and feedback entries, and a status of finished or unfinished. Sessions can be archived, restored, and deleted.
- **Session sources must be finished and not archived.** This is the Library's "study sessions" rule. Unfinished sessions never appear; archived sessions are hidden and come back if restored; deleted sessions' content is gone. **Saved notebook entries also feed Collections**, with no finish or archive workflow. Notebooks use hard deletion.
- Session collections use **vocabulary** (word, meaning, example), **phrases** (phrase, meaning or use, example), **grammar notes** (formatted notes), and **mistakes** (what I said, the correction, and the reason). Notebooks supply only the first three kinds: no notebook mistakes, questions, answers, natural versions, or session dates. The **more natural versions** and the tutor's questions and answers are **not** part of Collections.
- If a build still has vocabulary, phrases, or mistakes as single-line entries instead of tables, Collections works with whatever text the entries contain.
- **Collections show saved source content.** To change the text of a word, phrase, note, or mistake, the learner edits its session or notebook. Collections let the learner change their own **star, status, and personal notes**, not source text; review selection uses the same scoped items.
- The **same word, phrase, or mistake can appear in several sessions**. Existing session grouping and seen counts stay unchanged. **Each notebook is a separate scope**: its matching word or phrase is one item within that notebook, never merged with sessions or another notebook.
- **Word and phrase identity is source scope, kind, and normalized main text**, ignoring capitalization, accents, extra spaces, and formatting. Session mistakes keep their existing identity: the normalized pair of "what I said" and "the correction". A notebook's scope is its identity, not its name or topic. Multiple comma-separated meanings remain one word item; repeated rows within that notebook do not create extra identities. **Grammar notes are never merged**: each saved row is its own item with its source checked.
- **Global star, status, and personal notes mean global for the scoped item**, wherever it is displayed, not global by spelling. Grammar marks belong to **that saved note itself**. Notebook name, description, or topic edits and shared-topic renames do not reset identity or marks.
- **"Learned" and "Still difficult" are the learner's own labels, and only the learner ever sets them.** The app never changes a status automatically. They do not change Progress totals, and Progress totals do not change them.
- A **session item's most recent appearance** is in the session with the latest session date, ties broken by the one created last. Notebook duplicate-row representative content and mixed-source date/frequency presentation remain [open decisions](feature-Notebooks.md#open-questions), not applications of session-date rules.
- The learner's marks are stored as **one record per scoped item**, with a plain-text copy of notes for searching. Marks for disappeared items remain under their original scope; matching text in another scope cannot recover them or keep a deleted item alive.
- **Collections hide archived sessions, while session-based Progress totals still count them.** The learner is told this wherever archiving happens. Notebook collection does not create finished sessions, session-content totals, practice days, or session-based streaks; exact Notebook daily/Quick review-activity metrics remain unresolved.
- Collections need an internet connection (offline use is an open question in the spec).

### Dependencies

- **Practice sessions:** the source of session entries, and the **reading page** and **open at item** behavior used to jump to the original session. Finishing a non-archived session makes its entries available.
- **[Notebooks](feature-Notebooks.md#collections-integration):** saved vocabulary, phrase, and grammar entries, notebook-scoped identities, shared topics, autosave, hard deletion, and **Open notebook** at the entry. The [scoped-mark rules](feature-Notebooks.md#scoped-identities-and-shared-marks) apply across surfaces.
- **Library:** the **study sessions** rule (finished and not archived), shared text matching that ignores capitalization, accents, and formatting, and archive, restore, and delete, reflected through existing refresh/navigation conventions.
- **Base app layout:** the main navigation (with a Collections link), the page header, tabs, and empty-state pieces.
- **[Review and re-practice (F4)](feature-F4-Review%20and%20re-practice.md):** uses eligible scoped items and the learner's stars and statuses. Content and eligibility must not borrow meanings or examples from another scope. Review may later **suggest** a status change (for example "You got this right 5 times. Mark it as learned?"), but the learner always confirms it.
- **[Custom reviews](feature-Custom%20reviews.md):** Add to review and bulk selection use distinct scoped identities. Membership remains fixed unless explicitly edited, source edits affect future content, and started attempts keep snapshots. Custom attempts and ratings do not change marks, daily schedules, recent daily/Quick results, streaks, or Progress.
- **Progress and motivation (F5):** existing finished-session content totals, practice days, and session-based streaks stay session-based and separate from the learner's "Learned" label. Notebook daily/Quick review-activity measures and deletion/history treatment require the [Notebook Progress decision](feature-Notebooks.md#open-questions).
- **Export and backup (F7):** the learner's stars, statuses, and personal notes should be included in downloadable data. Delivery of notebook parents, entries, scopes, and marks needs the separate Notebook export/backup decision; existing session exports are not assumed to cover them.
- **Import from spreadsheets (F6):** imported sessions, once finished, feed Collections like any other session.

### Related notes

- **Experience principles that apply:** simple first, calm and encouraging (mistakes are learning opportunities, never failures), quick to use on any device, distraction-free, and rewarding.
- **Spec goal:** make re-practicing easy and enjoyable. Collections are where the learner sees what to practice.
- **User flow in the feature list:** open Collections from the main menu, choose a collection and optionally a source/notebook, see each scoped item with its meaning and example, star the ones to focus on, open one to add a note and mark it "still difficult", jump to its session or notebook for context, and in My common mistakes see which session errors appear most often.
- **Screen in the feature list:** four tabs (Vocabulary, Phrases, Grammar, My mistakes). A list of scoped items with visible source provenance, meaning or correction, star, and status label. Source (All/Sessions/Notebooks), notebook, and shared Topic filters accompany sorting and "starred only". Panels show the full explanation, examples, notes, status buttons, and **See original session** or **Open notebook** as appropriate. In My mistakes, session items remain grouped with the most frequent first and a counter such as "Seen 5 times".
- **Existing visual reference:** the layout mockups show the general look of cards, tabs, and navigation. A specific mockup of the Collections screen has not been made yet.
- **Not part of this feature:** Review exercises, the Progress screen, editing the text of items, the more natural versions, and starring or marking several items at once (to be reconsidered after Review is built).

## Goals

- Let the learner see everything they have learned and every mistake they have made, in one organized place, without any extra work.
- Make it easy to find a particular word, phrase, note, or mistake, and to focus on what matters most (starred and difficult items).
- Show which mistakes keep coming back, so the learner knows what to work on.
- Let the learner keep their own marks (star, learned, still difficult) and notes on items, safely and permanently.
- Reflect eligible session changes and successfully saved notebook additions, edits, and deletions through existing load/navigation updates, without promising a real-time subscription.
- Keep it calm, fast, and easy to use on a phone as well as a computer.

## Requirements

### The Collections page

1. Collections is reachable from the main navigation, keeping its existing name and destination.
2. It has four collections, shown as tabs in this order: **Vocabulary**, **Phrases**, **Grammar notes**, and **My mistakes**. Each tab shows its **total number of scoped items** for the selected Source/notebook (for example "Vocabulary (180)"). This baseline total ignores search and the remaining filters. Vocabulary is the default tab, and the tabs work with the keyboard (arrow keys).
3. The chosen tab, search, filters (including Source and notebook), sort, and scoped opened item are kept in the **page address**, so a refresh or a bookmark opens the same view (details under "Finding and focusing" and "Opening an item").
4. Collections are built **automatically** from finished, non-archived sessions **and saved notebook vocabulary, phrases, and grammar notes**. The learner never adds items directly to Collections. Successfully saved content is reflected on the next load or through existing navigation/refresh behaviour; failed or pending notebook saves do not publish unsaved content.
5. There are three different empty situations, each with a friendly message: **no collectable content from either source** (offer starting a session or creating/opening a notebook), **a collection with no items** (for example "No words yet. Words saved in finished sessions or notebooks will appear here."), and **no results** for the current search or filters. Notebook content must not be hidden behind a no-finished-sessions state. My mistakes with notebook sources selected has an honest empty state, not invented notebook mistakes.
6. Items from unfinished or archived sessions never appear. The session finish/archive restriction does not apply to saved notebook entries; blank rows and formatting-only grammar notes do not become notebook items.

### Items in a collection

7. Each **word and phrase** is shown once per **source scope and kind**, using normalized main text (ignoring capitalization, accents, extra spaces, and formatting). Existing grouping across sessions is unchanged, including mistakes grouped by the pair of what was said and the correction. Each notebook has its own scope: a session's "bank" and two notebooks' "bank" are **three items**, even with identical meanings/topics. Comma-separated meanings and duplicate word/phrase rows within one notebook remain one item. **Grammar notes are never merged**: each saved note row keeps its own identity.
8. A **session item's most recent appearance** is in the session with the **latest session date**, ties broken by the one created last. Its list shows spelling, meaning, example, and reason from the most recent appearance that has them; its panel shows all distinct ones. Notebook duplicate-row representative content, distinct-value presentation, and incomplete/complete row eligibility require the [Notebook duplicate-row decision](feature-Notebooks.md#open-questions). Resolution must be deterministic, shared with Review, and confined to that notebook, not borrowed from session dates or another scope.
9. A **vocabulary** item shows the **word**, its **meaning**, and its **example**. On wide screens the list is a table (Word, Meaning, Example), and on phones a card. Rows visibly identify session or named-notebook provenance so equal spellings can be distinguished.
10. A **phrase** item shows the **phrase**, its **meaning or use**, and its **example**, with visible source provenance. A phrase **without a meaning** shows quiet text such as "No meaning added" and is **not** marked "Needs attention" for that reason, because that is normal for phrases. This does not make it eligible for a meaning flashcard.
11. A **grammar note** item shows a short **plain-text preview** (about 160 characters, formatting removed, with an ellipsis when cut), its source, and its **topic**. Session notes retain the session's **date** and wide-screen Note/Topic/Date presentation. Notebook notes show notebook provenance, never a fabricated session date or question number; mixed-source date presentation is an open decision. The **full formatted text** is shown when the note is opened.
12. A **mistake** item shows **what I said**, **the correction** (with its light formatting shown safely), and **the reason**, or quiet text such as "No reason added" when there is none. On wide screens the list is a table (What I said, Correction, Why, Seen). Mistakes remain session-only.
13. Every item shows a **star**, a clear **status label** ("Learned" or "Still difficult", as text, and nothing when no status is set), and a **"Has notes"** indicator when the learner has written notes. Existing session **"Seen N times"** semantics remain: words and phrases show it **only with more than one appearance**; mistakes **always** show it, with correct singular wording ("Seen 1 time"). Notebook rows show notebook provenance, not session appearances or "Seen 0 times". Notebook frequency presentation remains open.
14. The **"Needs attention"** marker comes from the **one shared rule used across the app**, with the same wording and look. For session words, none of the item's appearances having a meaning triggers "Add the meaning"; a row with more than one separator triggers "More than one separator found. Please split it yourself." A phrase is marked **only** for multiple separators, and a mistake for no correction or multiple separators. Grammar notes are never marked. Apply the shared rules to notebook content **within its scope**, with duplicate-row resolution pending the decision in requirement 8. Commas inside meanings are not entry/column separators. Attention and exercise eligibility must not be cleared or supplied by another scope's meaning/example. Marked items remain discoverable and offer their own source link to fix them.
15. When the same word or phrase has **different meanings or examples** in different sessions, the list shows the most recent, and the opened item shows all of them with their sessions. Notebook content remains within its notebook; comma-separated meanings are one item's content, not separate items. Duplicate-row display follows the unresolved requirement 8 decision.
16. A session item repeated in the **same box of the same question** counts as **one appearance**. The same item in two questions or sessions counts as **two**. Notebook duplicates retain their saved rows and warning but count as one scoped study identity, not extra custom-review slots; this does not define a notebook "Seen" count.
17. Long meanings, examples, reasons, and notes are shortened in the list and complete when opened.
18. Lists load **30 items at a time**, with a **Load more** button always available and more loading as the learner reaches the end. Ordering must be stable so pagination never repeats or skips scoped items. For session sources, **newest first** remains based on most recent session date, with consistent tie-breaks and grammar-note position; **My mistakes** defaults to **most often seen**. Notebook dates, cross-source tie-breaks, and frequency-sort presentation must be resolved before integration, not invented by this document.

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
27. The opened item shows its own scoped text, meanings/examples/reasons under requirement 8, applicable labels, star, status, personal notes, and attention marker. **Session panels** keep the **"Seen in"** list with topic, session date, question number, and **"See original session"** per appearance. **Notebook panels** show the notebook name, shared topic, relevant entry content, and **"Open notebook"** at the entry. They never show a session link, fabricated question/date, or meanings/examples from a matching item in another scope.
28. **"See original session"** opens the session's reading page **at that item**, scrolled to it and briefly highlighted, using the Library's "open at item" behavior. Each session occurrence has its own link. **"Open notebook"** opens the owning notebook **at the entry** using the corresponding source-navigation behaviour.
29. Provenance shows **only currently available sources of that scoped item**. Archived/deleted sessions and deleted notebook entries never appear as live source links. A missing source is not replaced by matching text in another scope.
30. The **scoped** opened item is part of the page address. **Opening it adds a history entry**, so the browser's **Back button closes the panel**. If it no longer exists, the page opens normally with a calm note and no error; it never resolves to a same-spelling item elsewhere.
31. **Returning** from a session or notebook brings the learner back to the same tab, search, filters (including Source and notebook), sort, loaded items, scroll position, and scoped opened item, subject to source availability.

### Star, status, and personal notes

32. **Star:** every item has a star control in the list and in the opened item. In the list it is a **separate control from the row's open action**, so starring never opens the item and opening never stars it. It has an accessible name that includes the item, and its state is announced. It updates immediately, and the request carries the **desired state** (never "toggle"), so retries and two tabs cannot flip it. If saving fails, it returns to its previous state with a calm message.
33. **Status:** the learner can mark an item **Learned** or **Still difficult**, or clear it. An item has at most one status. In the list, a small quick menu on each row changes it. In the opened item, a two-option group does, and choosing the active option again clears it. It updates immediately, carries the desired state, and returns to its previous state with a calm message if saving fails. **Only the learner ever sets the status.** The app never changes it automatically.
34. **Personal notes:** the opened item has a notes area with simple formatting (bold, italic, bulleted and numbered lists, and line breaks) and the same safe handling of pasted text as elsewhere in the app. The server cleans the formatted text itself and stores a plain-text copy for searching. Notes **save automatically** with a visible indicator ("Saving…", "Saved", "Not saved. Retrying…"), retries, and a quiet Retry action. A failed save keeps the text on screen. Leaving the page makes a best effort to save first and warns only when something could not be saved. If the same notes are changed in another tab, a calm message offers **load the latest version** or **keep what I have**, and nothing is lost until the learner chooses. A note with only blank space counts as no note. Notes allow a generous length (about 10,000 characters), and beyond it the learner is told clearly.
35. Items with notes show a small **"Has notes"** indicator in the list.
36. **Marks belong to the scoped item.** Star, status, and personal notes are shared wherever that identity is shown, including its notebook, Collections, and review context; matching text in another notebook or sessions has independent marks. A **grammar note** has marks for **that saved note itself**. Notebook name/description edits, topic reassignment, and shared-topic renames do not reset identity or marks. Personal notes are separate from notebook descriptions and entry content.
37. **Fixing a typo follows marks only within the source scope.** Existing rules apply to identity-changing edits in a **finished, non-archived session**. Notebook word/phrase edits apply the same rules **during successful autosave, within that notebook**. If the **old scoped item has no other appearance**, marks move to the new identity. If another old appearance remains in that scope (including a notebook duplicate row), the old item keeps its marks and a new unmarked identity gets none. **Destination marks are never overwritten**; if present, they stay and the old marks remain quietly stored under the old identity. Neither a session item nor another notebook is a destination for a notebook edit. Normalisation-only changes move nothing; unfinished session edits move nothing. The rule runs in the **same successful save** as the source edit, is safe to repeat, and does not run for failed/conflicting notebook saves. A **grammar note** retains its saved-entry identity and marks when edited.
38. When an item **disappears**, its marks remain quietly stored **under its original scoped identity**. Session archive/restore behaviour is unchanged: returning appearances in the same session grouping recover those marks, including the same archived grammar note on restore. Removing a notebook row leaves its scoped item alive only if another appearance remains **in that notebook**; deleting its last appearance or hard-deleting the notebook removes the item. Matching text in sessions or another notebook cannot keep it alive or rescue its marks/review reference. A newly created notebook, even with the same name and text, does not inherit old marks. Permanently deleted note identities and deleted notebook scopes leave stored marks unused.
39. Changing a mark **never changes source content, its last-updated information, tab totals, or Progress totals.** There is at most **one record per learner and scoped item**, even if requests repeat or arrive together, and a learner can reach only their own marks and notes. Custom-review attempts and ratings remain isolated: showing marks does not let an attempt change them, daily schedules/results, streaks, or Progress.
40. Changing a star or status in one tab shows correctly in another tab after its next load, with no flipping.

### Finding and focusing

41. Each collection has a **search box**. Results appear **as the learner types, after a short pause**, once at least **two characters** have been entered (with fewer, a gentle hint appears). Very long queries are limited and the learner is told. **Escape** clears the box. Only the **latest** search updates the screen. Search works **within the active collection** only.
42. Search covers **all the text of a scoped item**: for words the word and **every** meaning and example, for phrases the phrase and every meaning or use and example, for mistakes what I said, the correction, and **every** reason, for grammar notes the note's text, and, for every item, the **learner's own notes**. It ignores capitalization, accents, extra spaces, and formatting, matches parts of words, and treats symbols and punctuation as ordinary text. When several words are typed, **all must appear in the same scoped item**. Notebook duplicate-row search/display must agree with the resolved content rule; matching meanings or notes in another scope cannot make this item match. Source and notebook filters constrain results by identity, not by spelling.
43. Matching text is **highlighted** in the list and the opened item, in a way that does not rely on color alone and is built safely from plain text. When an item matches only in text that is not visible in its row (another meaning, another example, another reason, or the learner's notes), the row shows a quiet **"Matched in …"** line saying where.
44. **Filters:** **Source** is **All** (default), **Sessions**, or **Notebooks**; a **notebook picker** narrows notebook items to one notebook. Selecting a notebook selects Notebooks; switching to Sessions visibly clears the incompatible notebook choice. These select scoped identities, never an "appears only here" spelling test. Retain **Starred only**, **Status** (all, not marked, learned, still difficult), and **Needs attention only** in Vocabulary, Phrases, and My mistakes. Its **"N need attention"** count covers the active collection within Source/notebook, ignoring search and remaining filters, and disappears when zero. **Topic** remains shared, not a separate category: session items match any eligible appearance with a chosen topic; notebook items match their notebook's topic. Allow one or several topics, listed alphabetically and searchable, with scoped-item counts; available topics/notebooks must match relevant sources and items in the active collection.
45. Filters **combine**: an item must match each filter kind and search, and any chosen topic. All may show a session item and several same-spelling notebook items separately; no "both sources" merged identity is introduced. Eligible-only views, bulk selection, and **Add to review** use these same scoped identities and source-local eligibility. Notebook duplicates and comma-separated meanings do not add review slots; identical text in different scopes remains separate. This does not add bulk starring/status changes or change custom-review membership, limits, or isolation rules.
46. Active filters appear as **removable chips** for non-default Source, a selected notebook, starred only, non-default status, needs attention only, and each chosen topic. **Clear all** resets filters (including Source to All and notebook to none), not search; the search box's clear button removes only search. Removing/changing source chips must keep notebook/source state compatible. Focus moves sensibly after either.
47. Session sorting remains **newest**, **A to Z** (normalized main text), and **most often seen**, with newest the default for Vocabulary, Phrases, and Grammar notes, and most often seen for My mistakes. **Grammar notes do not offer "most often seen"**. A to Z uses scoped items, and equal values must be ordered consistently. Notebook date choice, cross-source tie-breaks, and frequency-sort presentation remain [open](feature-Notebooks.md#open-questions); do not assign notebook typing a session date or appearance count. Sorting creates no chip.
48. The **result count** reads like "12 of 180 words" when narrowed and "180 words" otherwise, with the right noun and singular wording. Both matched totals and the baseline denominator use distinct scoped identities in the selected Source/notebook; tab totals and attention/topic counts use the corresponding scope, never matching-spelling totals across sources. Counts are announced calmly once. When **nothing matches**, offer **Clear all** for active filters and **Clear search** for search; a notebook-only My mistakes view explains that notebooks have no mistakes. Loading shows skeletons with no layout jump; failure shows a calm message with Try again.
49. When a star or status change **in the list** makes an item stop matching the active filters, it **leaves the list immediately**, the count updates, and focus moves to the next row. Changes made **inside the opened item** update the list when the panel closes. Only that scoped item is affected.
50. **Address behavior:** search, filters (including Source/notebook), and sort **replace** the current address instead of adding history entries. **Switching tabs** starts the new tab with its own defaults, including All sources and no notebook selection. Changing search, a filter, or sort starts a fresh list from the top. Unknown, outdated, incompatible, or invalid values are ignored calmly, including deleted notebooks; source/notebook normalization must remain visible rather than leave a hidden incompatible filter.
51. On phones, the search box stays at the top, and the filters and sort open in a **panel** from a **Filters** button that shows how many filters are active, with Done and Clear all. Source and notebook controls are included and keyboard/screen-reader accessible.

### Staying up to date

52. On the **next load or existing refresh/navigation update**, Collections reflects session finishing, editing, archiving, restoration, and deletion (single or bulk), and successful notebook entry saves, removals, and notebook hard deletion. New scoped items appear, edits update their content or identity, and unavailable sources disappear; session restoration brings its appearances back. Notebook metadata/topic changes update provenance and filtering, not identity. Removing a last scoped appearance cannot be rescued by another scope's matching text. No cross-tab or cross-device real-time subscription is promised.
53. Tab, result, attention, topic, and selection counts agree with current source-scoped data whenever the view is loaded/updated. Unsaved or failed notebook edits do not change persisted Collections content or counts.
54. Archiving never affects Progress totals, but it does hide items from Collections, and the learner is told so. The archive confirmation messages (for one or several sessions), the "This session is archived" banner, and the Archived view say, calmly and reassuringly, that archived sessions are **hidden from the Library and Collections** and can be restored any time. The delete warning says that the session will leave the Library, Collections, and Review.

### Experience

55. Collections work comfortably on a phone, tablet, and computer. On a phone, items appear as easy-to-tap cards, the tabs scroll sideways if needed, and an opened item takes a bottom panel or the whole screen. Nothing scrolls sideways, and the bottom navigation never covers the end of a list.
56. Everything works with the keyboard and a screen reader, with visible focus, names for stars and status controls that include the item, "What I said" and "Correction" read as words, and calm announcements for changes (starring, status, saved notes, result counts, items leaving the list).
57. Wording is friendly and encouraging. Nothing about Collections makes the learner feel judged by the number of mistakes or difficult items.

### Edge cases

- **Very many items:** thousands of words, phrases, and mistakes list, search, and filter quickly.
- **Same word with different capitalization or accents:** one item within its scope and kind (for example "Café" and "cafe"), but separate across notebooks and sessions.
- **Same word with different meanings:** session grouping still lists meanings with their sessions. A notebook retains its own meanings; comma-separated meanings remain one item. Representative content for duplicate notebook rows remains an open decision.
- **Same mistake with different reasons:** one session item, listing each reason with its session.
- **Same correction for different mistakes:** separate items, because the pair is what identifies a mistake.
- **Missing meaning or correction:** shared attention and exercise-eligibility rules apply to source-local content, and the item can be opened, marked, starred, and annotated. Another scope's complete row cannot supply the missing content; notebook incomplete/complete duplicate resolution remains open.
- **Very long notes, meanings, or grammar notes:** accepted, tidy in the list (shortened), and complete when opened.
- **Special characters and non-English letters:** treated as ordinary text in the list, in search, and in notes.
- **An item whose session is later archived:** it disappears if that was its only eligible session appearance; other session appearances are unaffected. It returns with its marks on restoration. Matching notebook items are independent throughout.
- **An item whose only session is deleted:** it disappears and its marks remain quietly stored in the session scope; another notebook cannot keep it alive or inherit them.
- **A session finished after an item was starred:** the star is already there only if it matches the existing session identity, never just a notebook item's spelling.
- **A session or notebook edited so an item changes:** the typo rule applies within scope in the successful source save. A remaining notebook duplicate retains the old marks; an existing destination's marks are never overwritten. Failed or conflicting saves move nothing.
- **Notebook metadata or shared-topic rename:** provenance/filter labels change without resetting identities, marks, schedules, or custom-review membership.
- **Last notebook appearance removed or notebook hard-deleted:** the scoped item leaves Collections and future review selection; matching text elsewhere cannot rescue it or its review reference. A remaining duplicate in the same notebook keeps that identity available. Stored orphan marks stay in the original scope; recreating the notebook with the same name does not recover them.
- **A grammar note edited:** it keeps its saved-entry identity and marks, however much its text changes, in sessions or notebooks.
- **Two grammar notes with identical text:** two separate items, within or across sources, each with its own marks.
- **Two tabs open:** changing a star or status in one tab shows correctly in the other after its next load, and notes use the same careful saving as elsewhere, with a calm message if they conflict and nothing lost.
- **Unfinished session finished later:** its items become available when finished and appear on the next load or existing navigation update.
- **No finished sessions but saved notebook items:** show the notebook items normally, not a start-session empty state. With neither source providing items, offer both source paths.
- **Opening a changed or removed source:** use the source's calm missing-entry/unavailable-page behaviour. Never substitute a matching item from another scope.
- **A phrase without a meaning:** shown with quiet "No meaning added" text and no marker.
- **A word or phrase whose row had more than one entry/column separator:** marked "Needs attention" with the reason "More than one separator found", using the shared rule. Commas inside one meaning field do not split items or trigger this treatment.
- **The same mistake text with and without a correction:** two separate groups, only the one without a correction marked.
- **The typo rule when the new identity already has marks in the same scope:** the existing marks are kept, nothing is overwritten, and the old marks stay stored under the old scoped identity.
- **An edit in an unfinished session:** moves no marks.
- **Unstarring while "Starred only" is on:** the item leaves the list immediately, and the count and focus update.
- **Changing a mark inside the opened item:** the list updates when the panel closes.
- **Search matching only hidden text** (another meaning, example, reason, or personal notes in the same scoped item): show a quiet "Matched in …" line, never a match borrowed from another scope.
- **Switching tabs:** the new tab starts with its own defaults, including All sources and no notebook selection, so old filters never hide its items.
- **Source/notebook changes:** selecting a notebook selects Notebooks; switching to Sessions visibly clears that notebook choice. Topic remains the shared topic filter. Notebook-only My mistakes shows an honest empty result.
- **Invalid values in the address** (a missing topic/notebook, incompatible Source/notebook choice, a misspelled sort, "Needs attention only" on Grammar notes, a too-short search): handled calmly without an error page or a hidden incompatible filter.
- **Mixed-source sorting and notebook duplicates:** stable pagination, date/frequency presentation, and representative content require the Notebook open decisions; do not use fabricated session dates or incidental row ordering.
- **Marks for a permanently deleted grammar note:** stay stored and unused, with no errors.
- **Two tabs editing the same notes:** a calm conflict message with "load the latest version" or "keep what I have", and nothing lost.
- **Archiving a session:** items leave Collections but still count in Progress totals, and the archive messages say so.
- **Slow or failed loading:** a clear loading state, and on failure a calm message with a way to try again.

## Technical

This describes the intended approach at a high level, using the agreed stack (Laravel, Inertia, Vue, Tailwind, shadcn-vue). It is a plan to refine, not a final design.

1. **Build on what exists.** Reuse session and notebook entry content, shared normalization, labels, attention rules, and source navigation. The Library's "study sessions" rule remains session-only; saved notebooks do not need synthetic sessions/questions or a finish step. Follow the [Notebook technical boundaries](feature-Notebooks.md#technical); parent representation is still an open decision.
2. **Define scope once.** Preserve existing session identities. Notebook words/phrases use notebook identity, kind, and normalized main text; mistakes stay session-only. Comma-separated meanings and duplicate notebook word/phrase rows remain one study item. **Grammar notes retain their saved-entry identity**, with source validation. Content resolution, search, marks, counts, bulk selection, and Review must use the same scoped definition, not scope only the visible list.
3. **Gather items from eligible sources.** Compose bounded, bulk reads for session and notebook content, provenance, topics, and scoped counts. Preserve session appearance counts/date rules. Constrain content to its parent scope before resolving meanings/examples or eligibility. Resolve notebook duplicate-row content and mixed-source sort decisions before implementation; never use another scope or incidental row order as a fallback. Read paths must not mutate sources, marks, or review references.
4. **Store scoped marks.** Keep **one record per learner and scoped item** for star, status, and notes. The typo rule runs **inside the same successful source save**, confined to that scope: a remaining old appearance keeps its marks, destination marks are never overwritten, and failed notebook autosaves move nothing. Grammar marks stay tied to the saved note. Metadata/topic changes do not reset identity. Disappeared identities retain orphan marks in their original scope; hard deletion never relinks them to a matching item elsewhere. Store cleaned formatted notes and a normalized plain-text projection; only the learner sets status, and star/status requests carry the desired state.
5. **Create the Collections page.** A Vue page in the main app layout with a page header, four tabs with counts, and a list area per collection. Use shadcn-vue components wherever possible (see "UI components" below).
6. **Create the item list.** Reuse one list adapting to vocabulary, phrases, grammar notes, and session-only mistakes. Show scoped content, marks, and distinguishable provenance, keeping session seen counts and the resolved notebook presentation. Include loading, empty, no-results, and error states that work without finished sessions.
7. **Create the opened item.** Show scoped full details and marks with session context/**See original session**, or notebook name/topic/**Open notebook** at the entry. Reuse personal-note editing and saving. Source links, panel addresses, and return navigation must preserve scope; missing sources never fall back to matching text elsewhere.
8. **Group mistakes.** Keep session grouping, frequency counts, most-seen ordering, and the occurrences view for My mistakes. Notebooks contribute none.
9. **Add search, filters, and sorting.** Reuse normalized matching, safe highlighting, and "Matched in" hints within scoped content/notes. Add Source (All default/Sessions/Notebooks) and notebook selection alongside star, status, attention, and shared Topic. Selecting a notebook selects Notebooks; Sessions clears the incompatible notebook choice. Share server-side matching/count logic across lists, eligible-only views, and bulk/Add to review selection. Preserve filter/address state and resolve notebook sort rules before implementation.
10. **Keep Collections in step.** Reflect session lifecycle changes and successful notebook saves/removals/hard deletion on the next load or existing navigation refresh. Recompute scoped content/counts without promising real-time subscriptions. Write actions, not read queries, handle typo-following and deletion effects.
11. **Share attention rules and wording.** Apply attention/eligibility only to the scoped item's content, including the resolved notebook duplicate rule. A matching meaning elsewhere cannot repair it. Keep session archive messages, the banner, and Archived view clear that archived sessions are hidden from Collections.
12. **Connect without changing review or Progress contracts.** Expose stable scoped items/marks to Review and Add to review. Preserve fixed custom-review membership, live future content, attempt snapshots, and custom-attempt isolation. Existing session Progress totals/streaks remain unchanged; Notebook daily/Quick metrics and export/backup delivery need their separate decisions.
13. **Make it work everywhere.** Test on phone, tablet, and computer, in light and dark mode, with the keyboard and a screen reader, and with large numbers of items.

### UI components: use shadcn-vue wherever possible

All interface pieces in Collections should come from **shadcn-vue**, the component library already chosen for the app. Only build a custom piece when shadcn-vue has nothing suitable, and then build it by **composing shadcn-vue parts with Tailwind**. Do not add any other component library.

| Collections need                                                 | shadcn-vue component to use                                                      |
| ---------------------------------------------------------------- | -------------------------------------------------------------------------------- |
| The four collection tabs with counts                             | Tabs                                                                             |
| Item rows and cards                                              | Card, or Table for wider screens with stacked cards on phones                    |
| Star button                                                      | Toggle (with an accessible label naming the item)                                |
| Status label and "Needs attention" marker                        | Badge (text, never color alone)                                                  |
| Status choice (Learned, Still difficult, clear)                  | Toggle Group, or Dropdown Menu in the list                                       |
| Opened item                                                      | Sheet (side panel on larger screens, bottom or full-screen on phones), or Dialog |
| Search box                                                       | Input with a clear button                                                        |
| Source, notebook, starred only, status, and shared topic filters | Toggle, Toggle Group, Select, and Combobox                                       |
| "Needs attention only" filter and its count                      | Toggle, with Badge for the count                                                 |
| Sort control                                                     | Select                                                                           |
| Active filter chips                                              | Badge with a small Button to remove each                                         |
| Personal notes                                                   | The app's existing rich text editor, with Button for formatting                  |
| Saved indicator and messages                                     | Badge or text with Sonner (toast)                                                |
| "Show more" for long text or source details                      | Collapsible                                                                      |
| Loading, empty, and error states                                 | Skeleton, Alert, and the existing empty-state piece                              |
| Dividers and layout details                                      | Separator, Scroll Area                                                           |

Where a shadcn-vue component needs small changes, change its **variants and Tailwind classes** instead of replacing it, so Collections stays visually consistent with the rest of the app, in light and dark mode, and keeps the accessibility built into the components.

## Implementation Steps

The steps are ordered so each one leaves a usable, reviewable piece of the feature. **All Tests lists below are planned acceptance requirements, not evidence of implementation or checks run.** Session-specific regressions retain existing behaviour; notebook integration must satisfy the scoped rules above and the linked [Notebook acceptance plan](feature-Notebooks.md#step-3-collections-and-scoped-marks). Resolve the relevant open decisions before implementing or asserting notebook sort/duplicate behaviour.

### Step 1: The Collections page and the Vocabulary collection

**What it delivers:** A Collections page with four tabs and scoped counts, automatically gathering eligible session and saved notebook entries. Vocabulary is a read-only source-content list with panels and **See original session**/**Open notebook** links. The other three tabs show a friendly "coming soon in the next step" state only if needed during development and are completed in the next steps; this is not a complete Notebook integration until all stages are satisfied.

**Tests**

- The Collections link in the main navigation opens the page, and the tab choice is kept in the address and survives a refresh.
- The four tabs appear in order, arrow keys move between them, and the tabs not built yet show a calm "on its way" message with no count and no fake data.
- The first 30 words load, more load when reaching the end and with Load more, with no duplicates or gaps.
- The opened word is kept in the address, Back closes the panel, and an address for a word that no longer exists opens the page with a calm note and no error.
- Session vocabulary comes only from finished, non-archived sessions; saved notebook vocabulary also contributes without a finish step. Pending/failed saves, blank rows, unfinished sessions, and archived sessions do not contribute.
- Each word appears once within its scope/kind, with unchanged session normalization/grouping ("Café" and "cafe" merge only within scope). A session and two notebooks with the same word produce three items; comma-separated meanings remain one item.
- Each item shows its own word, meaning, example, and identifiable provenance. Session words retain existing appearance-count display; notebook words never show fabricated session dates or "Seen 0 times".
- Session words retain most-recent list content and all distinct meanings with session provenance in panels. Notebook duplicate-row content follows the resolved shared representative rule, never a session or another notebook's meaning/example.
- A repeated session row in the same box of the same question counts as one appearance; notebook duplicates do not inflate distinct identities.
- Missing meanings use shared attention rules within scope; another source's meaning cannot repair attention or review eligibility.
- Session panels show their sessions with topic/date; notebook panels show notebook name, shared topic, entry content, and **Open notebook**, with no session link.
- "See original session" opens the reading page at the correct appearance; "Open notebook" opens that notebook at its entry.
- Returning from either source preserves tab, Source/notebook, other filters, search, sort, scroll position, and scoped panel context.
- A notebook-only learner sees saved items, not the no-finished-sessions state. With no collectable items from either source, offer session and notebook paths; empty vocabulary has its own message.
- Tab counts match distinct items in the current Source/notebook view, and pagination has no duplicates or gaps once mixed-source ordering is resolved.
- With 5,000 vocabulary items, the page loads quickly and the number of database queries does not grow with the number of items.
- The page works on a phone, tablet, and computer, in light and dark mode, with no sideways scrolling.

### Step 2: Phrases and Grammar notes

**What it delivers:** The Phrases collection (phrase, meaning or use, example) and the Grammar notes collection (each note as its own item, with a short preview in the list and the full formatted text when opened), using the same list, item view, and links as Vocabulary.

**Tests**

- Phrases come from eligible sessions and saved notebooks, once per scope/kind/normalized phrase with source-local meaning/use and example. Session seen counts stay unchanged; notebook duplicate content/frequency presentation follows the resolved decisions.
- A phrase without a meaning or use is shown normally and is **not** marked "Needs attention", because that is normal for phrases.
- A phrase without a meaning shows the quiet "No meaning added" text, and a phrase whose row had more than one separator is marked "Needs attention" by the shared rule.
- Vocabulary uses the same shared needs-attention rule, so a word whose row had more than one separator shows the right reason.
- Grammar notes appear as separate items with a short plain preview in the list and the full formatted text (bold, italic, lists) when opened.
- Two grammar notes with identical text stay separate saved-entry identities, within one notebook or across sources; blank/formatting-only notebook notes do not become items.
- Very long grammar notes are shortened in the list and complete when opened.
- "See original session" or "Open notebook" opens the right phrase/note at its actual source, with no fabricated session context for notebook notes.
- The tab counts match the number of items in each tab.
- Each tab has its own friendly empty and error states.
- Phrases and grammar notes match Vocabulary in layout, wording, and behavior, using the same reusable list.
- Special characters and non-English letters appear correctly.
- The pages work on a phone, tablet, and computer, in light and dark mode, with the keyboard and a screen reader.

### Step 3: My common mistakes

**What it delivers:** The session-only My mistakes collection, retaining grouping, seen counts, frequency ordering, and an opened view with every session appearance. Notebooks add no mistakes, questions, answers, or natural versions.

**Tests**

- The same mistake in several finished, non-archived sessions appears once, as one group, identified by the pair of what was said and the correction, ignoring capitalization, accents, and spaces.
- Selecting Notebooks or a particular notebook produces an honest empty My mistakes result; no notebook mistake/question/natural-version entries are fabricated.
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

- A star can be added/removed from the list and panel and agrees across all surfaces for that scoped item; matching words in a session or another notebook remain unchanged.
- The star in a list row is a separate control from the row's open action, and its accessible name includes the item.
- Star and status requests carry the desired state, so repeated or simultaneous requests never flip them, and a failed save returns them to the previous state with a calm message.
- The list's quick status menu and the opened item's status group both work, and choosing the active option in the opened item clears it.
- An item can be marked Learned or Still difficult, or cleared, from the list and from the opened item, and has at most one status at a time. The status label is text, not color alone.
- Starring or changing status never changes source content, source last-updated information, or Progress totals; saved custom-review attempts/ratings never mutate these marks, daily schedules/results, streaks, or Progress.
- Personal notes can be written with bold, italic, bulleted lists, and numbered lists, and pasted text is cleaned in the same safe way as elsewhere in the app. Unsafe content is never saved or shown.
- Notes save automatically, a saved indicator is shown, a failed save keeps the text on screen with a calm message, and a refresh shows everything that was saved.
- Two tabs editing the same notes show a calm message with "load the latest version" and "keep what I have", and nothing is lost until the learner chooses.
- The server cleans pasted notes itself, a plain-text copy is stored, notes with only blank space count as no note, and the notes length limit is enforced with a clear message.
- Items with notes show a "Has notes" indicator in the list.
- Star, status, and personal notes agree for the same scoped identity only. Notebook metadata/topic changes and shared-topic renames do not reset them; notebook description remains separate from personal notes.
- A typo in an eligible session or successfully saved notebook entry preserves marks when the old identity has no other appearance in that scope and the destination has no marks.
- Grammar edits retain saved-entry identity/marks in either source; identical note text does not merge marks.
- The status is only ever changed by the learner, and never changes automatically.
- If an old appearance remains in the same scope, including a notebook duplicate, it retains its marks and a new identity receives none. Another scope's matching row is neither the old appearance nor a destination.
- Existing destination marks within scope are never overwritten; old marks stay stored under their old scoped identity.
- Normalisation-only changes and unfinished session edits move no marks. Failed/conflicting notebook saves also move none.
- Typo-following runs in the same successful source save (including notebook autosave), safely on retries.
- There is at most one record per learner and scoped item, even with repeated or simultaneous requests.
- Archived session marks return with the same identity on restore. Removing a last notebook appearance or hard-deleting its notebook leaves marks stored under the old scope, not inherited by another source or a recreated notebook; a remaining duplicate in that notebook keeps its item alive.
- Two tabs changing the same star or status show correctly after the next load, and two tabs editing the same notes show a calm message with nothing lost.
- Marks and notes work on items marked "Needs attention" and on every collection.
- Star and status controls have clear accessible names that include the item, work with the keyboard, and announce changes calmly.
- Everything works on a phone, tablet, and computer, in light and dark mode.

### Step 5: Search, filters, and sorting

**What it delivers:** Scoped search in each collection; Source (All default/Sessions/Notebooks), notebook, starred, status, attention, and shared Topic filters; resolved sorting; chips/counts; and address state shared with scoped eligible-only/bulk/Add to review selection.

**Tests**

- Search finds scoped items by text, meanings, examples, reasons, and personal notes, with existing normalization/substring matching; matching content in another scope cannot supply a result.
- Typing several words requires all words within the same scoped item, not spread across matching spellings in different sources.
- Search covers the scoped item's meanings/examples/reasons and notes and shows "Matched in …" for hidden matches; notebook duplicate search/display follows the resolved representative rule.
- Matching text is highlighted without relying on color alone, and saved text can never inject markup.
- Special characters, very long text, and non-English letters never cause errors or odd results.
- "Starred only" shows only starred items.
- The status filter shows all, not marked, learned, or still difficult items.
- All initially shows session and notebook identities separately; Sessions and Notebooks return only their source identities, not spelling-based "only here" results.
- Selecting a notebook selects Notebooks; switching to Sessions visibly clears it. Source/notebook choices and compatible filter state survive refresh and return navigation.
- Shared Topic matches any eligible session appearance or the item's notebook topic. Choices/counts reflect relevant sources and active-collection items, alphabetically and searchable; no second category picker is added.
- The "N need attention" count covers the active collection within Source/notebook, ignoring search/remaining filters; Grammar notes have no attention-only control.
- Attention filtering shows only flagged scoped items; empty counts disappear. Eligible-only views and bulk/Add to review selection use the same identities and source-local eligibility.
- Fixing missing content in its session or notebook updates attention on the next load/existing navigation refresh; another scope's complete row cannot remove the marker or enable an exercise. Notebook duplicate completeness follows the resolved shared rule.
- Session newest/A to Z/most-seen order and defaults remain unchanged. Notebook/mixed-source dates, frequency presentation, tie-breaks, and stable pagination are checked against the resolved Notebook decisions, not invented session dates.
- Filters combine correctly, appear as removable chips, and Clear all removes them all.
- Clear all removes only filters, resetting Source to All and notebook to none; search clear removes only search. Removing source/notebook chips never leaves an incompatible hidden choice.
- "Most often seen" is not offered for Grammar notes, and sorting creates no chip.
- Unstarring in the list while "Starred only" is on removes the item immediately with sensible focus, and changes made in the opened item update the list when the panel closes.
- Switching tabs starts the new tab with its own defaults.
- Results, baseline/tab totals, topic/attention counts, and bulk selected totals agree on distinct scoped items. Three same-spelling items across sessions/two notebooks remain three; comma-separated meanings and duplicate rows within one notebook do not inflate totals.
- When nothing matches, a friendly message offers Clear all/Clear search as applicable, never a blank page or a false no-finished-sessions state.
- Collection, search, Source/notebook and other filters, sorting, and scoped panel identity survive refresh and return from either source. Invalid/deleted/incompatible address choices are handled calmly without resolving to another scope.
- Search and filters stay fast with thousands of items, with a bounded number of database queries.
- The controls work on a phone, tablet, and computer, in light and dark mode, with the keyboard and a screen reader.

### Step 6: Staying in step, and polish

**What it delivers:** Collections reflecting saved session and notebook changes through existing load/navigation behaviour, plus checks of scoped integration, speed, wording, accessibility, and consistency.

**Tests**

- Finishing a non-archived session makes its items available on the next load/existing navigation refresh; unfinished sessions never contribute. Successful notebook autosaves make saved entries available without finishing.
- Session edits and successful notebook saves/removals update only affected scoped content/counts. Failed/conflicting notebook saves do not publish unsaved changes or move marks.
- Archiving removes eligible session appearances, leaving an item only if another eligible session appearance remains. Restore recovers its marks; deletion removes appearances. Matching notebook text has no effect.
- Notebook hard deletion or last scoped appearance removal removes that item and its live review reference; same-spelling items elsewhere cannot rescue either. A duplicate remaining in that notebook keeps the item alive.
- Orphan marks remain under the deleted scope; a new notebook with identical name/text cannot inherit them. Existing custom-attempt snapshots/history remain readable without relinking to another source.
- Archived/deleted sessions and deleted notebook entries are absent from live provenance; source links handle disappearance calmly.
- Tab, result, attention, topic, and selection counts agree after each source update; metadata/topic changes affect provenance/filters without resetting identities or marks.
- Loaded/refreshed views reflect saved data, and only the latest search/filter response updates the screen. No unsupported cross-tab/device real-time guarantee is tested or claimed.
- Existing session Progress totals/streaks are unchanged; archiving leaves session totals intact while hiding Collections appearances. Notebook collection is not a finished session or proof of learning; daily/Quick activity metrics await their separate decision, and custom-review attempts remain excluded.
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
- **Items are identified by source scope, kind, and normalized text.** Existing session grouping is unchanged: session words and phrases by their text across sessions, mistakes by the pair of what was said and the correction. Each notebook is its own scope, so matching text never merges across notebooks or between a notebook and sessions. Each grammar note is its own item in either source. The list shows the most recent session appearance; the opened item shows everything within the item's scope.
- **"Seen N times" counts appearances.** A repeated row in the same box of the same question counts once. Mistakes always show the count, and words and phrases only when seen more than once.
- **"Worth practicing"** is a quiet text label for mistakes seen three or more times, with the threshold in one named setting.
- **"Needs attention" comes from one shared rule.** A phrase without a meaning is not flagged. A word is flagged when none of its appearances has a meaning.
- **Star and status requests carry the desired state**, and the star and status controls are separate from a row's open action.
- **The typo rule** moves marks only when the old scoped item has no other appearance in that scope, never overwrites existing destination marks, ignores unfinished sessions and failed/conflicting notebook saves, and runs in the same successful save as the source edit. Notebook edits follow marks only within that notebook; grammar edits keep their saved-row identity.
- **Search covers every meaning, example, reason, and the learner's notes**, requires all words in the same item, highlights matches safely, and shows "Matched in …" for hidden matches. It works within one collection.
- **The topic filter** lists only topics with items in the active collection within the selected Source/notebook. The **"N need attention" count** covers that same scope. Topic stays the shared topic concept, not a second category picker.
- **Address behavior:** search, filters, and sort replace the address. Opening an item adds a history entry so Back closes the panel. Switching tabs resets to that tab's defaults.
- **Marks changed in the list** make a no-longer-matching item leave the list immediately. Changes in the opened item apply when the panel closes.
- **Archived sessions are hidden from Collections but still count in Progress**, and the archive messages, banner, and Archived view say so.
- **Collections is the combined study index.** Finished, non-archived sessions and saved notebook entries feed it automatically. Notebooks contribute vocabulary, phrases, and grammar notes only; My mistakes stays session-only. The feature keeps the name Collections; notebooks are the editable containers.
- **Source filter and notebook picker.** All (the default), Sessions, or Notebooks, plus a picker narrowing notebook items to one notebook. Selecting a notebook selects Notebooks; switching to Sessions visibly clears the incompatible choice. Filters select scoped identities, never an "appears only here" spelling test.
- **Marks are global for the scoped item.** Star, status, and personal notes are shared wherever that identity appears, including its notebook and review context, and independent for matching text in another scope.
- **Notebook content needs no finish step.** Successfully saved entries appear; blank rows and formatting-only notes do not. Empty states never require a finished session when notebook items exist, and notebook-only My mistakes shows an honest empty result.
- **Notebook deletion is hard deletion.** Removing the last scoped appearance removes the item and its live review reference; matching text elsewhere cannot keep it alive, and a recreated notebook does not inherit its stored marks.
- **Notebook sorting and duplicate-row presentation are not invented here.** Mixed-source date/frequency presentation and duplicate-row representative content follow the [Notebook open decisions](feature-Notebooks.md#open-questions) before implementation.

## Open Questions

No Collections-specific decisions remain open. The notebook integration depends on decisions tracked in [Notebooks — Open Questions](feature-Notebooks.md#open-questions): mixed-source date/frequency sorting, duplicate-row representative content and eligibility, and export/backup delivery. Resolve them before implementing the affected behaviour; this document does not settle them by assuming session dates or row order.

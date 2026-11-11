# Notebooks

## Summary

Notebooks let the learner save vocabulary, phrases, and grammar notes **without recording a practice session**. After reading a book, watching a video, or noticing a useful expression, the learner creates a named notebook, chooses a topic, and adds entries to one familiar, automatically saved board.

Notebooks are **not Collections**. A notebook is a container the learner creates and edits. Collections remain the combined study index, now showing items from eligible sessions and from notebooks. A notebook's items also become available to daily Review and can be gathered into a saved custom review.

**Words are scoped to their notebook.** A notebook's “bank” and a session's “bank” are separate study items, even when their spelling matches. Their meanings, examples, marks, and daily-review schedules must never be mixed. Multiple meanings of one word in one notebook are entered together, separated by commas, and remain **one item**.

This document specifies the new feature; it is not a claim that the feature has been implemented or tested. Confirmed product decisions are recorded below. The remaining creation details, Progress measures, and export/backup delivery are listed under **Open Questions**. The **Related document updates** section maps the shared requirements to the other feature specifications; documentation alignment does not imply implementation.

### Assumptions

- The app is for **one learner** in this version, with the same access boundaries as the existing app.
- Entries are written or pasted by the learner. No AI generates meanings, separates word senses, or grades answers.
- Each notebook has a required **name**, one required **topic**, and an optional **rich-text description**.
- The category discussed for notebooks is the existing **topic** concept, not a second category system. The default is **General**.
- Each notebook has **one board** with **Vocabulary**, **Phrases**, and **Grammar notes**. Any combination can be filled; unused boxes remain empty.
- Notebooks have no questions, answers, mistakes, natural versions, session dates, or session Draft/Finished workflow. Saved entries do not wait for a notebook to be finished before appearing in Collections.
- Session items continue to follow the existing **study sessions** rule: finished and not archived. Notebook support does not make unfinished or archived session entries available.
- Notebooks use **hard deletion**, not archiving or a recycle bin.
- The 50-item maximum belongs to a **custom review**, not to notebook storage. A notebook can contain more than 50 items.
- An internet connection is required. Failed saves must keep the learner's unsaved text on screen, but offline synchronisation is not introduced here.

### Dependencies

- [Practice sessions](feature-Practice%20sessions%20%28copy%20and%20paste%20entry%29.md): the existing topic picker, entry editors, paste handling, rich-text editor, autosave feedback, and conflict behaviour. Reuse the components, not the session page or a fabricated session record.
- [Collections](feature-F3-Personal%20collections.md): item grouping, stars, statuses, personal notes, attention rules, search, filters, panels, and Add to review actions.
- [Review and re-practice](feature-F4-Review%20and%20re-practice.md): exercise eligibility, daily and Quick review selection, ratings, schedules, source navigation, and exercise screens.
- [Custom reviews](feature-Custom%20reviews.md): saved review membership, allowed kinds, the recommended 20 items and maximum 50, attempt snapshots, and attempt history.
- [Library](feature-F2%20Library%20of%20saved%20sessions.md): session-only inclusion rules and the safe preview-and-confirm deletion pattern. The Library remains a list of sessions, not notebooks.
- [Feature list](features.md): Progress and motivation (F5), Export and backup (F7), and the feature summary.
- [Product specification](spec.md): the application layout, mobile-first experience, accessibility, dark mode, and no-AI boundary.

## Goals

- Capture useful language from sources other than conversations, with no artificial question or session to fill in.
- Make adding and editing entries feel familiar to someone who already uses the session editor.
- Keep context-specific meanings and learning state separate, while letting the learner find all their study material in Collections.
- Start practice directly from a notebook when its items satisfy the existing review rules.
- Preserve saved work, honest counts, and readable review history as entries change or are deleted.

## User flow

### From a book to a notebook

1. The learner opens **Notebooks** and chooses **New notebook**.
2. They enter “Nature reading”, keep **General** or choose/create another topic, and optionally add a formatted description such as the book title and chapter list.
3. **Create notebook** saves the container and opens its single entry board. The board appears **after creation**, not as an unsaved session-like form.
4. They add “bank” with the meaning “river edge, raised ground beside water”, add an example, paste a few phrases, and write a grammar note.
5. Each change saves automatically. A visible indicator distinguishes saving, saved, and a save that needs attention.
6. They open Collections and find the notebook's entries, with the notebook named as their source. Opening an item offers **Open notebook**, not **See original session**.
7. They can star the word, add a personal note, or mark it Still difficult. Those marks are the same wherever **this notebook item** is shown.

### The same spelling in different places

The learner already has “bank” meaning “financial institution” in a finished session and also has it in another notebook.

| Source             | Word | Meaning                                | Study identity                         |
| ------------------ | ---- | -------------------------------------- | -------------------------------------- |
| A finished session | bank | financial institution                  | Existing session vocabulary identity   |
| Nature reading     | bank | river edge, raised ground beside water | Nature reading's vocabulary identity   |
| Business reading   | bank | financial institution                  | Business reading's vocabulary identity |

Collections shows **three items**. The two comma-separated meanings in Nature reading do not become two items. Starring or rating Nature reading's item does not star or reschedule either of the other two.

### From a notebook to a saved review

1. The learner chooses **Create review and practise** on the notebook.
2. The app uses saved, distinct notebook item identities, checks their count and eligibility, and explains any entries that cannot be used yet.
3. If the selection fits the maximum and contains at least one eligible item, the learner can start immediately. The existing warning above 20 items still applies.
4. If there are more than 50 distinct items, a selection step asks the learner to choose at most 50; the app never takes the first 50 silently.
5. The action creates a **new saved custom review**, explicitly marks that new review Finished as part of the learner's request to practise, and starts an attempt with its eligible items.
6. The learner adds another word to the notebook later. It appears in Collections and can become a daily-review candidate, but it does **not** join the saved custom review automatically.
7. Editing the meaning of an included word changes the review's current content and future attempts. An attempt already started keeps its snapshot.

## Requirements

### Creating, finding, and opening notebooks

1. **Notebooks** has its own main-navigation destination. **Collections** keeps its existing name and destination.
2. The notebook listing shows the name, topic, item counts, last-updated information, and a shortened description when present. Description previews are plain text; the full description is displayed with supported formatting on the notebook page.
3. The listing provides **New notebook**, search, and a topic filter. Search/filter choices survive refresh through the page address, using the app's existing navigation conventions.
4. Creation asks for a name, a topic defaulting to General, and an optional rich-text description. A blank or whitespace-only name is refused with a field error. Reuse established field-length limits where applicable and make any new limits explicit before implementation.
5. A successful creation opens the saved notebook and its one board. An empty notebook is valid, remains on the listing, and can be reopened or deleted. Cancelling before creation does not leave a notebook behind.
6. Existing notebooks reopen in the same editable page. Name, topic, description, and all entries remain editable without a separate finish step.
7. Empty states distinguish **no notebooks**, **an empty notebook**, and **no search results**. A learner who has notebooks but no sessions is not told that they must finish a session to see their notebook content.

### Shared topics

8. A notebook belongs to **exactly one existing topic**. The picker uses the same selection, creation, and name-normalisation rules as sessions, so capitalization or spacing variations do not create separate topic namespaces.
9. **General** is the default topic, not a second category field. Resolve it through the shared topic mechanism. If it must be created, that is part of a write operation, never a side effect of opening a listing or rendering a page.
10. Renaming a topic updates its label everywhere it is used, including notebooks and sessions. It does not change notebook item identities, marks, schedules, or review membership.
11. A topic can be deleted **only when no notebook and no session references it**. Empty notebooks, unfinished sessions, and archived sessions still count as references.
12. A refused topic deletion explains that the topic is in use. It must not delete notebooks or sessions, silently reassign them to General, or cascade their content away.
13. Changing one notebook's topic changes only that notebook's topic assignment. The Library's topic filter remains based on its relevant sessions; a topic used only by notebooks need not appear in the Library filter.
14. Deleting a notebook leaves its topic intact. A now-unused topic becomes eligible for a separate, explicit deletion; it is not automatically removed.

### One entry board

15. The board has exactly three boxes, in order: **Vocabulary**, **Phrases**, and **Grammar notes**. There are no question cards and no controls for mistakes, questions, answers, or natural versions.
16. Vocabulary uses the existing word, meaning, and example editing behaviour. Phrases use the existing phrase, meaning/use, and example behaviour. Grammar notes are individual rich-text entries.
17. The learner can add, edit, remove, and paste entries using the same applicable interactions as the session editor. Existing example formatting/list behaviour is retained rather than introducing a simpler, incompatible notebook-only editor.
18. Empty boxes are allowed. Blank rows and formatting-only grammar notes do not become saved items. Partially completed non-blank rows remain editable and receive the shared attention treatment where appropriate.
19. Multiple meanings of one word are written in the **same meaning field, separated by commas**. Commas in that field do not create entries, identities, exercises, or additional review slots.
20. List pasting keeps the existing entry and column boundaries. It must not interpret commas inside meanings as a request to split word senses.
21. Repeated vocabulary or phrase rows within one notebook use the editor's duplicate warning behaviour. They are not silently deleted or overwritten, and do not become multiple study identities. The learner can consolidate their meanings and examples into the intended single entry. Until then, content resolution and eligibility use the deterministic, shared rule recorded below: saved editor order, the first row with a meaning and the first with an example, any complete row making the item eligible, and the shared attention rule applied over those rows.
22. Grammar notes are **never merged**, even when their text matches, within one notebook or across sources.
23. Removing saved content uses the existing entry-removal confirmation or undo pattern. The stronger whole-notebook deletion flow is separate.

### Autosave and rich text

24. Changes save after the same short pause used by the existing editor. The page exposes saving, saved, failure, and retry states for metadata and entries.
25. Save success is shown only after the server accepts the change. Failed saves keep the learner's text, offer the existing retry behaviour, and do not falsely announce that it is safe to close the page.
26. Repeated save requests must not duplicate rows. Stable client row identifiers, ordering, and replacement/removal semantics remain consistent with session entry saving.
27. Two tabs must not silently overwrite newer changes. Apply the existing conflict flow, preserving local text until the learner chooses to load the latest content or resolve the conflict.
28. Navigating away makes the existing best effort to save, and warns when work remains unsaved. Starting practice must first save pending notebook changes; a failed or conflicting save blocks the shortcut rather than practising stale content without warning.
29. Descriptions and grammar notes use the shared rich-text editor and supported formatting. Formatted examples retain the existing example editor. The server sanitises stored rich text and derives plain-text copies for search and previews.
30. Rich text that is only whitespace or empty markup is treated as empty. Unsupported or unsafe pasted markup must not execute or load unwanted external content in the editor, listing, panel, or review.
31. Adding or editing entries updates the notebook's last-updated information. Opening a notebook, changing a Collection mark, or attempting a review does not masquerade as editing its source content.

### Scoped identities and shared marks

32. A vocabulary or phrase item is identified by **source scope, kind, and normalised main text**. For notebook entries, the scope is the notebook's identity, not its name or topic. Vocabulary and phrases with the same text remain different kinds.
33. Existing session grouping stays unchanged. Notebook entries never merge with session entries or entries in another notebook, even if their topic, spelling, and meaning all match.
34. One notebook's word with several comma-separated meanings is one item. Repeated rows for that same normalised word within that notebook are appearances of that one item, not extra custom-review slots.
35. Each grammar note retains its own saved-entry identity, with its source checked when reading or updating it. Editing its text does not create a new grammar-note identity.
36. **Global marks means global for the scoped item.** A star, Learned/Still difficult status, and personal notes are shared wherever that item is displayed, including its notebook, Collections, and review context. They are not global by spelling across notebooks and sessions.
37. The existing mark controls and rules apply to notebook items. Personal notes on a study item are separate from the notebook description and from its meaning or grammar-note content. Only the learner changes item status; practice never silently marks an item Learned.
38. Renaming a notebook, editing its description, or changing its topic does not move or reset item marks, daily-review schedules, or custom-review membership.
39. Identity-changing word/phrase edits apply the existing typo-following rules **within the notebook scope** during the successful save. If the old identity no longer has an appearance, its marks can follow; if another old appearance remains, its marks remain there. Existing destination marks are never overwritten. Normalisation-only changes do not move anything.
40. Daily-review history/schedules and custom-review references follow the corresponding existing edit rules in the same scope and save. A matching session item or another notebook must never be used as the destination. Grammar-note edits keep their identity.
41. Marks for disappeared items remain quietly stored under their original scoped identity, consistent with Collections. A different notebook created later, even with the same name and words, does not inherit them.

### Collections integration

42. Collections automatically includes saved notebook items alongside eligible session items. The session finish/archive rule remains a rule for session sources only.
43. Add a **Source** filter with **All**, **Sessions**, and **Notebooks**. All is the default. The filter selects scoped items by their source; it is not an “appears only here” test against matching spellings elsewhere.
44. With All selected, a session's “bank” and each notebook's “bank” appear separately. Sessions shows the session item; Notebooks shows the notebook items. There is no combined “both sources” item for these identities.
45. Provide a notebook picker to narrow notebook results to a particular notebook. Selecting a notebook also selects notebook sources; switching Source to Sessions clears an incompatible notebook selection visibly. Preserve source and notebook choices with the existing filter/address state.
46. Keep the **Topic** filter as a shared concept. Notebook items match their notebook's topic; session items match under the existing session appearance rules. Do not replace Topic with a separate category picker. Counts and available choices must match the relevant source and items.
47. Rows visibly identify their source so equal spellings can be distinguished. Notebook panels show the notebook name, topic, relevant entry content, shared scoped marks, and **Open notebook** at the entry. Session panels keep their existing session context and links.
48. A notebook panel must never offer a session link or borrow a meaning/example from a similarly named session item. Source navigation and Back preserve the Collections context using the existing return behaviour.
49. Search, eligible-only views, starred/status filters, attention counts, bulk selection, Add to review, and result totals all use the scoped identity. Searching a notebook item's meanings must not match only because another scope contains that meaning.
50. Existing session “Seen N times” semantics are unchanged. Notebook rows show their notebook provenance, not fabricated session appearances or an unhelpful “Seen 0 times”. Under Newest they use their stable “Added” date; under most-seen they follow counted session items without an invented appearance count, as recorded below.
51. Adding, editing, or deleting a notebook entry updates its Collections data and counts through the existing refresh/navigation conventions. Do not claim cross-device live updates beyond what the app supports.
52. A source filter yielding no notebook mistakes has an honest empty state. Notebooks do not generate mistake entries to fill the existing My mistakes tab.

### Notebook search

53. The notebook listing search covers **name, topic, description, and entry content**: words, meanings, phrases/use, examples, and grammar-note text. Rich-text formatting is ignored for matching.
54. Reuse the existing text normalisation and search conventions, including safe highlighting, clear/reset behaviour, and protection against an older response replacing a newer search.
55. A notebook appears once in notebook search results, even when many entries match. If it matched through an entry rather than visible metadata, show a meaningful matching-entry preview and a way to open that entry.
56. Topic filtering and search combine. Search results must not expose content from a different notebook merely because the same normalised word occurs there.

### Creating a custom review and starting immediately

57. A notebook offers **Create review and practise**, clearly distinguished from **Add to review**. It creates a new saved custom review from this notebook, rather than silently choosing an existing unfinished review.
58. Candidate membership consists of distinct, valid scoped identities from saved, non-blank notebook entries. Rows without enough main text to identify an item remain in the editor with guidance; they cannot be turned into anonymous review references.
59. Every selected item counts toward the review's **50-item maximum**, including an item that is not exercise-eligible yet. Eligibility and membership are separate: the screen names the selected total and how many can actually be practised.
60. The shortcut can start when the chosen set has **1–50 items** and **at least one eligible item**. Use the existing per-kind exercise eligibility rules; do not invent a notebook-only shortcut around them. A phrase missing a meaning, for example, is not necessarily an attention error, but cannot make a meaning flashcard.
61. When the candidate set exceeds 50, open a selection step showing kinds, content, eligibility reasons, and the selected count. Let the learner choose at most 50. Cancelling writes no review. Nothing is silently truncated or split into several reviews.
62. Above the recommended **20 items**, retain the existing Continue/Cancel size warning after the maximum and allowed-kind checks. Exactly 50 is allowed; confirming a warning cannot allow 51. A notebook itself remains editable above either number.
63. When no selected item is eligible, explain what is missing and offer to fix the source entries. Do not create a supposedly ready attempt or silently fabricate eligible content. Ordinary Add to review remains available according to its existing rules.
64. **Create review and practise is the learner's explicit readiness choice.** Only the new review created by this action is marked Finished. It then starts with every eligible selected item, without per-kind truncation. Ordinary review creation and adding still follow the existing Empty/Draft/Finished rules.
65. Reuse existing custom-review naming and allowed-kind defaults. A notebook's currently populated kinds do not silently redefine a review's allowed kinds; any restriction must be the learner's choice and obey the shared kind rules.
66. Revalidate saved candidates, source existence, limits, and eligibility on the server before creating the review and attempt. A stale selection, failed save, or retry must not create partial membership, duplicate reviews, or an attempt sourced from a different scope.
67. If review creation succeeds but starting the attempt fails, the saved review remains accessible with an honest error and retry path. Do not ask the learner to create the same review again to recover.

### Fixed membership, live content, and history

68. A created review has **fixed membership**, not a live notebook subscription. New notebook entries do not join it automatically. The learner may still explicitly add or remove items using normal custom-review actions and constraints.
69. Editing an included item's meanings, examples, or grammar-note text updates the review's current content and future attempts. Word/phrase identity changes follow the scoped edit rules; they do not start resolving to another notebook or a session.
70. Fixed membership is **not frozen content or permanently frozen eligibility**. An included word that gains a meaning later may become usable in a future attempt without being re-added; an included item that loses required content stays a member but is skipped until eligible again.
71. Every started custom-review attempt keeps the existing snapshot contract. Later source edits do not rewrite an in-progress or past attempt. History remains paged according to the Custom reviews specification.
72. Source navigation from a notebook-backed attempt opens the notebook at its entry when it still exists. A deleted source is represented calmly without a broken link or fallback to another scope.
73. **Custom-review isolation is preserved.** Its ratings and attempts stay in its own history and do not change scoped marks, daily schedules, recent daily/Quick results, streaks, or Progress. Displaying source marks is not permission for the attempt to change them.

### Daily Review and Progress

74. Notebook items feed **daily Review and Quick review** as well as saved custom reviews. A learner with no finished sessions can practise eligible notebook items through these flows.
75. Candidate selection applies the existing per-kind eligibility, attention, learned-item, and scheduling rules to the notebook source. An incomplete source remains editable and discoverable even if it cannot yet produce an exercise.
76. Daily/Quick ratings and schedules belong to the **scoped item**. Rating Nature reading's “bank” must not reschedule a session's “bank” or Business reading's “bank”. An exercise uses only content from its own scope.
77. Notebook topics participate in the existing topic-based review chooser. Exercise hints and source actions identify the notebook rather than inventing a session date or question number. No notebook question or mistake exercise is generated.
78. Deleting an entry or notebook removes its scoped items from future candidate selection. Existing handling of unavailable items during an active daily/Quick review applies without selecting a same-spelling replacement from another source.
79. **Progress integration is in scope**, but entry collection and actual practice are different activities. Merely creating a notebook, typing words, or writing comma-separated meanings must not be counted as finished conversation sessions or as proof that words were learned.
80. Daily/Quick practice of notebook items must be represented consistently in review-activity reporting, alongside the equivalent session-sourced practice. The exact F5 measures and deletion/history treatment require the decision listed under Open Questions before this part is implemented.
81. Existing **session-based streaks, practice days, and session-content totals** remain session-based unless separately changed by an explicit product decision. Notebook practice is not a fabricated finished session. Saved custom-review attempts remain excluded under requirement 73.

### Hard deletion

82. The notebook page and listing offer **Delete notebook** through a deliberate destructive action. It is available for empty and populated notebooks, with Cancel as the safe default.
83. Before deletion, show a read-only impact preview naming the notebook, the content counts by kind, the scoped items being removed from Collections/Review, and the affected saved custom reviews. Counts distinguish saved rows from distinct study items where duplicates make them different.
84. State plainly that deletion **cannot be undone** and require explicit acknowledgement. Explain that other notebooks and sessions are unaffected, even when they contain the same words. Explain that saved custom reviews lose these items but keep their existing attempt snapshots.
85. Reuse the existing stale-preview protection: if relevant content or deletion impact changes before confirmation, refuse the stale confirmation and require a fresh preview. Do not delete based on an obsolete count.
86. Delete the notebook and its owned entries together. Remove their references from saved custom reviews according to the existing permanent-deletion rules. A review losing its last item becomes Empty; do not delete the review or its attempt history.
87. Retain existing custom-attempt snapshots with their Deleted indication. Do not relink a deleted notebook reference to matching text in a session or another notebook. Stored marks remain under the old scope as described above, not under a replacement notebook.
88. Removing one entry applies the same scoped identity rules: if a duplicate appearance remains in that notebook, the study item still exists; otherwise its review reference is removed. Another source's matching spelling cannot keep it alive.
89. The shared topic is not deleted by notebook deletion. A save arriving for a deleted notebook cannot recreate it accidentally or attach entries to another parent; the learner receives a clear unavailable-source message.

### Experience and boundaries

90. Notebook creation, metadata editing, entry editing, selection, practice, and deletion work on phone, tablet, and desktop, in light and dark mode, without sideways page scrolling.
91. Controls have labels, visible keyboard focus, and useful accessible names. Announce save states, selection counts, validation failures, and deletion impact calmly. Dialogs and panels restore focus correctly.
92. Reusing entry components must preserve editor focus behaviour, especially when opening a box or adding an example sub-field. A box must not close immediately because focus was sent to the wrong target.
93. Long descriptions, meanings, examples, and grammar notes remain readable and editable. Listing previews can shorten content; the source page and practice answer must not silently discard it.
94. Requests validate the parent, supported kind, scoped identity, and content at the server boundary. An identifier in the browser is not authority to update an entry under another notebook or question.
95. No notebook archive, multi-board layout, artificial practice session, word-sense splitting, automatic review synchronisation, cross-notebook item moving, or AI features are introduced in this version.

## Technical

This describes implementation boundaries rather than final schema syntax. Follow the application's existing architecture and installed package versions when implementing.

1. **Use a real notebook model.** Store the notebook's name, shared topic reference, optional cleaned rich description and plain-text projection, timestamps, and the version/conflict information required by the existing save flow. Do not disguise a notebook as a session or question.
2. **Reuse the existing entry table.** Keep vocabulary, phrase, and grammar-note payloads compatible with session entries. Every entry must have exactly one valid parent: a practice question or a notebook. The parent representation is an explicit decision before migrations, not assumed to be a polymorphic conversion.
3. **Compare parent representations carefully.** Two nullable foreign keys with an enforced exactly-one-parent constraint preserve database referential integrity for the two known parent kinds. A polymorphic type/id offers one relationship but loses a normal foreign key to either parent and its automatic database cascade; it therefore needs deliberate parent validation and deletion handling. Confirm database support and migration safety before choosing. Do not promise that morph entries will cascade by themselves.
4. **Migrate without changing session identity.** Preserve existing question relationships, entry ids, marks, schedules, review membership, and attempt history. New notebook scoping must not reinterpret previously saved session identities. Backfill and parent deletion tests are required for whichever schema is chosen.
5. **Define scope once.** Grouping, source content queries, resources, marks, daily schedules, custom-review references, edit-following, and deletion must agree on the same scope/kind/identity. Notebook content resolution must constrain the parent before choosing meanings or examples; scoping only the visible list is insufficient.
6. **Keep writes in actions.** Notebook creation, autosave, topic changes, deletion, and creating a review are write operations. Validation belongs in requests, composed reads in queries, and payload shape in resources. Read queries and resources must not create General, repair parents, or prune references as hidden side effects.
7. **Reuse components, not pages.** Build a notebook page from the existing entry boxes, table/list editors, rich-text editor, save indicators, and notices. Share row-sync behaviour only where it is actually common. Session finish/archive logic must stay out of notebook saves.
8. **Treat integration as source-aware work.** Existing session joins in Collections, custom-review availability/content, daily candidate selection, deletion previews, and search must explicitly support notebooks. Avoid both a second incompatible entry pipeline and unbounded per-row parent queries.
9. **Keep mutations consistent.** A save that changes identity must update projections and dependent references under the existing transactional edit rules. Review creation must protect its membership/count/readiness decision from concurrent changes and use the app's established retry protections.
10. **Preserve bounded reads.** Search and list counts use database-side filtering and aggregation. Do not load every notebook entry just to count or filter the listing. Paginate large listings/selection results using existing patterns; do not confuse list pagination with the 50-item review membership maximum.
11. **Keep historical evidence honest.** Custom attempts snapshot their source text. Quality reports describe checks actually executed, not acceptance criteria copied from this document. Export/backup scope must be coordinated separately rather than assuming the existing session export automatically includes notebook parents.

## Implementation Steps

These are proposed reviewable stages, not permission to ship a partially integrated feature as complete. Each stage includes tests at the route or learner-visible boundary. Resolve its listed open decisions before implementing the affected behaviour.

### Step 1: Parent storage, scoped identity, and shared topics

**What it delivers:** A real notebook parent, one shared topic system, and identities that do not collide with sessions or other notebooks.

**Tests**

- Existing session entries, marks, schedules, and custom-review references keep their identities through the migration.
- An entry cannot have neither parent, both parents, or a nonexistent parent.
- One word in two notebooks and a session produces three independent study identities; a vocabulary item and phrase with the same text are also distinct.
- Multiple comma-separated meanings occupy one word item; repeated word rows within a notebook do not inflate review membership.
- Identical grammar-note text still produces separate note identities.
- A shared topic rename reaches notebooks and sessions without changing item identity.
- Topic deletion is refused for empty notebooks, unfinished sessions, and archived sessions; an unreferenced topic can be deleted.
- Notebook deletion never cascades into the topic or unrelated session content.

### Step 2: Notebook creation, listing, and the autosaving board

**What it delivers:** The learner can create, reopen, search, and edit a notebook with its three boxes, using familiar controls.

**Tests**

- Creation validates the name, defaults the topic appropriately, saves rich description safely, and opens the board only after the notebook exists.
- Empty notebooks persist; cancelling creation creates nothing.
- Vocabulary, phrases, examples, and grammar notes survive save and reload with supported formatting.
- Commas in meanings do not split entries. Blank rows do not save; incomplete rows stay editable.
- Retry and duplicate save submissions do not duplicate entries. Two-tab conflict handling preserves local content until resolved.
- Metadata and entry autosave failures remain visible and block stale-content practice.
- Search finds a notebook by entry meaning/example/note and returns the notebook once, without matching through another scope.
- Opening a feedback box and adding a sub-field keeps keyboard focus in the intended editor.

### Step 3: Collections and scoped marks

**What it delivers:** Notebook content participates in Collections without merging with matching session content.

**Tests**

- All/Sessions/Notebooks and the notebook picker show the expected scoped rows; incompatible source selections are cleared visibly.
- A notebook-only learner sees their content rather than the no-finished-sessions state.
- Topic/search/status/attention/eligible filters, counts, and bulk selection agree on scoped identity.
- A notebook panel shows its own meanings/examples and Open notebook; a session panel keeps session navigation.
- Duplicate rows with different meanings/examples, including one incomplete row, produce deterministic content and eligibility under the resolved representative rule. Collections and new review snapshots agree without reading outside that notebook.
- Starring, status changes, and personal notes agree across surfaces for one scoped item and leave other scopes unchanged.
- A typo edit follows the agreed mark/reference rule within the notebook, including an existing destination and a remaining duplicate appearance.
- Notebook renaming and topic changes do not reset marks or schedules.
- Pagination, date sorting, and source presentation follow the resolved mixed-source rules without repeating or skipping items.

### Step 4: Saved custom reviews and immediate practice

**What it delivers:** A notebook can create a ready saved review and start an attempt, with selection when the item maximum is exceeded.

**Tests**

- One eligible item starts through the explicit Create review and practise action; ordinary creation still starts Empty and ordinary adding still follows Draft rules.
- 20 items need no size warning; 21 require confirmation; 50 are allowed; 51 open selection and cannot be submitted as 51.
- More than 50 saved rows can still fit when they represent at most 50 valid scoped identities. Ineligible members count toward the maximum.
- A selection with no eligible items cannot start, and the learner sees what to fix.
- Cancelling selection or the warning creates no review; retrying the same creation submission does not duplicate the review.
- Allowed kinds are checked and not silently inferred from populated notebook boxes.
- A later notebook addition does not join the review. Explicit Add to review still works under the shared rules.
- Editing included content changes future attempts but not existing attempt snapshots; becoming eligible later does not require re-adding a member.
- Matching text in another source never supplies the attempt answer or rescues a deleted notebook reference.
- Custom attempts leave daily schedules, scoped marks, recent daily results, streaks, and Progress unchanged.

### Step 5: Daily Review and Progress integration

**What it delivers:** Eligible notebook items participate in daily/Quick practice, with separately specified review-activity reporting.

**Tests**

- A learner with notebooks but no sessions can receive a daily suggestion and use Quick review.
- Exercise content and hints come from the selected notebook. A same-spelling session item has independent ratings and schedule.
- Vocabulary/phrase and grammar eligibility use the existing shared rules, including usable examples and grammar-note formatting.
- Unusable content is skipped with guidance, not hidden from the notebook or Collections.
- Deleting or editing a source during daily practice follows existing live-source behaviour without substituting another scope.
- Creating/editing a notebook does not increment finished-session totals or session-based streaks.
- Review-activity metrics follow the resolved F5 definition, treat notebook and session review sources consistently, and exclude saved custom-review attempts.

### Step 6: Hard deletion, integration regression checks, and polish

**What it delivers:** Safe removal, accurate impact warnings, and a verified complete learner journey.

**Tests**

- Deletion previews distinguish saved rows, scoped study items, and affected custom reviews; preview requests perform no writes.
- Cancelling changes nothing; stale impact confirmation requires a fresh preview; confirmation removes the notebook and only its own entries.
- Shared topics and matching words in sessions/other notebooks survive with their marks and schedules unchanged.
- A custom review losing its last member becomes Empty, while snapshots remain readable with Deleted indications.
- Removing one duplicate row does not prune an identity still present in that notebook; a same-spelling row outside the notebook cannot keep a deleted scoped item alive.
- Saves arriving after deletion do not recreate the notebook.
- A full journey works: create from book notes, edit, search, filter Collections, mark an item, create and attempt a review, add a later word, practise daily, and delete with a correct warning.
- Mobile layouts, dark mode, keyboard-only use, screen-reader announcements, focus return, long content, error recovery, and large lists are verified in the browser.
- Session creation, session grouping, archive/restore, topic filters, existing custom reviews, and daily review retain their documented behaviour.

## Decisions Made

- The new feature is **Notebooks**; the existing combined index remains **Collections**.
- Notebooks reuse **topics**, default to General, and have one required topic. Topics can be renamed and can be deleted only with no session or notebook references.
- There is **one board** per notebook with vocabulary, phrases, and grammar notes only.
- Name, topic, optional rich-text description, and entries are editable; entry editing uses the existing autosave experience.
- Words and phrases are **notebook-scoped**, separate from session identities and other notebooks. Several comma-separated meanings of one word remain one item.
- Marks are global **for the scoped item**, not for every matching spelling in the app. Grammar notes remain individual items.
- Source filtering separates session and notebook items; a notebook picker narrows notebook results. Shared topics remain shared filters.
- Notebook deletion is **hard deletion**, with a clear impact warning. It never deletes matching items in other scopes.
- The notebook can start a new saved custom review immediately when constraints are met; above 50 distinct selected items the learner chooses a smaller set instead of receiving a silently truncated review.
- Custom-review membership is **fixed unless explicitly edited**; source content remains live for future attempts, while started attempts keep snapshots.
- Notebook items participate in daily Review and Progress integration, without inventing completed conversation sessions. Exact Progress measures remain to be specified.
- Full notebook descriptions are rich text; list previews are plain text. Search includes entries, not only metadata.

## Related document updates

These documents share the Notebook integration requirements. Keep their descriptions aligned with this specification while preserving the open decisions below and the distinction between planned behaviour and verified implementation.

| Document                                                                               | Required coordination                                                                                                                                                                             |
| -------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [features.md](features.md)                                                             | Add Notebooks to the feature summary and its own section; update Collections, Review, Progress, Custom reviews, and clarify the all-data backup promise.                                          |
| [spec.md](spec.md)                                                                     | Broaden the session-only intake story, add notebook screens and a reading-to-practice journey, and keep streak wording consistent with the agreed Progress boundary.                              |
| [Collections](feature-F3-Personal%20collections.md)                                    | Replace session-only source assumptions with scoped source rules; update filters, panels, marks, search, counts, sorting, empty states, and edit/delete effects. The feature name is Collections. |
| [Custom reviews](feature-Custom%20reviews.md)                                          | Add notebook sources and the explicit create-and-practise path; preserve limits, allowed kinds, membership, snapshots, deletion, and custom-attempt isolation.                                    |
| [Review and re-practice](feature-F4-Review%20and%20re-practice.md)                     | Extend source-aware candidates, content, topics, schedules, navigation, empty states, and the agreed Progress reporting boundary.                                                                 |
| [Practice sessions](feature-Practice%20sessions%20%28copy%20and%20paste%20entry%29.md) | Clarify that topics are shared with notebooks and that entry-component reuse does not give notebooks a session lifecycle.                                                                         |
| [Library](feature-F2%20Library%20of%20saved%20sessions.md)                             | Keep session-only listing/filter rules while clarifying shared topic deletion and source-isolated content deletion.                                                                               |

Existing quality reports remain historical evidence. Do not rewrite their test results to imply that Notebook behaviour has been verified; add new evidence after implementation.

## Open Questions

Entries 1, 3, and 6 are resolved and kept here so other documents can link to the agreed rules. Entries 2, 4, and 5 remain open.

1. **Parent representation — resolved.** An entry carries two nullable foreign keys, `practice_question_id` and `notebook_id`, each with database-level cascade deletion for its own children. A model save guard enforces exactly one parent, because SQLite cannot express that as an alterable CHECK constraint. Notebook entries are further limited to vocabulary, phrases, and grammar notes. No polymorphic parent is used.
2. **Exact Progress measures:** Notebook participation is agreed, but F5 currently defines session-derived totals and days. Specify which daily/Quick review activity measures are shown, how repeated practice and source deletion affect those measures, and their wording. Preserve session-based streaks and custom-review isolation unless explicitly revised. Do not implement a guessed “words learned” formula.
3. **Mixed-source date and frequency presentation — resolved.** A notebook item's date is the moment its most recently first-saved scoped row was created (`MAX(created_at)` across that identity's rows), shown as "Added 12 Nov 2026". Editing existing rows, the notebook name, its description, or its topic never changes it; adding another duplicate row does. Session dates and seen counts are unchanged, and typing is never counted as an appearance. Under **Newest**, session items keep their latest session date and notebook items use their added date on one descending timeline, ties broken by public key so pages neither repeat nor skip. Under **Most often seen**, session items keep their appearance counts; notebook items carry no appearance count, sort after the counted session items by added date descending then key, and never display "Seen 0 times" or a fabricated session date. Notebook grammar notes take "Added <date>" in the date slot; session notes keep the session date.
4. **Remaining creation details:** set notebook-specific metadata limits and confirm how the General default is resolved if that shared topic is renamed or deleted while unused. Topic renaming and reference-protected deletion must remain available.
5. **Export/backup delivery:** the feature list promises all-data backup. Confirm the delivery stage for notebook metadata, entries, scopes, and marks so exports do not silently omit the new source; spreadsheet-to-notebook import is not part of this feature.
6. **Duplicate-row content resolution — resolved.** The representative spelling is the first scoped row in saved editor order (position, then id). The representative meaning is the first scoped row in that order holding a non-blank meaning, and the representative example is the first row holding a non-blank example, kept with its own stored markup. The opened panel lists every distinct non-blank meaning and example in saved editor order with row provenance, never a session date or another scope's content. The item is eligible when any scoped row supplies usable content by the shared rule; an incomplete row neither supplies representative content nor blocks a complete duplicate. Attention follows the shared rule over the scoped rows: a word asks for a meaning only when no scoped row holds one, with the separator warning replacing that request when such a row could not be split, and a phrase keeps only the separator warning. Collections display, search, eligibility, and review content resolution all apply this one rule.

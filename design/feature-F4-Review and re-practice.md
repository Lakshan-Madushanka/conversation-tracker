# Review and re-practice

## Summary

Review and re-practice turns vocabulary, phrases, and grammar saved in **sessions or notebooks**, plus session-only mistakes and past conversations, into **short, active exercises**. The learner flips **flashcards**, **fixes sentences** that contain a mistake, **answers an old question out loud** and compares it with the more natural version, and **fills in the blank** in saved phrases, corrections, and grammar notes. After each item the learner rates how it went (**Hard, Okay, or Easy**), and the app uses those ratings to bring hard items back sooner and easy items back later — separately for each scoped item.

Notebook integration is a **planned requirement**, aligned with [Notebooks](feature-Notebooks.md), not a claim of implemented or tested behaviour. The one-off chooser in this feature is **Quick review** (previously called "Custom review" here), not [saved Custom reviews (F13)](feature-Custom%20reviews.md). Daily/Quick reviews affect scoped ratings/schedules and review-activity reporting; saved custom attempts are isolated and retain snapshots.

Every day the Home screen suggests a **short review of about five minutes** with the items most worth revisiting. The learner can also choose their own review by topic, by collection, or from starred items only. Reviews are calm and pressure-free: no timers, no scores, and no judgment, just a steady way to make new words and corrected mistakes stick.

### Assumptions

- The app is for **one learner** (personal use). All review material and history belong to that learner.
- Practice sessions, Library, and Collections supply existing saved content, session grouping, stars, and statuses. Notebook integration adds **source-scoped identities** without changing session grouping: notebook words/phrases use **notebook identity + kind + normalised main text**, and grammar notes use **individual saved-row identity with source checks**. Matching text in another notebook or a session is a separate item.
- **Session sources must be finished and not archived** (the study sessions rule). Unfinished sessions never contribute; archive hides their items and restore returns them. **Saved notebook entries need no finish step** and can feed daily/Quick Review even when the learner has no sessions. Notebooks are hard-deleted, never archived.
- **Vocabulary** (word, meaning, example), **phrases** (phrase, meaning/use, example), and **grammar notes** come from either source. **Mistakes** (what I said, correction, reason) and **past questions** (tutor's question plus natural version) remain session-only. Grammar uses the existing bold-key-part or quoted-example rules, not an invented notebook exercise.
- **Items that cannot make an exercise are skipped, not hidden in Collections or the notebook.** Shared attention and per-exercise eligibility remain distinct: a phrase without a meaning need not be an attention error, but cannot make a meaning flashcard; usable-example rules still apply. Show guidance/counts for missing required content and grammar formatting, never borrowing a meaning, example, correction, or eligibility from another scope.
- Review is **self-assessed**. The app shows the answer and the learner judges how it went. The app does **not** automatically mark typed answers right or wrong, because many corrections and sentences have more than one good answer.
- **Speaking is done out loud, without recording.** Voice recording and pronunciation are future ideas (F8) and are not part of this feature.
- A daily/Quick rating applies to the **scoped item**, not one exercise type: rating a notebook word Easy on a flashcard affects that same item's other exercise kinds, never a matching session word or another notebook's word.
- Daily/Quick history and schedules **follow the scoped item** under the existing edit rules. Notebook typo-following stays within that notebook and successful save, respecting remaining old appearances and existing destination state; normalisation-only changes do not move state. Grammar edits keep row identity. Disappeared state remains under its original identity and returns only with that identity, never through a same-named replacement notebook or same-spelling item in another scope.
- Multiple comma-separated meanings and duplicate word/phrase rows within one notebook remain one item; grammar rows never merge. Content/eligibility uses the deterministic shared duplicate-row representative rule still **open in Notebooks**, not accidental SQL row order or session dates.
- **Learned items** (marked by the learner in Collections) are left out of the daily suggestion, but can be included in a Quick review. Reviewing a learned item never changes its status automatically.
- A "day" is the learner's **own local day**.
- **Schedule gaps start as:** a **Hard** item returns by the next day, an **Okay** item after about three days, and an **Easy** item after about a week, with the gap growing after repeated Easy ratings up to a sensible limit (about two months). These are a starting point to tune after using the app.
- **Hard items are scheduled for the next day** and are **not** repeated within the same review.
- **The daily review contains about 8 items** (about five minutes) by default.
- **Suggestions to mark an item Learned or Still difficult** after several Easy or Hard ratings are part of this feature, and the learner always confirms them.
- Review needs an internet connection (offline use is an open question in the spec).
- **Reviewing does not change session-based streaks, practice days, or session-content totals.** Notebook creation/editing is not a finished session or proof of learning. Daily/Quick notebook practice belongs in review-activity reporting alongside session-sourced practice; exact F5 measures and source-deletion/history treatment remain open. Saved custom attempts remain excluded.

### Dependencies

- **Practice sessions:** source of all session entry kinds and the only source of mistakes, questions, and natural versions, with the reading page for opening the original session.
- **Library:** the study sessions rule, shared text normalisation, and session archive/restore/delete behaviour, unchanged by notebooks.
- [Notebooks](feature-Notebooks.md): saved vocabulary, phrases, and individual grammar notes; shared topics; source-scoped content/identity, edit/deletion rules, and Open notebook navigation. Duplicate-row representative selection remains open there.
- **Collections:** scoped identity, the shared "Needs attention" rule, and scoped stars/statuses used for suggestions. Review may suggest a status change; only learner confirmation changes that scoped mark.
- [Custom reviews](feature-Custom%20reviews.md): separate saved named sets (F13), not this feature's Quick chooser. Their fixed membership and started snapshots do not adopt daily/Quick live-source handling, and their attempts never feed daily ratings/schedules, recent results, marks, streaks, or Progress.
- **Base app layout:** the main navigation (with a Review link), the **focus layout** used by exercises, the page header, and the empty-state pieces.
- **Home screen:** shows today's review suggestion and starts it.
- **Progress and motivation (F5):** daily/Quick review-activity integration for notebook and session sources is in scope. Define exact measures, repeated-practice handling, wording, and source-deletion/history effects before implementation. Session-derived totals, streaks, and practice days stay unchanged, and saved custom attempts are excluded.
- **Export and backup (F7):** review history and schedules should be included in the data the learner can download, once that feature exists.
- **Voice and pronunciation (F8):** future. Say-it-again must leave room for recording and playing back the learner's voice.

### Related notes

- **Experience principles that apply:** simple first, calm and encouraging (mistakes are learning opportunities), quick to use on any device, distraction-free, and rewarding.
- **Spec goals:** make re-practicing easy and enjoyable, not like homework. **Success measures:** the learner practices review several times a week, repeats the same mistakes less often, and remembers more of the words and phrases they have learned.
- **User flow in the feature list:** the Home screen suggests today's review ("5 minutes: 8 items to review"), the learner taps **Start review**, sees a card, tries to recall, taps **Show answer**, rates it **Easy, Okay, or Hard**, and moves on, possibly through a different kind of exercise. A short summary at the end shows how many were easy and how many need more practice, and hard items are scheduled to come back soon. The learner can also open **Quick review** and choose a topic, a collection, or starred items only, and how many items.
- **Screens in the feature list:** **Review Home** (today's suggested review with a Start review button, a Quick review option, and a short list of recent results) and the **Exercise screen** (distraction-free, one item at a time, a thin progress bar and an Exit button, a large card, a Show answer button, and three large Hard, Okay, and Easy buttons), plus a **finish screen** with a friendly summary and a Done button.
- **Stepper decision (made later):** when a review contains more than one kind of exercise, the exercise screen shows a **non-linear stepper with one step per exercise kind**, so the learner can start with the kind they like and move between kinds freely. Inside each step, items are still shown one at a time in the distraction-free layout.
- **Example journey in the spec:** the next morning Home suggests a five-minute review of three flashcards, two mistake-fixing exercises, and one say-it-again question. The learner completes it and rates the items, and the hard ones come back sooner. (The streak is not part of this, because it comes only from finished sessions.)
- **Existing visual reference:** the layout mockups already show the Home "Today's review" card and the focus layout for a flashcard with Hard, Okay, and Easy buttons.
- **Not part of this feature:** recording/playing back voice, automatic marking, or changes to session-based streaks, practice days, and totals. Progress screen design remains F5's responsibility; daily/Quick review-activity integration is in scope once its open measures/deletion rules are decided. Saved custom attempts remain outside that integration.

## Goals

- Give the learner a quick, enjoyable way to re-practice what they have saved, so words and corrected mistakes actually stick.
- Make it effortless to start: one clear suggestion every day, ready in one tap.
- Bring hard items back sooner and easy items back later, so time is spent where it helps most.
- Give the learner control: they can choose a topic, a collection, or starred items only, and how many items.
- Offer variety (flashcards, fixing sentences, speaking, and filling in blanks) so reviews do not feel repetitive.
- Keep every review calm, short, and free of pressure, and reachable on a phone as comfortably as on a computer.
- Support the spec's success measure: the learner reviews several times a week and repeats the same mistakes less often.

## Requirements

### Review Home and the daily review

1. **Review** is reachable from the main navigation. Its page, **Review Home**, shows today's **suggested review**, a **Quick review** option, and a short list of **recent results**.
2. Today's suggestion is a **short set of items about five minutes long** (by default about 8 items), chosen automatically from the items most worth revisiting today. It shows an honest estimate such as "About 5 minutes · 8 items" and a short description of its mix (for example "3 flashcards, 3 mistakes to fix, 2 questions to say again").
3. A **Start review** button begins it. The same suggestion appears on the **Home screen**, where it starts the same review.
4. The suggestion favors items that are **due**, items the learner found **Hard**, items marked **Still difficult**, **starred** items, and items never reviewed before. It leaves out **Learned** items unless they are due again.
5. The suggestion mixes **different kinds of exercise** where the learner's content allows it, and fills any gaps with other kinds, so it is never empty just because one kind of content is missing.
6. After the learner **completes** the day's review (every item rated or skipped), the suggestion changes to a friendly **"You've reviewed today"** message, with an option to **review a bit more** from the next items worth revisiting. It never nags.
7. When **nothing is worth reviewing right now**, a friendly message points to Quick review, saving notebook entries, or recording a session.
8. The empty state depends on **reviewable items across both sources**, not on finished-session count. A notebook-only learner with eligible items can review normally; otherwise explain how to add/fix notebook content or finish a session, with appropriate actions. Never require a session just to review notebook items.
9. Review Home counts **scoped items waiting for a fix**, with guidance and links to the relevant Collections attention view or source entry. Counts cannot merge matching words across scopes or equate all per-exercise ineligibility with an attention error.
10. Review Home also counts unusable **grammar notes from either source**, with the existing bold-key-part/quoted-example tip and a link to the relevant Grammar notes view/source.
11. The **recent results** list shows only daily and Quick reviews: date, item count, and Easy/Okay/Hard counts. It includes notebook-sourced practice, never saved custom attempts. Daily/Quick review-activity reporting in Progress is also in scope, with exact F5 measures and deletion/history effects still open; it does not alter session totals, streaks, or practice days.

### Choosing what to review (Quick review)

12. **Quick review** is a one-off chooser, not a saved F13 custom review. The learner chooses one/several **shared topics**, collections (words, phrases, grammar notes, mistakes, past questions), and **starred items only**. Notebook items match their notebook's topic; session items retain existing topic/appearance rules. Vocabulary, phrases, and grammar can come from either source; mistakes/questions remain session-only.
13. The learner chooses **how many items**, from a short list of sensible options (for example 5, 10, 15, or 20).
14. The learner can choose to **include Learned items**, which are left out by default; marks are read for the scoped item only.
15. Before starting, Quick review shows how many **distinct eligible scoped items** match (for example "12 items match"), with guidance when too few match. Same-spelling items from different notebooks/sessions count separately and are labelled by source; duplicate notebook word/phrase rows or comma-separated meanings count once. Identical grammar rows remain separate.
16. Quick review mixes exercise types and uses the same scoped rating/scheduling and live-source rules as daily review. It records practice history, but does not create a saved named set or invoke F13's Finished status.

### Exercises in general

17. A review shows **one item at a time** in a **distraction-free layout** (no main navigation), with a thin **progress bar** (for example "4 of 8 rated"), an **Exit** button, and one clear next action. When the review has more than one kind of exercise, a **stepper** (see below) lets the learner move between kinds. There are **no timers** and no time pressure.
18. The exercise kinds are **flashcards**, **fix-the-mistake**, **say-it-again**, and **fill-in-the-blank**. Each item is shown as the kind of exercise that suits its content.
19. After the answer is revealed, the learner rates the item **Hard**, **Okay**, or **Easy** with three large buttons that use clear text (never color alone). Nothing else is needed to continue.
20. The learner can **undo the last rating** briefly (for example for a few seconds, or until they answer the next item), in case of an accidental tap.
21. Only items that can make a good exercise are used. **Items missing a needed part** (a word with no meaning, a mistake with no correction, a question with no natural version, a grammar note with no usable bold part or quoted example) are skipped, and nothing breaks.
22. Daily/Quick reviews retain **live-source behaviour**: if a scoped item is deleted, its notebook is hard-deleted, its session is archived, or required content becomes unusable during practice, skip it gracefully and adjust counts. If its text changes and remains usable, show current content from that source only. Never rescue it with matching text elsewhere. A duplicate row still present in the same notebook can preserve the scoped item. This differs from saved custom attempts, which retain started snapshots.
23. The learner can **exit at any time**. Ratings already given are **kept**, and nothing is lost. Leaving mid-review never penalizes the learner. Exiting leaves immediately, while **finishing early** (see below) shows the summary first.

### Moving between exercise kinds (the stepper)

24. When a review contains **two or more kinds of exercise**, a **stepper** appears at the top of the exercise screen with **one step per kind**, in a fixed, friendly order (flashcards, fix-the-mistake, say-it-again, fill-in-the-blank). Each step shows its **name, an icon, and its progress** (for example "Flashcards 2 of 5"). When a review has only **one kind**, there is no stepper and the plain progress bar is shown.
25. The steps are **not linear**. The learner can open **any step at any time**, in any order, without finishing the previous one. No step is ever locked.
26. Inside a step, items are shown **one at a time**, in a steady order, in the same distraction-free layout. Each step **remembers its place** (its next unrated item), so the learner can leave a step half-finished and come back. Switching steps never loses anything.
27. Items in a review are grouped into **one list per kind**. Within a kind, due and new items are mixed fairly and the order stays stable once the review has started. No order is imposed across kinds.
28. Each step shows its **state** in a way that never relies on color alone: **active** (the one being shown), **completed** (every item in it rated or skipped, with a check mark), or **not finished** (with its count). Opening a completed step shows a calm "All done here" panel with its Easy, Okay, and Hard counts and a button to continue with the next step that is not finished.
29. The review opens on the first step, in the suggested order, that still has unrated items. A refresh or a return puts the learner back on the **step and item** they were on.
30. The thin overall progress bar remains, showing how many items are rated across all steps (for example "4 of 8 rated").
31. The learner can **finish the review early** once at least one item is rated. When items are unrated, a short, friendly confirmation says how many and that they will stay due. Finishing early shows the finish screen for the items rated, and unrated items are not rescheduled.
32. A review is **complete** when every item is rated or skipped. Only a completed daily review changes the suggestion to "You've reviewed today". Finishing early keeps the ratings, and the suggestion simply shrinks to what is still worth revisiting.
33. **Undo** works across steps: it returns the learner to the item that was rated, and to its step, for a few seconds, whichever step they are on.
34. A step whose items were all removed or skipped during the review is left out of the stepper, and the counts adjust calmly.
35. The stepper is **accessible navigation among sections**. It has a clear accessible name, each step is a keyboard-reachable control (arrow keys move between steps, Enter or Space opens one), and names, counts, and states are read aloud. Opening a step moves focus to that step's current item and announces it calmly. Rating shortcuts and typing are never affected by the stepper.
36. On phones the stepper is **compact** (an icon and a count for each step, with the active step's name shown clearly), each step is at least 44 px to tap, and nothing scrolls sideways.

### Flashcards

37. A flashcard shows a **word or phrase** on the front. The learner tries to recall its meaning, then taps **Show answer** to reveal the **meaning (or use)** and the **example**, if there is one.
38. Flashcards work for **vocabulary** and **phrases**. A word or phrase without a meaning (or use) is not shown as a flashcard.
39. The card labels its source and offers **Open original session** for session context or **Open notebook** at the notebook entry. Notebook context shows its name and shared topic, not a fabricated session date/question number. Deleted sources have a calm unavailable indication, never a broken link or same-spelling fallback.

### Fix the mistake

40. **Fix-the-mistake** shows a **sentence that contains a mistake** the learner made (what they said), and asks them to type the corrected version.
41. The learner types their attempt and taps **Check**. The app then shows the learner's attempt next to the **correction** and the **reason** (the tutor's explanation), if there is one. The app does not mark the attempt right or wrong.
42. The learner rates the item Hard, Okay, or Easy. Typing is optional: the learner can also choose to just think about the answer and tap **Check**.
43. Fix-the-mistake uses **mistakes that have a correction**. Mistakes without a correction are skipped.

### Say it again

44. **Say-it-again** shows a **question from a past session** (the tutor's question) and asks the learner to **answer it out loud**, in their own words.
45. The learner taps **Show natural answer** to see the **more natural version** saved with that question, and compares it with what they said.
46. The learner rates the item Hard, Okay, or Easy.
47. Say-it-again uses questions that have **both a tutor's question and a more natural version**. Other questions are skipped.
48. No recording is made. The app does not listen. (Recording is a future idea.)

### Fill in the blank

49. **Fill-in-the-blank** shows a **sentence or phrase with a word missing**, and the learner types the missing word, then taps **Check** to see the full text, with the missing word highlighted. The app does not mark the attempt right or wrong.
50. Blanks are built automatically from the learner's saved content: **example sentences of vocabulary** (the word itself is blanked), **phrases and their examples** (a key word is blanked), **corrections of mistakes** (the words that changed from what the learner said are blanked, with the reason shown as a hint), and **grammar notes** (see below).
51. The app chooses the blank sensibly and **skips items that have no suitable text**. Sentences are shown exactly as the learner saved them, apart from the blank.
52. The learner rates the item Hard, Okay, or Easy.
53. **Grammar notes from either source** become exercises through the part the learner has **bolded**. A note qualifies when a sentence or list item contains a **bold part of one to four words**. That part is blanked in its original sentence/list item. The hint names the actual source's shared topic; a notebook note also identifies its notebook and offers Open notebook, never invented session context.
54. If a note has **several bold parts**, one is chosen, and the same one is used throughout a review. If a note has **no bold part** but contains a **quoted example sentence**, a key word of that sentence is blanked, using the same sensible choice as for phrases. Notes with neither, whose bold part is **longer than four words**, or whose whole text is bold, are **skipped** and never shown as broken exercises.
55. Checking shows the **full note** with supported formatting and the blanked part highlighted. The **individual saved row is the item**, with its source checked: its scoped ratings/schedule stay through text edits and remain under that identity when unavailable. Two notes with identical text remain separate within or across sources; recreating text in a different row never inherits the deleted row's state.
56. Grammar notes count toward the daily suggestion's fill-in-the-blank mix.

### Ratings and scheduling

57. Every rating is **saved immediately**, safely, and **once** (a double tap or a retry never counts twice).
58. Each item has a **schedule** that decides when it is worth revisiting. Items **never reviewed** are due right away. A rating of **Hard** brings an item back **soon** (by the next day). **Okay** brings it back after a few days. **Easy** brings it back after a longer time, and **repeated Easy ratings** make the gap longer and longer, up to a sensible limit.
59. A Hard rating **resets** an item's gap, so something that was getting easy but slips returns soon again.
60. Nothing disappears from review forever. Even items rated Easy repeatedly come back eventually.
61. An item's schedule is the same whichever kind of exercise it was reviewed with.
62. Schedules and ratings belong to the **scoped item**. They **follow it when the learner fixes a typo** in a session or a successfully saved notebook edit (using the same rule as marks in Collections, confined to that source scope), are **kept quietly** when the item disappears, and **return** when it comes back. A remaining duplicate appearance in the same notebook keeps the old state; existing destination state is never overwritten, and another scope is never a destination.
63. The learner's **status** (Learned or Still difficult) only influences what is suggested. Review never changes a status automatically.

### Finishing a review

64. At the end, a **finish screen** shows a friendly summary: how many items were reviewed and how many were **Easy**, **Okay**, and **Hard**, a short note that hard items will come back soon, and a **Done** button. It also offers to **review a little more** when there are more items worth revisiting, and a way to **open the Hard items in Collections**.
65. When the learner has rated an item **Easy several times in a row**, the summary may gently suggest **marking it as Learned**, and when they have rated it **Hard several times**, it may suggest **marking it as Still difficult**. These are only suggestions. The learner confirms or ignores each one, and Review never changes a status by itself. The number of ratings needed is one clearly named setting.
66. The finished review is added to the **recent results** on Review Home, and is also shown with the Home suggestion changing to "You've reviewed today."
67. Wording on the summary is **always encouraging**. Hard items are described as "worth another look", never as failures.

### Staying in step

68. Review reflects the learner's sources **straight away**. New finished sessions and successfully saved notebook entries add reviewable items, edits change the text shown in their own scope, and archived/deleted sessions or hard-deleted notebooks/entries remove their items from future reviews. Restored sessions bring their items, with their schedules, back. A deleted notebook item is never replaced by same-spelling content from another scope.
69. Starring an item or changing its status in Collections changes what Review suggests from then on.
70. The counts and estimates on Review Home and Home (items, minutes, mix) are always correct for what would actually be shown.

### Experience

71. Review works comfortably on a phone, tablet, and computer, with large, easy-to-tap buttons and no sideways scrolling.
72. Everything works with the keyboard and a screen reader. Showing the answer, rating, checking, and exiting all have clear keyboard actions and visible focus. Ratings can be given with the keyboard (for example with number keys), with the shortcuts shown on screen, and shortcuts never interfere while the learner is typing an answer.
73. Moving to the next item and revealing answers are announced calmly to screen readers, and progress ("4 of 8") is available to them.
74. Wording is friendly and encouraging. Nothing about Review makes the learner feel judged, rushed, or scored.

### Edge cases

- **Very few reviewable items:** a review with only one or two items still works, with a gentle note that more items will appear as the learner finishes sessions or saves notebook entries.
- **No items due:** the daily suggestion explains that nothing needs review, and Quick review is still available.
- **All items Learned or Easy:** the daily suggestion can be short or empty, and Quick review can still include learned items.
- **Items missing a needed part:** skipped, counted on Review Home, and linked to Collections.
- **An item removed, archived, hard-deleted, or edited mid-review:** skipped gracefully, or shown with its current text from its own scope. A same-spelling item elsewhere is never substituted.
- **The same item as different exercise types:** shown at most once per review, so the learner never sees the same item twice in one sitting.
- **A phrase and a word with the same text:** treated as separate items, because they are different kinds.
- **The same spelling in a notebook and a session (or two notebooks):** separate scoped items with independent ratings and schedules; rating one never reschedules the others.
- **A typo fixed in a session or a saved notebook edit:** the item's schedule and ratings follow it within that scope, using the same rule as marks. A remaining duplicate in the notebook keeps the old state; an existing destination is never overwritten.
- **A notebook hard-deleted during or after practice:** its scoped items leave future selection; stored schedules stay under the old identity and are not inherited by a same-named replacement notebook or a same-spelling item elsewhere.
- **A double tap or retried request on a rating:** counted once.
- **Two tabs reviewing the same items:** ratings from both are kept, the later rating wins for the schedule, and nothing breaks.
- **A review left open for a long time:** continues normally, and the day's suggestion recalculates for the next visit.
- **Network trouble while rating:** the rating stays on screen as pending, is retried, and a calm message appears if it still fails. Ratings are never silently lost.
- **Leaving with unsaved ratings:** the browser's warning appears only if a rating could not be saved.
- **Time zones and midnight:** "due today" follows the learner's local day, including across midnight and daylight saving changes.
- **Very long words, phrases, or sentences:** shown completely and readably in the card, wrapping neatly.
- **Special characters and non-English letters:** shown correctly, and typed answers accept them.
- **Formatted text** (bold or italic in examples and natural versions): shown safely with only the supported formatting.
- **Typed answers:** never judged. Empty answers are allowed.
- **Many items to choose from:** thousands of items still produce today's suggestion and a Quick selection quickly.
- **A review with only one kind of exercise:** no stepper is shown, only the plain progress bar.
- **Steps opened in any order:** nothing is lost, each step keeps its own place, and ratings are saved per item.
- **A step left unfinished:** its items stay unrated and are not rescheduled, and the learner can come back to it any time during the review.
- **Finishing early with unrated items:** a friendly confirmation first, then a summary of the items rated, with no "You've reviewed today" message.
- **A step whose items all disappear:** left out of the stepper, with counts adjusted.
- **Switching steps quickly or undoing across steps:** the rating and the place in each step stay correct.
- **Two tabs reviewing:** each tab has its own step and place, and ratings from both are kept.
- **A kind that is not available** (nothing suitable, or not built yet): no step for it.
- **Narrow screens:** the stepper shows icons and counts, with the active step's name, and never scrolls sideways.
- **A grammar note with several bold parts:** one is used, consistently within a review.
- **A grammar note that is entirely bold, or whose bold part is longer than four words:** skipped.
- **A grammar note with no bold part but with a quoted example sentence:** a key word of that sentence is blanked.
- **A grammar note with neither:** never used, and counted on Review Home with a friendly tip.
- **A grammar note edited after it was rated:** keeps its schedule, and the blank may change if the bold part changed.
- **Bold text inside a list item:** the list item is the sentence that is shown.
- **A very long grammar note:** only the sentence or list item with the blank is shown during the exercise, and the full note is shown, scrollable, when checking.

## Technical

This describes the intended approach at a high level, using the agreed stack (Laravel, Inertia, Vue, Tailwind, shadcn-vue). It is a plan to refine, not a final design.

1. **Build on what exists.** Use the Collections **scoped item identity** and grouping, the **study sessions** rule for session sources, the learner's scoped stars and statuses, the shared "Needs attention" rule, the shared text normalization, the reading page, the focus layout, and the existing Review placeholder pages. Saved notebook entries join candidate selection without a finish step; notebook words/phrases use notebook identity + kind + normalised text, and grammar notes keep their saved-row identity. Resolve the [Notebook duplicate-row representative rule](feature-Notebooks.md#open-questions) before dependent content resolution.
2. **Decide what can be reviewed.** Define, in one place, which items can become which exercise: flashcards from words and phrases with a meaning, fix-the-mistake from mistakes with a correction, say-it-again from questions with a natural version, and fill-in-the-blank from vocabulary examples, phrases and their examples, corrections, and grammar notes (through their bold parts or quoted example sentences). Items missing a needed part are skipped. Resolve content and eligibility only within the item's source scope; another scope's matching text never supplies a missing part. Keep this separate from the screens so every exercise and the daily suggestion use the same definition.
3. **Store review history and schedules.** Add a place to keep, for each learner and **scoped item**, its **schedule** (when it is next worth revisiting, how many times it was rated, and how many Easy ratings in a row), and a record of **each review** (when, which items, and each rating). Tie schedules to the scoped identity so they follow typo fixes within the source scope, are kept when items disappear, and return only with that identity. Notebook typo-following runs in the successful notebook save under the same remaining-appearance and destination rules as marks. Make saving a rating safe to repeat, with one rating counted once.
4. **Create a simple, explainable schedule.** Use a small, readable rule (a few clearly named intervals, growing with repeated Easy ratings and resetting on Hard) kept in one place, so it can be tuned without touching the screens.
5. **Choose today's items.** Build the daily suggestion in one place from eligible items across sessions and saved notebooks: due items first, boosted by Hard, Still difficult, starred, and never-reviewed items, leaving out Learned unless due, mixing exercise types, never repeating a scoped item, and filling gaps from other kinds. Use the same selection piece for Quick reviews with the learner's choices. Work this out efficiently in bulk, with a bounded number of database queries however many items exist.
6. **Create Review Home.** A Vue page in the main layout with today's suggestion, the Quick review option, items waiting for a fix, and recent results. Reuse the Home screen's card for today's suggestion.
7. **Create the exercise screen.** A page in the focus layout with a **section for each exercise kind**, a **non-linear stepper** (one step per kind, shown only when there is more than one), a thin overall progress bar, Exit and Finish early, the answer reveal, the rating buttons, undo across steps, and keyboard shortcuts. Build each exercise kind as a small piece registered as a section with its own items and its own place, so adding a kind only adds a step.
8. **Build the four exercise kinds.** Flashcards, fix-the-mistake, say-it-again, and fill-in-the-blank, each following the rules above, with the blank chosen automatically and safely. For grammar notes, find the bold part (or quoted example) in the note's saved formatting, using the same safe handling of formatted text as elsewhere, and keep the choice of blank in one place so it is predictable and testable.
9. **Create the finish screen and suggestions.** A summary of the review, gentle suggestions to mark items as Learned or Still difficult that the learner confirms, and links back to Collections.
10. **Add Quick review.** The choices for topics, collections, starred only, number of items, and learned items, with a live count of matching items, using the same selection piece.
11. **Keep Review in step.** Make sure finishing, editing, archiving, restoring, and deleting sessions, saving and hard-deleting notebook entries/notebooks, and changing stars and statuses, update what Review suggests straight away, without keeping copies that can go out of date. Scope every content/eligibility read to the item's source.
12. **Connect to other features.** Expose review history in a stable form for Progress and Export later, keep session-based streaks separate, and leave room for voice recording in say-it-again. Daily/Quick review-activity reporting for notebook and session sources is in scope, but its exact F5 measures and deletion/history treatment await the [Notebook open decisions](feature-Notebooks.md#open-questions); saved custom attempts stay excluded.
13. **Make it work everywhere.** Test on phone, tablet, and computer, in light and dark mode, with the keyboard and a screen reader, and with large numbers of items.

### UI components: use shadcn-vue wherever possible

All interface pieces in Review should come from **shadcn-vue**, the component library already chosen for the app. Only build a custom piece when shadcn-vue has nothing suitable, and then build it by **composing shadcn-vue parts with Tailwind**. Do not add any other component library.

| Review need                                                           | shadcn-vue component to use                                                                      |
| --------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| Flashcard, and Review Home cards                                      | Card                                                                                             |
| Show answer, Check, Hard / Okay / Easy, Start review, Done, Exit      | Button (rating buttons use clear text and distinct styles, never color alone)                    |
| Overall progress bar ("4 of 8 rated")                                 | Progress                                                                                         |
| Exercise-kind steps (non-linear, one per kind, with icons and counts) | Stepper, with its linear option turned off and a custom look built from Button, icons, and Badge |
| Typed answers (fix-the-mistake and fill-in-the-blank)                 | Input, and Textarea for longer sentences                                                         |
| Labels such as exercise kind, "Needs attention", and results counts   | Badge                                                                                            |
| Quick review choices: topics                                          | Combobox (Popover with Command and Checkbox)                                                     |
| Quick review choices: collections                                     | Toggle Group, or Checkboxes                                                                      |
| Quick review choices: starred only and include learned                | Switch, or Toggle                                                                                |
| Quick review choices: number of items                                 | Select, or Toggle Group                                                                          |
| Quick review panel on phones                                          | Sheet                                                                                            |
| Undo for the last rating and brief messages                           | Sonner (toast)                                                                                   |
| Exit confirmation if needed                                           | Alert Dialog                                                                                     |
| Failure, empty, and no-results messages                               | Alert, and the existing empty-state piece                                                        |
| Loading states                                                        | Skeleton                                                                                         |
| Recent results list                                                   | Card, or Table on wide screens with stacked cards on phones                                      |
| Dividers and layout details                                           | Separator                                                                                        |

Where a shadcn-vue component needs small changes, change its **variants and Tailwind classes** instead of replacing it, so Review stays visually consistent with the rest of the app, in light and dark mode, and keeps the accessibility built into the components.

## Implementation Steps

The steps are ordered so each one leaves a usable, reviewable piece of the feature. The tests below are acceptance requirements, including notebook integration regressions, not evidence of implemented or verified behaviour. Session behaviour keeps its existing rules; notebook integration must satisfy the scoped rules above and the [Notebook acceptance plan](feature-Notebooks.md#step-5-daily-review-and-progress-integration). Resolve the open duplicate-row and Progress decisions before implementing the behaviour that depends on them.

### Step 1: The review screen, flashcards, and ratings

**What it delivers:** A Review Home with a Start review button, the distraction-free exercise screen, **flashcards** for words and phrases, the Hard, Okay, and Easy rating buttons with undo, saving of every rating safely and once, and a finish screen. At this step the items come from a simple selection (reviewable words and phrases, never-reviewed first), and the smart schedule and daily suggestion come in Step 2. The exercise screen is built so that each exercise kind is a **section** with its own list of items and its own place, even though only flashcards exist so far, so no stepper is shown yet.

**Tests**

- The Review link in the main navigation opens Review Home, and the placeholder pages are replaced.
- With only one kind of exercise in a review, no stepper is shown and the plain progress bar is shown.
- The screen keeps a separate place for each section, so more kinds can be added later without changing how flashcards work.
- Start review begins a review of reviewable words and phrases from finished, non-archived sessions and saved notebook entries. Unfinished and archived sessions never contribute; notebook entries need no finish step.
- A notebook flashcard labels its notebook and shared topic and offers Open notebook at the entry, with no fabricated session date or question number. Rating it leaves a same-spelling session or other-notebook item's schedule unchanged.
- A word or phrase without a meaning (or use) is never shown as a flashcard.
- The exercise screen uses the focus layout, with a progress bar ("4 of 8"), an Exit button, and no timer.
- A flashcard shows the word or phrase first, and Show answer reveals the meaning (or use) and example, with formatting shown safely.
- The rating buttons appear after the answer is revealed, use text labels, and each rating is saved immediately and only once, even after a double tap or retry.
- Undo brings back the previous item's rating choice, and only the final rating counts.
- Exiting at any point keeps the ratings already given and loses nothing.
- The finish screen shows how many items were reviewed and how many were Easy, Okay, and Hard, in encouraging wording, with a Done button.
- A review with one or two items works. The empty state considers reviewable items across both sources: a notebook-only learner reviews normally, and a learner with nothing reviewable sees friendly guidance for saving notebook entries or finishing a session.
- Items removed, archived, or edited during a review are skipped gracefully or shown with their current text.
- Network trouble while rating shows a calm message, keeps the rating pending, and retries, and ratings are never silently lost.
- Buttons are at least 44 px, the screens work on a phone, tablet, and computer, in light and dark mode, with no sideways scrolling.
- Everything works with the keyboard (including rating shortcuts that do not interfere with typing), with visible focus and calm screen reader announcements.

### Step 2: The schedule and the daily review

**What it delivers:** Each item's schedule (due dates growing with Easy and resetting on Hard), the **daily suggestion** of about five minutes on Review Home and on the Home screen, the "You've reviewed today" state with an option to review a bit more, and the count of items waiting for a fix.

**Tests**

- A never-reviewed item is due right away. A Hard rating makes an item due by the next day, Okay after a few days, and Easy after a longer time.
- Repeated Easy ratings make the gap longer up to the limit, and a Hard rating resets the gap.
- Nothing disappears from review forever, and the rule lives in one place.
- Today's suggestion contains about 8 items and "about 5 minutes", chooses due items first, favors Hard, Still difficult, starred, and never-reviewed items, and leaves out Learned items unless due.
- The suggestion never repeats an item, and shows an honest mix description.
- Home and Review Home show the same suggestion, and starting from either begins the same review.
- After the day's review, the card changes to "You've reviewed today", and "Review a bit more" offers the next items worth revisiting.
- With nothing due, a friendly message appears with Quick review and links to save notebook entries or record a session.
- Review Home shows how many items are waiting for a fix and links to "Needs attention only" in Collections.
- "Due today" follows the learner's local day, including across midnight and daylight saving changes.
- The suggestion changes after a session is finished, edited, archived, restored, or deleted, after a notebook entry is saved or hard-deleted, and after a star or status change.
- With thousands of items, the suggestion appears quickly, and the number of database queries does not grow with the number of items.
- Each rating changes only that item's schedule, whichever exercise it was shown with.
- Schedules follow a scoped item when a typo is fixed in a session or a saved notebook edit, are kept when the item disappears, and return only with that identity, never through a same-spelling item in another scope.

### Step 3: Fix-the-mistake

**What it delivers:** The fix-the-mistake exercise using mistakes that have a correction, with typed answers, Check, the side-by-side correction and reason, and rating. It also introduces the **stepper**: when a review has flashcards and mistakes to fix, the exercise screen shows a non-linear stepper with one step per kind.

**Tests**

- Only mistakes with a correction become exercises, from finished, non-archived sessions. Mistakes without a correction are skipped.
- A review with flashcards and mistakes shows a stepper with two steps, each with its name, icon, and progress (for example "Flashcards 2 of 5"), and a review with only one kind shows no stepper.
- Any step can be opened at any time, in any order, without finishing the previous one, and no step is locked.
- Each step remembers its place (its next unrated item), and leaving a step half-finished and coming back loses nothing.
- Each step shows active, completed (check mark), or not finished (count) without relying on color alone, and a completed step shows a calm "All done here" panel with its counts and a way to continue.
- The review opens on the first step in the suggested order that has unrated items, and a refresh returns to the same step and item.
- Finishing early is available once an item is rated, asks first when items are unrated, shows the summary for rated items, does not reschedule unrated items, and does not show "You've reviewed today".
- Undo returns to the item that was rated and its step, even from another step.
- The stepper is keyboard-reachable (arrow keys, Enter or Space), has an accessible name, reads names, counts, and states aloud, moves focus to the current item when a step opens, and never interferes with rating shortcuts or typing.
- On phones the stepper is compact, each step is at least 44 px, and nothing scrolls sideways.
- The sentence shown is exactly what the learner said, and long text wraps neatly.
- The learner can type an answer, or leave it empty, and Check always works.
- Check shows the learner's attempt beside the correction and the reason, with formatting shown safely, and never marks the attempt right or wrong.
- The reason is shown only when there is one.
- The learner rates the item, and the rating is saved once and applies to the mistake's schedule.
- A mistake group that appears several times is shown once per review.
- Special characters and non-English letters can be typed and are shown correctly.
- Typed text never injects markup.
- The exercise is usable on a phone (with the on-screen keyboard not hiding the input or the buttons), in light and dark mode, with the keyboard and a screen reader.
- The daily suggestion includes fix-the-mistake items in its mix when mistakes are available.

### Step 4: Say-it-again

**What it delivers:** The say-it-again exercise using past questions and their more natural versions, with Show natural answer and rating. Say-it-again appears as a **third step** in the stepper when questions are available.

**Tests**

- Only questions that have both a tutor's question and a more natural version, from finished, non-archived sessions, become exercises.
- Say it again appears as its own step in the stepper, with its name, icon, and progress, and can be opened at any time and in any order.
- A review with no usable questions shows no say-it-again step.
- The question is shown as saved, with formatting displayed safely.
- Show natural answer reveals the natural version with its formatting, and the learner can compare it with what they said out loud.
- No recording is made and no microphone permission is requested.
- The learner rates the item, and the rating is saved once and applies to that question's schedule.
- A question is shown once per review.
- Open original session leads to the reading page at that question.
- The exercise works on a phone, in light and dark mode, with the keyboard and a screen reader, and the wording explains that the learner answers out loud.
- The daily suggestion includes say-it-again items in its mix when questions are available.
- Questions without a natural version are skipped, and nothing breaks.

### Step 5: Fill-in-the-blank

**What it delivers:** The fill-in-the-blank exercise built automatically from vocabulary examples, phrases and their examples, corrections of mistakes, and grammar notes (through their bold parts or quoted examples), with typed answers, Check, and rating, plus the friendly tip on Review Home about grammar notes that cannot be used yet. Fill-in-the-blank appears as a **fourth step** in the stepper when suitable text is available.

**Tests**

- Blanks are built from vocabulary examples (the word is blanked), phrases and their examples (a key word is blanked), and mistake corrections (the changed words are blanked, with the reason as a hint).
- Fill in the blank appears as its own step in the stepper, with its name, icon, and progress, and can be opened at any time and in any order.
- A review with no suitable text shows no fill-in-the-blank step, and with four kinds the stepper still fits a phone without scrolling sideways.
- Items with no suitable text are skipped, and sentences are shown exactly as saved apart from the blank.
- A grammar note with a bold part of one to four words in a sentence or list item becomes an exercise: the bold part is blanked, the sentence or list item is shown with the blank, and a small hint names the actual source's topic; a notebook note also identifies its notebook and offers Open notebook.
- A note with several bold parts uses one of them, and the same one throughout a review.
- A note with no bold part but a quoted example sentence blanks a key word of that sentence.
- A note with neither, whose bold part is longer than four words, or whose whole text is bold, is skipped and never shown as a broken exercise.
- Checking a note shows the full note with its formatting and the blanked part highlighted, without relying on color alone.
- The note is the item: its rating and schedule belong to it, stay with it when its text is edited, are kept when it disappears, return with it, and two notes with identical text have separate schedules.
- Review Home shows how many grammar notes cannot be used yet, with the tip "Bold the key part of a note to turn it into an exercise." and a link to Grammar notes in Collections.
- Grammar notes count toward the daily suggestion's fill-in-the-blank mix, and a very long note shows only the relevant sentence during the exercise and the full note, scrollable, when checking.
- The learner can type an answer or leave it empty, and Check reveals the full text with the missing word highlighted, without marking the attempt right or wrong.
- The highlight does not rely on color alone and has good contrast in light and dark mode.
- The blank is chosen sensibly and consistently, and the same item gives the same blank within a review.
- Typed text with special characters and non-English letters works, and never injects markup.
- Formatting in examples is shown safely.
- The rating is saved once and applies to the item's schedule.
- An item is shown once per review, whichever exercise it appears as.
- The exercise works on a phone, in light and dark mode, with the keyboard and a screen reader.
- The daily suggestion includes fill-in-the-blank items in its mix when suitable text is available.

### Step 6: Quick review, recent results, and suggestions

**What it delivers:** Quick review (topics, collections, starred only, number of items, include learned), the recent results list, and the finish screen's gentle suggestions to mark items Learned or Still difficult.

**Tests**

- Quick review lets the learner choose topics, collections (words, phrases, grammar notes, mistakes, past questions), starred only, the number of items, and whether to include Learned items.
- A Quick review uses the same stepper rules: one step per kind that has items, hidden when there is only one kind, non-linear, with early finishing.
- The preview shows how many items match, and a friendly message appears when too few match, with a way to change the choices.
- A Quick review mixes exercise types, never repeats an item, and uses the same rating and scheduling rules.
- Only items from finished, non-archived sessions and saved notebook entries are used, and Learned items are left out unless included.
- Quick review counts distinct eligible scoped items: same-spelling items in different notebooks/sessions count separately and are labelled by source, while duplicate notebook rows and comma-separated meanings count once. Shared topics match notebook items through their notebook's topic; mistakes and past questions stay session-only.
- The recent results list shows the date, number of items, and Easy, Okay, and Hard counts of the last reviews, and updates after each review.
- After several Easy ratings in a row, the summary may suggest marking an item Learned, and after several Hard ratings, Still difficult. The learner can confirm or ignore each suggestion, and confirming sets the status, while ignoring changes nothing.
- Review never changes a status by itself.
- The number of ratings needed for a suggestion lives in one named setting.
- The finish screen offers to review a little more and to open the Hard items in Collections.
- The Quick review panel works on a phone, with the keyboard and a screen reader, in light and dark mode.
- Choices and results stay correct after sessions or marks change.

### Step 7: Staying in step, and polish

**What it delivers:** Review that always follows the learner's sessions and marks, plus a full check of speed, wording, accessibility, and consistency.

**Tests**

- Finishing a session or successfully saving notebook entries adds items to future reviews; an unfinished session never contributes, and notebook entries need no finish step.
- In the full journey, steps are opened in an unusual order, a step is left half-finished and finished later, an item is undone from another step, and the review is finished early once and completed fully once, with the daily review message appearing only after a complete review.
- Editing a finished session or a saved notebook entry changes the text shown in later exercises, within that item's own scope only.
- Archiving removes session items from future reviews, restoring brings them back with their schedules, and deleting removes them. Hard-deleting a notebook or entry removes its scoped items without touching matching text elsewhere.
- Starring an item or changing its status changes what is suggested.
- Review history and schedules follow typo fixes within the source scope, are kept when items disappear, and return only with that identity, in every case that Collections' marks handle.
- Counts, estimates, and mixes on Home and Review Home are always correct for what would actually be shown.
- Nothing is stale and only the latest request updates the screen.
- A full journey works: finish a session, see the suggestion appear, start the daily review, work through each kind of exercise, rate items, see the summary, return the next day, see Hard items back sooner and Easy items later, star an item and see it favored, and archive a session and see its items leave.
- A notebook-only journey works: save notebook entries with no sessions, receive a daily suggestion and use Quick review, rate a notebook word without rescheduling a same-spelling session word, then hard-delete the notebook and see its items leave future selection while stored state stays under the old identity.
- Daily/Quick notebook practice changes no session-based streak, practice day, or session-content total, and saved custom attempts stay excluded from recent results and review-activity reporting; exact Progress measures await the Notebook decision.
- Wording is friendly, calm, and consistent, and nothing makes the learner feel judged, rushed, or scored.
- All screens work on a phone, tablet, and computer, with no sideways scrolling, and at 200 percent zoom.
- All text, buttons, and focus outlines have good contrast in light and dark mode, and no meaning relies on color alone.
- Everything is usable with the keyboard alone, and key changes are announced by a screen reader without being chatty.
- Loading, empty, no-results, and error states are friendly and tell the learner what to do next.
- Today's suggestion and a Quick selection stay fast with thousands of items, and no query runs once per item where one query for the page would do.
- A learner can reach only their own review history and schedules, and saved text can never inject anything into the page.
- Interface pieces come from shadcn-vue wherever a suitable component exists, and no other component library has been added.

## Decisions Made

- **Reviewing does not count toward the streak.** Streaks and practice days come only from finished sessions. The example journey in `spec.md` was updated to match.
- **Schedule gaps start as:** Hard returns by the next day, Okay after about three days, and Easy after about a week, growing with repeated Easy ratings up to about two months. These are tuned later after using the app.
- **Status suggestions are included.** After several Easy or Hard ratings, the finish screen may suggest marking an item Learned or Still difficult, and the learner always confirms.
- **Fill-in-the-blank also uses grammar notes**, through the part the learner has bolded (one to four words), or a quoted example sentence as a fallback. Notes with neither are skipped, and Review Home gives a friendly tip. Blanks are built from vocabulary examples, phrases and their examples, corrections, and grammar notes.
- **Typed answers are never marked automatically.** The learner judges how it went.
- **Hard items are scheduled for the next day** and are not repeated within the same review.
- **The daily review defaults to about 8 items** (about five minutes).
- **The exercise screen has a non-linear stepper with one step per exercise kind**, shown only when a review has more than one kind. Items inside a step are shown one at a time in the distraction-free layout, each step remembers its place, and no step is ever locked.
- **Finishing early is allowed** once an item is rated. A review is complete when every item is rated or skipped, and only a completed daily review changes the suggestion to "You've reviewed today".
- **Daily and Quick review draw from both sources.** Eligible session items and saved notebook vocabulary, phrases, and grammar notes are candidates; mistakes and past questions stay session-only. Notebook entries need no finish step, and a notebook-only learner can review normally.
- **The one-off chooser is named Quick review.** It is not the saved, named Custom reviews feature (F13); it starts straight away, is not saved as a set, and never invokes F13's Finished status.
- **Ratings and schedules belong to the scoped item.** Rating a notebook word never reschedules a same-spelling session word or another notebook's word, and exercise content comes only from the item's own scope.
- **Session-based streaks, practice days, and totals are unchanged by reviewing or by notebooks.** Daily/Quick review-activity reporting for both sources is in scope, but its exact measures and deletion/history treatment remain open in Notebooks; saved custom attempts stay excluded.

## Open Questions

No Review-specific decisions remain open. Notebook integration depends on decisions tracked in [Notebooks — Open Questions](feature-Notebooks.md#open-questions): the exact Progress review-activity measures and deletion/history treatment, and the duplicate-row representative content rule shared with Collections. Resolve them before implementing the affected behaviour.

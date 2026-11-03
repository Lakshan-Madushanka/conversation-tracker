# Review and re-practice

## Summary

Review and re-practice turns the learner's saved words, phrases, mistakes, and past conversations into **short, active exercises**. Instead of re-reading old sessions, the learner flips **flashcards**, **fixes sentences** that contain a mistake, **answers an old question out loud** and compares it with the more natural version, and **fills in the blank** in saved phrases, corrections, and grammar notes. After each item the learner rates how it went (**Hard, Okay, or Easy**), and the app uses those ratings to bring hard items back sooner and easy items back later.

Every day the Home screen suggests a **short review of about five minutes** with the items most worth revisiting. The learner can also choose their own review by topic, by collection, or from starred items only. Reviews are calm and pressure-free: no timers, no scores, and no judgment, just a steady way to make new words and corrected mistakes stick.

### Assumptions

- The app is for **one learner** (personal use). All review material and history belong to that learner.
- The **Practice sessions**, **Library**, and **Personal collections** features already exist. Review builds on their saved content, on the Collections **item identity** (the same normalized word, phrase, or pair of what was said and the correction), and on the learner's **stars** and **statuses**.
- **Only finished sessions that are not archived feed Review.** (This is the "study sessions" rule.) Unfinished sessions never appear, archived sessions are hidden and come back when restored, and deleted content is gone.
- Review uses these saved items: **vocabulary** (word, meaning, example), **phrases** (phrase, meaning or use, example), **mistakes** (what I said, the correction, and the reason), and **past questions** (a session's tutor's question together with its more natural version). **Grammar notes** are used for fill-in-the-blank exercises, through the part the learner has **bolded** (or a quoted example sentence), because the app cannot guess the key point of a free-form explanation on its own.
- **Items that cannot be reviewed yet are skipped, not hidden in Collections.** A word with no meaning, a mistake with no correction, or a question with no natural version cannot make a good exercise. They stay in Collections with their "Needs attention" marker, and Review tells the learner how many are waiting for a fix.
- Review is **self-assessed**. The app shows the answer and the learner judges how it went. The app does **not** automatically mark typed answers right or wrong, because many corrections and sentences have more than one good answer.
- **Speaking is done out loud, without recording.** Voice recording and pronunciation are future ideas (F8) and are not part of this feature.
- The learner's rating of an item applies to the **item**, not to one exercise type, so a word rated Easy on a flashcard is treated as easy everywhere. An item can appear as different kinds of exercise on different days.
- Review history and each item's schedule **follow the item** the same way the learner's marks do. They belong to the item's identity, follow it when the learner fixes a typo in a session, are kept quietly when the item disappears, and return if it comes back.
- **Learned items** (marked by the learner in Collections) are left out of the daily suggestion, but can be included in a custom review. Reviewing a learned item never changes its status automatically.
- A "day" is the learner's **own local day**.
- **Schedule gaps start as:** a **Hard** item returns by the next day, an **Okay** item after about three days, and an **Easy** item after about a week, with the gap growing after repeated Easy ratings up to a sensible limit (about two months). These are a starting point to tune after using the app.
- **Hard items are scheduled for the next day** and are **not** repeated within the same review.
- **The daily review contains about 8 items** (about five minutes) by default.
- **Suggestions to mark an item Learned or Still difficult** after several Easy or Hard ratings are part of this feature, and the learner always confirms them.
- Review needs an internet connection (offline use is an open question in the spec).
- **Reviewing does not change the streak.** Streaks and practice days come only from finished sessions (the example journey in `spec.md` was updated to match).

### Dependencies

- **Practice sessions:** the source of questions, natural versions, and all entries, and the **reading page** for opening the original session.
- **Library:** the **study sessions** rule (finished and not archived), the shared text normalization, and archive, restore, and delete, which must be reflected in Review straight away.
- **Personal collections:** the **item identity**, the **shared "Needs attention" rule**, and the learner's **stars** and **statuses**, which Review reads to decide what to suggest. Review may **suggest** a status change, and the learner always confirms it.
- **Base app layout:** the main navigation (with a Review link), the **focus layout** used by exercises, the page header, and the empty-state pieces.
- **Home screen:** shows today's review suggestion and starts it.
- **Progress and motivation (F5):** may later use review history. Review does not feed streaks.
- **Export and backup (F7):** review history and schedules should be included in the data the learner can download, once that feature exists.
- **Voice and pronunciation (F8):** future. Say-it-again must leave room for recording and playing back the learner's voice.

### Related notes

- **Experience principles that apply:** simple first, calm and encouraging (mistakes are learning opportunities), quick to use on any device, distraction-free, and rewarding.
- **Spec goals:** make re-practicing easy and enjoyable, not like homework. **Success measures:** the learner practices review several times a week, repeats the same mistakes less often, and remembers more of the words and phrases they have learned.
- **User flow in the feature list:** the Home screen suggests today's review ("5 minutes: 8 items to review"), the learner taps **Start review**, sees a card, tries to recall, taps **Show answer**, rates it **Easy, Okay, or Hard**, and moves on, possibly through a different kind of exercise. A short summary at the end shows how many were easy and how many need more practice, and hard items are scheduled to come back soon. The learner can also open **Custom review** and choose a topic, a collection, or starred items only, and how many items.
- **Screens in the feature list:** **Review Home** (today's suggested review with a Start review button, a Custom review option, and a short list of recent results) and the **Exercise screen** (distraction-free, one item at a time, a thin progress bar and an Exit button, a large card, a Show answer button, and three large Hard, Okay, and Easy buttons), plus a **finish screen** with a friendly summary and a Done button.
- **Example journey in the spec:** the next morning Home suggests a five-minute review of three flashcards, two mistake-fixing exercises, and one say-it-again question. The learner completes it and rates the items, and the hard ones come back sooner. (The streak is not part of this, because it comes only from finished sessions.)
- **Existing visual reference:** the layout mockups already show the Home "Today's review" card and the focus layout for a flashcard with Hard, Okay, and Easy buttons.
- **Not part of this feature:** recording or playing back the learner's voice, automatic marking, the Progress screen, and streaks.

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

1. **Review** is reachable from the main navigation. Its page, **Review Home**, shows today's **suggested review**, a **Custom review** option, and a short list of **recent results**.
2. Today's suggestion is a **short set of items about five minutes long** (by default about 8 items), chosen automatically from the items most worth revisiting today. It shows an honest estimate such as "About 5 minutes · 8 items" and a short description of its mix (for example "3 flashcards, 3 mistakes to fix, 2 questions to say again").
3. A **Start review** button begins it. The same suggestion appears on the **Home screen**, where it starts the same review.
4. The suggestion favors items that are **due**, items the learner found **Hard**, items marked **Still difficult**, **starred** items, and items never reviewed before. It leaves out **Learned** items unless they are due again.
5. The suggestion mixes **different kinds of exercise** where the learner's content allows it, and fills any gaps with other kinds, so it is never empty just because one kind of content is missing.
6. After the learner finishes the day's review, the suggestion changes to a friendly **"You've reviewed today"** message, with an option to **review a bit more** from the next items worth revisiting. It never nags.
7. When **nothing is worth reviewing right now**, a friendly message says so and points to Custom review and to recording a new session.
8. When the learner has **no finished sessions** (or no reviewable items), a friendly empty state explains that Review fills up after finishing a session, with a button to start one.
9. Review Home mentions how many items are **waiting for a fix** before they can be reviewed (for example "3 items need a meaning or a correction first"), with a link to those items in Collections ("Needs attention only").
10. Review Home also gently mentions how many **grammar notes cannot be used yet** because they have no bold key part or quoted example, with a friendly tip ("Bold the key part of a note to turn it into an exercise.") and a link to Grammar notes in Collections.
11. The **recent results** list shows the learner's last few reviews: the date, how many items, and how many were Easy, Okay, and Hard.

### Choosing what to review (Custom review)

12. **Custom review** lets the learner choose **what** to review: one or several **topics**, one or several **collections** (words, phrases, grammar notes, mistakes, past questions), and **starred items only**.
13. The learner chooses **how many items**, from a short list of sensible options (for example 5, 10, 15, or 20).
14. The learner can choose to **include Learned items**, which are left out by default.
15. Before starting, a custom review shows how many items match the choices (for example "12 items match"), and a friendly message when too few match, with a way to change the choices.
16. A custom review **mixes exercise types** the same way the daily suggestion does, and uses the same rating and scheduling rules.

### Exercises in general

17. A review shows **one item at a time** in a **distraction-free layout** (no main navigation), with a thin **progress bar** (for example "4 of 8"), an **Exit** button, and one clear next action. There are **no timers** and no time pressure.
18. The exercise kinds are **flashcards**, **fix-the-mistake**, **say-it-again**, and **fill-in-the-blank**. Each item is shown as the kind of exercise that suits its content.
19. After the answer is revealed, the learner rates the item **Hard**, **Okay**, or **Easy** with three large buttons that use clear text (never color alone). Nothing else is needed to continue.
20. The learner can **undo the last rating** briefly (for example for a few seconds, or until they answer the next item), in case of an accidental tap.
21. Only items that can make a good exercise are used. **Items missing a needed part** (a word with no meaning, a mistake with no correction, a question with no natural version, a grammar note with no usable bold part or quoted example) are skipped, and nothing breaks.
22. If an item is **removed or archived during a review** (for example in another tab), it is skipped gracefully with no error. If its text is edited, the review shows the current text.
23. The learner can **exit at any time**. Ratings already given are **kept**, and nothing is lost. Leaving mid-review never penalizes the learner.

### Flashcards

24. A flashcard shows a **word or phrase** on the front. The learner tries to recall its meaning, then taps **Show answer** to reveal the **meaning (or use)** and the **example**, if there is one.
25. Flashcards work for **vocabulary** and **phrases**. A word or phrase without a meaning (or use) is not shown as a flashcard.
26. The back of the card also lets the learner open the original session if they want more context.

### Fix the mistake

27. **Fix-the-mistake** shows a **sentence that contains a mistake** the learner made (what they said), and asks them to type the corrected version.
28. The learner types their attempt and taps **Check**. The app then shows the learner's attempt next to the **correction** and the **reason** (the tutor's explanation), if there is one. The app does not mark the attempt right or wrong.
29. The learner rates the item Hard, Okay, or Easy. Typing is optional: the learner can also choose to just think about the answer and tap **Check**.
30. Fix-the-mistake uses **mistakes that have a correction**. Mistakes without a correction are skipped.

### Say it again

31. **Say-it-again** shows a **question from a past session** (the tutor's question) and asks the learner to **answer it out loud**, in their own words.
32. The learner taps **Show natural answer** to see the **more natural version** saved with that question, and compares it with what they said.
33. The learner rates the item Hard, Okay, or Easy.
34. Say-it-again uses questions that have **both a tutor's question and a more natural version**. Other questions are skipped.
35. No recording is made. The app does not listen. (Recording is a future idea.)

### Fill in the blank

36. **Fill-in-the-blank** shows a **sentence or phrase with a word missing**, and the learner types the missing word, then taps **Check** to see the full text, with the missing word highlighted. The app does not mark the attempt right or wrong.
37. Blanks are built automatically from the learner's saved content: **example sentences of vocabulary** (the word itself is blanked), **phrases and their examples** (a key word is blanked), **corrections of mistakes** (the words that changed from what the learner said are blanked, with the reason shown as a hint), and **grammar notes** (see below).
38. The app chooses the blank sensibly and **skips items that have no suitable text**. Sentences are shown exactly as the learner saved them, apart from the blank.
39. The learner rates the item Hard, Okay, or Easy.
40. **Grammar notes** become exercises through the part the learner has **bolded**. A note qualifies when a sentence or list item in it contains a **bold part of one to four words**. That bold part is blanked, and the learner sees the **sentence or list item** it came from, with the blank, and a small hint naming the session's topic.
41. If a note has **several bold parts**, one is chosen, and the same one is used throughout a review. If a note has **no bold part** but contains a **quoted example sentence**, a key word of that sentence is blanked, using the same sensible choice as for phrases. Notes with neither, whose bold part is **longer than four words**, or whose whole text is bold, are **skipped** and never shown as broken exercises.
42. Checking shows the **full note** with its formatting, with the blanked part highlighted. The **note itself is the item**: its ratings and schedule belong to it, stay with it when its text is edited, are kept when it disappears, and return if it comes back. Two notes with identical text remain separate items.
43. Grammar notes count toward the daily suggestion's fill-in-the-blank mix.

### Ratings and scheduling

44. Every rating is **saved immediately**, safely, and **once** (a double tap or a retry never counts twice).
45. Each item has a **schedule** that decides when it is worth revisiting. Items **never reviewed** are due right away. A rating of **Hard** brings an item back **soon** (by the next day). **Okay** brings it back after a few days. **Easy** brings it back after a longer time, and **repeated Easy ratings** make the gap longer and longer, up to a sensible limit.
46. A Hard rating **resets** an item's gap, so something that was getting easy but slips returns soon again.
47. Nothing disappears from review forever. Even items rated Easy repeatedly come back eventually.
48. An item's schedule is the same whichever kind of exercise it was reviewed with.
49. Schedules and ratings belong to the **item**. They **follow it when the learner fixes a typo** in a session (using the same rule as marks in Collections), are **kept quietly** when the item disappears, and **return** when it comes back.
50. The learner's **status** (Learned or Still difficult) only influences what is suggested. Review never changes a status automatically.

### Finishing a review

51. At the end, a **finish screen** shows a friendly summary: how many items were reviewed and how many were **Easy**, **Okay**, and **Hard**, a short note that hard items will come back soon, and a **Done** button. It also offers to **review a little more** when there are more items worth revisiting, and a way to **open the Hard items in Collections**.
52. When the learner has rated an item **Easy several times in a row**, the summary may gently suggest **marking it as Learned**, and when they have rated it **Hard several times**, it may suggest **marking it as Still difficult**. These are only suggestions. The learner confirms or ignores each one, and Review never changes a status by itself. The number of ratings needed is one clearly named setting.
53. The finished review is added to the **recent results** on Review Home, and is also shown with the Home suggestion changing to "You've reviewed today."
54. Wording on the summary is **always encouraging**. Hard items are described as "worth another look", never as failures.

### Staying in step

55. Review reflects the learner's sessions **straight away**. New finished sessions add reviewable items, edited sessions change the text shown, and archived or deleted sessions remove their items from future reviews. Restored sessions bring their items, with their schedules, back.
56. Starring an item or changing its status in Collections changes what Review suggests from then on.
57. The counts and estimates on Review Home and Home (items, minutes, mix) are always correct for what would actually be shown.

### Experience

58. Review works comfortably on a phone, tablet, and computer, with large, easy-to-tap buttons and no sideways scrolling.
59. Everything works with the keyboard and a screen reader. Showing the answer, rating, checking, and exiting all have clear keyboard actions and visible focus. Ratings can be given with the keyboard (for example with number keys), with the shortcuts shown on screen, and shortcuts never interfere while the learner is typing an answer.
60. Moving to the next item and revealing answers are announced calmly to screen readers, and progress ("4 of 8") is available to them.
61. Wording is friendly and encouraging. Nothing about Review makes the learner feel judged, rushed, or scored.

### Edge cases

- **Very few reviewable items:** a review with only one or two items still works, with a gentle note that more items will appear as the learner finishes more sessions.
- **No items due:** the daily suggestion explains that nothing needs review, and Custom review is still available.
- **All items Learned or Easy:** the daily suggestion can be short or empty, and Custom review can still include learned items.
- **Items missing a needed part:** skipped, counted on Review Home, and linked to Collections.
- **An item removed, archived, or edited mid-review:** skipped gracefully, or shown with its current text.
- **The same item as different exercise types:** shown at most once per review, so the learner never sees the same item twice in one sitting.
- **A phrase and a word with the same text:** treated as separate items, because they come from different collections.
- **A typo fixed in a session:** the item's schedule and ratings follow it, using the same rule as marks.
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
- **Many items to choose from:** thousands of items still produce today's suggestion and a custom selection quickly.
- **A grammar note with several bold parts:** one is used, consistently within a review.
- **A grammar note that is entirely bold, or whose bold part is longer than four words:** skipped.
- **A grammar note with no bold part but with a quoted example sentence:** a key word of that sentence is blanked.
- **A grammar note with neither:** never used, and counted on Review Home with a friendly tip.
- **A grammar note edited after it was rated:** keeps its schedule, and the blank may change if the bold part changed.
- **Bold text inside a list item:** the list item is the sentence that is shown.
- **A very long grammar note:** only the sentence or list item with the blank is shown during the exercise, and the full note is shown, scrollable, when checking.

## Technical

This describes the intended approach at a high level, using the agreed stack (Laravel, Inertia, Vue, Tailwind, shadcn-vue). It is a plan to refine, not a final design.

1. **Build on what exists.** Use the Collections **item identity** and grouping, the **study sessions** rule, the learner's stars and statuses, the shared "Needs attention" rule, the shared text normalization, the reading page, the focus layout, and the existing Review placeholder pages.
2. **Decide what can be reviewed.** Define, in one place, which items can become which exercise: flashcards from words and phrases with a meaning, fix-the-mistake from mistakes with a correction, say-it-again from questions with a natural version, and fill-in-the-blank from vocabulary examples, phrases and their examples, corrections, and grammar notes (through their bold parts or quoted example sentences). Items missing a needed part are skipped. Keep this separate from the screens so every exercise and the daily suggestion use the same definition.
3. **Store review history and schedules.** Add a place to keep, for each learner and item, its **schedule** (when it is next worth revisiting, how many times it was rated, and how many Easy ratings in a row), and a record of **each review** (when, which items, and each rating). Tie schedules to the item's identity so they follow typo fixes, are kept when items disappear, and return with them. Make saving a rating safe to repeat, with one rating counted once.
4. **Create a simple, explainable schedule.** Use a small, readable rule (a few clearly named intervals, growing with repeated Easy ratings and resetting on Hard) kept in one place, so it can be tuned without touching the screens.
5. **Choose today's items.** Build the daily suggestion in one place: due items first, boosted by Hard, Still difficult, starred, and never-reviewed items, leaving out Learned unless due, mixing exercise types, never repeating an item, and filling gaps from other kinds. Use the same selection piece for custom reviews with the learner's choices. Work this out efficiently in bulk, with a bounded number of database queries however many items exist.
6. **Create Review Home.** A Vue page in the main layout with today's suggestion, the Custom review option, items waiting for a fix, and recent results. Reuse the Home screen's card for today's suggestion.
7. **Create the exercise screen.** A page in the focus layout that walks through the selected items one at a time, with the progress bar, Exit, the answer reveal, the rating buttons, undo for the last rating, and keyboard shortcuts. Build each exercise kind as a small piece that plugs into the same screen.
8. **Build the four exercise kinds.** Flashcards, fix-the-mistake, say-it-again, and fill-in-the-blank, each following the rules above, with the blank chosen automatically and safely. For grammar notes, find the bold part (or quoted example) in the note's saved formatting, using the same safe handling of formatted text as elsewhere, and keep the choice of blank in one place so it is predictable and testable.
9. **Create the finish screen and suggestions.** A summary of the review, gentle suggestions to mark items as Learned or Still difficult that the learner confirms, and links back to Collections.
10. **Add custom review.** The choices for topics, collections, starred only, number of items, and learned items, with a live count of matching items, using the same selection piece.
11. **Keep Review in step.** Make sure finishing, editing, archiving, restoring, and deleting sessions, and changing stars and statuses, update what Review suggests straight away, without keeping copies that can go out of date.
12. **Connect to other features.** Expose review history in a stable form for Progress and Export later, keep streaks separate, and leave room for voice recording in say-it-again.
13. **Make it work everywhere.** Test on phone, tablet, and computer, in light and dark mode, with the keyboard and a screen reader, and with large numbers of items.

### UI components: use shadcn-vue wherever possible

All interface pieces in Review should come from **shadcn-vue**, the component library already chosen for the app. Only build a custom piece when shadcn-vue has nothing suitable, and then build it by **composing shadcn-vue parts with Tailwind**. Do not add any other component library.

| Review need                                                         | shadcn-vue component to use                                                   |
| ------------------------------------------------------------------- | ----------------------------------------------------------------------------- |
| Flashcard, and Review Home cards                                    | Card                                                                          |
| Show answer, Check, Hard / Okay / Easy, Start review, Done, Exit    | Button (rating buttons use clear text and distinct styles, never color alone) |
| Progress bar ("4 of 8")                                             | Progress                                                                      |
| Typed answers (fix-the-mistake and fill-in-the-blank)               | Input, and Textarea for longer sentences                                      |
| Labels such as exercise kind, "Needs attention", and results counts | Badge                                                                         |
| Custom review choices: topics                                       | Combobox (Popover with Command and Checkbox)                                  |
| Custom review choices: collections                                  | Toggle Group, or Checkboxes                                                   |
| Custom review choices: starred only and include learned             | Switch, or Toggle                                                             |
| Custom review choices: number of items                              | Select, or Toggle Group                                                       |
| Custom review panel on phones                                       | Sheet                                                                         |
| Undo for the last rating and brief messages                         | Sonner (toast)                                                                |
| Exit confirmation if needed                                         | Alert Dialog                                                                  |
| Failure, empty, and no-results messages                             | Alert, and the existing empty-state piece                                     |
| Loading states                                                      | Skeleton                                                                      |
| Recent results list                                                 | Card, or Table on wide screens with stacked cards on phones                   |
| Dividers and layout details                                         | Separator                                                                     |

Where a shadcn-vue component needs small changes, change its **variants and Tailwind classes** instead of replacing it, so Review stays visually consistent with the rest of the app, in light and dark mode, and keeps the accessibility built into the components.

## Implementation Steps

The steps are ordered so each one leaves a usable, reviewable piece of the feature.

### Step 1: The review screen, flashcards, and ratings

**What it delivers:** A Review Home with a Start review button, the distraction-free exercise screen, **flashcards** for words and phrases, the Hard, Okay, and Easy rating buttons with undo, saving of every rating safely and once, and a finish screen. At this step the items come from a simple selection (reviewable words and phrases, never-reviewed first), and the smart schedule and daily suggestion come in Step 2.

**Tests**

- The Review link in the main navigation opens Review Home, and the placeholder pages are replaced.
- Start review begins a review of reviewable words and phrases from finished, non-archived sessions only. Unfinished and archived sessions never contribute.
- A word or phrase without a meaning (or use) is never shown as a flashcard.
- The exercise screen uses the focus layout, with a progress bar ("4 of 8"), an Exit button, and no timer.
- A flashcard shows the word or phrase first, and Show answer reveals the meaning (or use) and example, with formatting shown safely.
- The rating buttons appear after the answer is revealed, use text labels, and each rating is saved immediately and only once, even after a double tap or retry.
- Undo brings back the previous item's rating choice, and only the final rating counts.
- Exiting at any point keeps the ratings already given and loses nothing.
- The finish screen shows how many items were reviewed and how many were Easy, Okay, and Hard, in encouraging wording, with a Done button.
- A review with one or two items works, and a learner with no reviewable items sees a friendly empty state.
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
- With nothing due, a friendly message appears with Custom review and a link to record a session.
- Review Home shows how many items are waiting for a fix and links to "Needs attention only" in Collections.
- "Due today" follows the learner's local day, including across midnight and daylight saving changes.
- The suggestion changes after a session is finished, edited, archived, restored, or deleted, and after a star or status change.
- With thousands of items, the suggestion appears quickly, and the number of database queries does not grow with the number of items.
- Each rating changes only that item's schedule, whichever exercise it was shown with.
- Schedules follow an item when a typo is fixed in a session, are kept when the item disappears, and return when it comes back.

### Step 3: Fix-the-mistake

**What it delivers:** The fix-the-mistake exercise using mistakes that have a correction, with typed answers, Check, the side-by-side correction and reason, and rating.

**Tests**

- Only mistakes with a correction become exercises, from finished, non-archived sessions. Mistakes without a correction are skipped.
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

**What it delivers:** The say-it-again exercise using past questions and their more natural versions, with Show natural answer and rating.

**Tests**

- Only questions that have both a tutor's question and a more natural version, from finished, non-archived sessions, become exercises.
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

**What it delivers:** The fill-in-the-blank exercise built automatically from vocabulary examples, phrases and their examples, corrections of mistakes, and grammar notes (through their bold parts or quoted examples), with typed answers, Check, and rating, plus the friendly tip on Review Home about grammar notes that cannot be used yet.

**Tests**

- Blanks are built from vocabulary examples (the word is blanked), phrases and their examples (a key word is blanked), and mistake corrections (the changed words are blanked, with the reason as a hint).
- Items with no suitable text are skipped, and sentences are shown exactly as saved apart from the blank.
- A grammar note with a bold part of one to four words in a sentence or list item becomes an exercise: the bold part is blanked, the sentence or list item is shown with the blank, and a small hint names the session's topic.
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

### Step 6: Custom review, recent results, and suggestions

**What it delivers:** Custom review (topics, collections, starred only, number of items, include learned), the recent results list, and the finish screen's gentle suggestions to mark items Learned or Still difficult.

**Tests**

- Custom review lets the learner choose topics, collections (words, phrases, grammar notes, mistakes, past questions), starred only, the number of items, and whether to include Learned items.
- The preview shows how many items match, and a friendly message appears when too few match, with a way to change the choices.
- A custom review mixes exercise types, never repeats an item, and uses the same rating and scheduling rules.
- Only items from finished, non-archived sessions are used, and Learned items are left out unless included.
- The recent results list shows the date, number of items, and Easy, Okay, and Hard counts of the last reviews, and updates after each review.
- After several Easy ratings in a row, the summary may suggest marking an item Learned, and after several Hard ratings, Still difficult. The learner can confirm or ignore each suggestion, and confirming sets the status, while ignoring changes nothing.
- Review never changes a status by itself.
- The number of ratings needed for a suggestion lives in one named setting.
- The finish screen offers to review a little more and to open the Hard items in Collections.
- The custom review panel works on a phone, with the keyboard and a screen reader, in light and dark mode.
- Choices and results stay correct after sessions or marks change.

### Step 7: Staying in step, and polish

**What it delivers:** Review that always follows the learner's sessions and marks, plus a full check of speed, wording, accessibility, and consistency.

**Tests**

- Finishing a session adds its items to future reviews, and an unfinished session never contributes.
- Editing a finished session changes the text shown in later exercises.
- Archiving removes items from future reviews, restoring brings them back with their schedules, and deleting removes them.
- Starring an item or changing its status changes what is suggested.
- Review history and schedules follow typo fixes, are kept when items disappear, and return with them, in every case that Collections' marks handle.
- Counts, estimates, and mixes on Home and Review Home are always correct for what would actually be shown.
- Nothing is stale and only the latest request updates the screen.
- A full journey works: finish a session, see the suggestion appear, start the daily review, work through each kind of exercise, rate items, see the summary, return the next day, see Hard items back sooner and Easy items later, star an item and see it favored, and archive a session and see its items leave.
- Wording is friendly, calm, and consistent, and nothing makes the learner feel judged, rushed, or scored.
- All screens work on a phone, tablet, and computer, with no sideways scrolling, and at 200 percent zoom.
- All text, buttons, and focus outlines have good contrast in light and dark mode, and no meaning relies on color alone.
- Everything is usable with the keyboard alone, and key changes are announced by a screen reader without being chatty.
- Loading, empty, no-results, and error states are friendly and tell the learner what to do next.
- Today's suggestion and a custom selection stay fast with thousands of items, and no query runs once per item where one query for the page would do.
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

## Open Questions

None at the moment.

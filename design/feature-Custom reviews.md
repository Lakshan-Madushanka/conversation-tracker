# Custom reviews

## Summary

Custom reviews let the learner build **their own named practice sets** from the items they choose, and practise them again and again whenever they want. Creating one makes a **draft** immediately, so the learner picks items from Collections (words, phrases, grammar notes, mistakes, and questions) and adds them straight into it, sets how many of each exercise kind they want, names it whenever they like, and attempts it as often as they wish. Every attempt is recorded, so the learner can see their history over time.

Custom reviews are **completely separate from the rest of the app**. Ratings made inside a custom review, its attempts, its statuses, its limits, and the draft being filled affect nothing else. They do not change item marks, schedules, the daily suggestion, recent results, status suggestions, streaks, or Progress.

This is different from the **Quick review** described in the Review feature. Quick review is a one-off chooser that starts straight away and is not saved. Custom reviews are saved, named, and repeatable.

### Assumptions

- The app is for **one learner**. Custom reviews belong to that learner.
- The Practice sessions, Library, Collections, and Review features already exist, and the item identity, the "study sessions" rule, and the exercise kinds from Review are available.
- Items are added from Collections by the learner. Nothing is added automatically.
- A **draft** is a custom review with no items yet. It is a real, saved custom review created the moment the learner starts one, named **"Custom Review N"** by default. It cannot be attempted until it has items, and it needs no separate storage, bar, or page.
- **There is no basket.** A draft is the staging area, and every item reaches a review through a draft or through that review's own page. There is no "save as custom review" step, because the review already exists.
- A custom review stores **references to items** (their identities), not their text. Attempts keep a **snapshot** of the text they were attempted with.
- **Limits are per exercise kind**, set by the learner. Flashcards are split into a **word limit** and a **phrase limit**, so words and phrases are limited separately. Fix-the-mistake, say-it-again, and fill-in-the-blank each have their own limit.
- **A custom review has no maximum size.** The limits control how many items each attempt uses.
- **Custom review attempts never change item ratings, schedules, statuses, or marks**, and never feed the daily suggestion, recent results, status suggestions, streaks, or Progress.
- A custom review's attempt is **self-rated** (Hard, Okay, or Easy), like the rest of Review.
- Archived sessions' items are **not available** while archived. They return in full when the session is restored.
- Deleted items are **removed permanently** from every custom review.
- The learner's local day is used for dates.
- Custom reviews need an internet connection (offline use is an open question in the spec).

### Dependencies

- **Practice sessions:** the source of questions, natural versions, and all entries, and the reading page for opening the original.
- **Library:** the study sessions rule, archiving, restoring, and deleting, all of which must be reflected in custom reviews.
- **Personal collections:** the item identity, the "Needs attention" rule, the learner's stars and statuses (which are shown, never changed, by custom reviews), and the bulk-selection pattern from Library Step 6, which the "Add to review" action reuses.
- **Review and re-practice:** the exercise kinds, the sections and stepper, the exercise shell (ratings, undo, early finish, saving), and the Quick review chooser, which remains separate.
- **Base app layout:** navigation (a link from the Review page), the page header, and the focus layout used for attempts.
- **Export and backup (F7):** custom reviews, including drafts, and their attempts should be included.

### Related notes

- **Relationship to Quick review:** Quick review is a one-off chooser that is not saved. Custom reviews are saved and repeatable. Both use the same exercise screen.
- **Relationship to the Review feature's status suggestions:** custom attempt ratings do not count toward the "runs of ratings" that trigger suggestions to mark an item Learned or Still difficult.
- **Naming:** the sequential default name is "Custom Review N", using the next unused number and given when the draft is created, so deleting a review never causes a duplicate name. The learner can rename at any time.
- **Experience principles that apply:** simple first, calm and encouraging, quick on any device, and distraction-free during attempts.

## Goals

- Let the learner create their own practice sets from any items they choose, across all collections.
- Let the learner decide how much of each kind of exercise a set contains.
- Make it easy to gather items from many places, with one place that already exists to keep the picks safe.
- Let the learner practise a set repeatedly and see their history over time.
- Keep custom practice completely separate, so it never disturbs the daily plan, statuses, marks, or progress.

## Requirements

### Navigation and the listing page

1. The **Review** page has a link to **Custom reviews**, which opens the Custom reviews listing.
2. The listing shows all the learner's custom reviews as cards, with a **New custom review** action that creates a draft immediately, named "Custom Review N" by default, and opens it.
3. The listing can be **searched by name** and **filtered by status** (see Attempt statuses). Search and filter choices are kept in the page address.
4. The listing has friendly empty states: when there are no custom reviews yet, and when a search or filter matches nothing.
5. The learner can open any custom review that has items and attempt it. A review with no items is shown as a **draft** and cannot be attempted yet.

### Starting and filling a draft

6. The **Add to review** action in each Collections list adds the selected items to a **draft**. It works with the existing bulk-selection pattern, and an item can be added from any collection.
7. If the learner has **one draft**, the items go into it without asking. If there are **several**, a small picker asks which one, with the most recently created draft already chosen. If there are **none**, a draft is created and named "Custom Review N", and the items go into it.
8. Adding is always to a draft or to the review the learner is standing in. Nothing joins a review without the learner choosing it.
9. A draft is a saved custom review, so it **survives page changes, refresh, and a different device** without any special handling, and it has **no maximum size**.
10. A draft stays on the listing with a **Draft** label until it has items. Empty drafts are kept until the learner deletes them; the app never deletes one silently or after a delay.
11. Items already in the draft show as added, and adding them again does nothing.
12. The learner can remove items from a draft, or clear it. Clearing asks for confirmation.
13. Adding to a draft never changes an item's marks, statuses, ratings, schedule, or any session.
14. Drafts are never shown in Collections, Review, Progress, or any other place, and they change nothing anywhere else. There is no basket bar and no separate basket page — a draft lives on the listing like any other review.

### Setting up a draft

15. Opening a draft shows its items grouped by collection with counts, and its **name**, **description**, and **limits**. Everything can be set or changed at any time, and changes take effect immediately — there is no save step.
16. The learner can give the review a **name** and an optional **description**. A review created without a name is named **"Custom Review N"**, using the next number not already used by one of the learner's reviews.
17. The learner sets a **limit for each exercise kind**:
    - **Words** (flashcards from words)
    - **Phrases** (flashcards from phrases)
    - **Fix the mistake** (from mistakes)
    - **Say it again** (from questions)
    - **Fill in the blank** (from vocabulary examples, phrase examples, corrections, and grammar notes)
18. Each limit shows how many eligible items of that kind are in the review, for example "Words: 4 of 9 available" and "Phrases: 2 of 5 available".
19. A limit **cannot be higher than the number of eligible items** of that kind. The message says how many are available.
20. A limit **can be set to zero**, which leaves that kind out of the review.
21. At least **one kind** must have a limit above zero before an attempt can be started, and a clear message says so. Until then the page names the kinds that are available to set.
22. If the learner lowers a limit below the number of eligible items of that kind, they are asked which items to drop, and nothing is dropped silently.
23. The screen and the server both validate the limits. A review cannot be left with limits that disagree with its items, and an attempt cannot start against them.
24. Items that are in the review but **not eligible yet** (for example a mistake with no correction, or a question with no natural version) are shown in a separate line with the reason, so the learner understands why they cannot be used.
25. **+ Add items** on a review's page opens the Collections pages **filtered to eligible items**, so the learner only sees items that can be used. Every item there has the same **Add to review** action, and its picks land in this review.
26. Removing an item from the review's page does not affect the item in its collection or in any other review.
27. Items the learner did not add do not appear. An item added to Collections later does **not** join any review by itself.
28. None of this changes any item, and a review has no attempts until it is first attempted.

### Editing a custom review

29. The learner can rename a review, change its description, change its limits, and add or remove items at any time.
30. Changes to items and limits affect **future attempts only**. Past attempts are never rewritten.
31. Adding items uses the same **Add to review** action, the same draft picker, and the same **+ Add items** filtered view.
32. Removing an item does not affect any other review or any collection.
33. A review whose items are all removed or deleted becomes a **draft** again — it shows the Draft label, cannot be attempted, and carries a friendly note. Its past attempts stay exactly as they were.

### Attempting a custom review

34. Starting an attempt builds the attempt's fixed list from the review's items and limits, using the same rules as the daily review for mixing kinds, allocating shares, and not repeating an item. Each item's text is read at that moment.
35. An attempt uses the **same exercise screen, sections, stepper, rating buttons, undo, early finish, and saving** as Review. The stepper shows one step per kind that has items in the attempt.
36. Ratings made in an attempt are stored **on that attempt only**. They do not change the item's schedule, marks, statuses, or any other review.
37. Undo, early finish, and resuming a half-finished attempt work as in Review, but only within the attempt.
38. An attempt is **complete** when every item is rated or skipped. Early finish is allowed once an item is rated, and an unfinished attempt remains in the history as unfinished.
39. Every attempt is numbered. The screen shows the **current attempt number** ("1st attempt", "2nd attempt", "3rd attempt"), counting **every attempt started**, so an unfinished attempt is visible as the latest one.
40. An attempt keeps a **snapshot** of each item's text, meaning, example, and kind as it was attempted, so the history stays readable after items are edited.
41. An item that has been **archived** is shown as **Not available right now** and skipped in new attempts. It returns in full when its session is restored.
42. An item that has been **deleted** is removed from the review permanently. In **past attempts** that included it, the item stays in its snapshot with a **Deleted** indicator, so the learner knows why it is no longer part of the review.
43. An item that is still available but whose text was edited shows its current text in the next attempt, and its snapshot in past attempts keeps the older text.
44. Attempts never change the learner's marks: stars, statuses, and notes stay exactly as they were.
45. A learner can open the **source item** from an attempt (for example "See original session") to see its context, in a new tab, without losing the attempt.

### Attempt history and attempt statuses

46. Each attempt records its **number**, **start and finish dates** (local time), the **counts** of Easy, Okay, and Hard ratings, how many items were **skipped**, and its **status**.
47. Attempt statuses are:
    - **Not started**: created but nothing rated yet. Not counted as an attempt in the history.
    - **In progress**: started and some items rated, not yet complete.
    - **Completed**: every item rated or skipped.
    - **Finished early**: finished with some items unrated, by the learner's choice.
48. Statuses are descriptive only. The app does not use pass or fail wording, and no status counts as a score.
49. The **attempt history** lists every attempt, most recent first, with its number, date, status, and counts. It can be opened to show the items in that attempt, with their snapshot text, their ratings from that attempt, and any **Deleted** indicators.

### The custom review card

50. Each custom review is shown as a **card** on the listing page. The front of the card shows:
    - the **name**
    - the **current attempt number** (the latest attempt, for example "2nd attempt")
    - the **created date**
    - the **status of the previous attempt**, where there is one (for example "Previous attempt: Completed")
    - a short **summary of the attempt history** (for example "3 attempts · last on 12 Oct"), with a **View history** link to the full list
51. A review with no items shows a **Draft** label on its card, with its item count and created date, and a **Delete** action in place of the attempt actions. The label disappears once the draft has items.
52. The card's status is **specific to custom reviews**. It describes the custom review's own attempts only, and never reflects, changes, or is changed by any collection item's status.
53. The card shows the learner's **description** when there is one, shortened if long.
54. Cards can be sorted by **most recently attempted** (the default), by **name**, and by **created date**.

### Deleting a custom review

55. Any custom review, including an empty draft, can be **deleted** from its card or its page. Deleting asks for confirmation first, and the safe choice (Cancel) is the default.
56. The confirmation states the number of attempts that will be deleted with it, and says that this cannot be undone. A draft has none, so its confirmation simply says the draft will be deleted.
57. Deleting a custom review **deletes its attempt history** as well. Nothing else is affected: the items remain in their collections, and no rating, schedule, status, or mark changes.

### Exclusivity

58. Custom reviews are **exclusive**. Nothing done inside a custom review (attempts, ratings, statuses shown, limits, drafts, names, or history) affects any other page or calculation in the app, including Collections, the daily review, Quick review, the schedule, the "waiting for a fix" note, recent results, status suggestions, streaks, practice days, Progress totals, and marks.
59. Custom review ratings never count toward the "runs of ratings" that trigger status suggestions.
60. Custom review attempts never appear in **recent results**. The Review page's recent results list shows only daily and Quick reviews.
61. Custom reviews appear in **exports** with their attempts, but exports do not change anything in the app.

### Staying in step

62. Custom reviews reflect the learner's sessions straight away. When an item's session is finished, edited, archived, restored, or deleted, the review, its counts, and its availability change on the next load, as described above.
63. Eligibility uses the same rules as Review: finished sessions that are not archived, and the same rules for corrections, natural versions, bold parts, and meanings.
64. Drafts and every other custom review are never stale, and only the latest request updates the screen.

### Experience

65. The listing, draft pages, and attempt screens work on a phone, tablet, and computer, in light and dark mode, with no sideways scrolling.
66. Everything can be done with the keyboard, with visible focus, and screen readers announce the draft's item count, the limits and their availability, the attempt number, statuses, and warnings calmly.
67. Wording is friendly and calm. Attempts are described as practice, never as tests or scores.

### Edge cases

- **A draft left open for weeks:** still there, with its items unchanged unless they were deleted.
- **Items in a draft that were archived:** shown as not available right now, and not counted as eligible.
- **A limit higher than the eligible count after an item is archived:** the limit is shown as higher than available, and the learner is asked to adjust before the next attempt. Past attempts are unaffected.
- **A review whose items are all deleted:** it becomes a draft again with a friendly note, and past attempts keep their snapshots with the Deleted indicators.
- **A review with items but no limit above zero:** it cannot be attempted, and the page says which kinds are available to set.
- **Several drafts at once:** Add to review asks which one, with the most recent already chosen; the listing shows each draft's name and item count so they are tellable apart.
- **Drafts left behind:** they stay visible on the listing with their Draft label and are deleted like any other review. Nothing is removed automatically, because silent deletion would be surprising.
- **Two attempts started at once:** each is its own attempt and gets its own number.
- **Renaming to a name already used:** allowed, since the sequential names only apply to reviews created without a name.
- **Deleting the only custom review:** the listing shows its empty state.
- **A session deleted that contained items in a review:** those items are removed from the review permanently, and the Deleted indicator appears in past attempts only.
- **Very large reviews:** the attempt is still built quickly, and the list is shown in pages.
- **Unfinished attempt left open:** remains In progress, and resumes where the learner left it.

## Technical

This describes the intended approach at a high level, using the agreed stack (Laravel, Inertia, Vue, Tailwind, shadcn-vue). It is a plan to refine, not a final design.

1. **Build on what exists.** Use the item identity, the study sessions rule, the eligibility rules from Review, the exercise sections and stepper, the exercise shell, and the bulk-selection pattern from Collections.
2. **Keep custom reviews in their own space.** Store custom reviews, their items, their limits, their attempts, and each attempt's ratings separately from the daily review's ratings, schedules, and history, so no code path can mix them up. Mark attempt ratings so the schedule, suggestions, recent results, and Progress never read them.
3. **Stage in the review itself.** A draft is a custom review record with no items yet, so the existing review storage is the staging area. There is no basket table, no basket bar, and no save-as-review conversion. A review becomes attemptable as soon as it has items and a limit above zero.
4. **Store references and snapshots.** A review keeps item identities and limits. Each attempt keeps a snapshot of each item's text and kind at the time of the attempt, so history stays readable after edits or deletions.
5. **Build the attempt list.** Reuse the Review piece that chooses items, extended with per-kind limits and eligibility, to build the fixed list of an attempt, and check that a limit never exceeds the eligible count.
6. **Build the draft flow.** New custom review creating a named draft, the Add to review action with the single-draft shortcut and the picker over several drafts, the draft's page with grouped items, the limits with live availability, validation on screen and on the server, the "not eligible yet" line, and the **+ Add items** view filtered to eligible collection items.
7. **Build the listing and the card.** Search, filters, sorting, the card's front, the Draft label with its Delete action, and the attempt history page.
8. **Build attempts.** Reuse the exercise screen with its stepper, ratings, undo, early finish, and resume, scoped to the attempt. Record attempt numbers, statuses, and counts.
9. **Handle availability.** Archived items are marked not available and skipped; deleted items are removed from reviews, with the Deleted indicator shown only in past attempts' snapshots. A review emptied this way returns to being a draft.
10. **Handle deletion of a review.** Confirm, then delete the review and its attempts, touching nothing else. Empty drafts use the same path.
11. **Keep everything exclusive.** Add tests that prove custom attempts, ratings, statuses shown, limits, and drafts change nothing outside custom reviews.
12. **Connect exports.** Include custom reviews, drafts, and attempts in the exports planned in F7, without changing anything in the app.
13. **Make it work everywhere.** Test on phone, tablet, and computer, in light and dark mode, with the keyboard and a screen reader, and with large numbers of items.

### UI components: use shadcn-vue wherever possible

All interface pieces should come from **shadcn-vue**. Only build a custom piece when shadcn-vue has nothing suitable, and then compose it from shadcn-vue parts with Tailwind. Do not add any other component library.

| Custom reviews need                              | shadcn-vue component to use                            |
| ------------------------------------------------ | ------------------------------------------------------ |
| Custom review cards                              | Card                                                   |
| Search by name and status filter                 | Input, Select, Toggle Group                            |
| Draft label on a card                            | Badge, with text                                       |
| "Which draft?" picker over several drafts        | Dialog, with Select or Command, the most recent chosen |
| Draft page and grouped items                     | Card, Checkbox, Separator                              |
| + Add items, filtered to eligible items          | The existing Collections lists, with filter controls   |
| Limits per kind                                  | Input (number), Label, with Badge showing availability |
| Name and description                             | Input, Textarea, Label                                 |
| Not eligible yet line                            | Alert, or Card section                                 |
| Attempt stepper                                  | The existing Stepper, linear option off                |
| Attempt history                                  | Table on wide screens, stacked cards on phones         |
| Deleted indicator and Not available right now    | Badge, with text                                       |
| Delete and clear confirmations                   | Alert Dialog                                           |
| Add to review, Start attempt, Done, Finish early | Button                                                 |
| Messages                                         | Sonner (toast) and Alert                               |
| Loading and empty states                         | Skeleton, and the existing empty-state piece           |

## Implementation Steps

The steps are ordered so each one leaves a usable, reviewable piece of the feature.

### Step 1: Draft reviews and "Add to review"

**What it delivers:** New custom review creating a named draft at once, the Add to review action in Collections routing items into a draft (asking only when there is a choice), and the draft's item list with remove and clear — all stored as ordinary custom reviews, with no basket bar, basket page, or basket storage anywhere.

**Tests**

- New custom review creates a draft named "Custom Review N" with the next unused number, and it appears on the listing with a Draft label.
- Add to review adds selected items from every collection into the only draft without asking.
- With several drafts, a picker asks which one and has the most recently created one already chosen. With none, a draft is created and named.
- Items already in the draft show as added, and adding them again does nothing.
- A draft survives page changes, refresh, and a different browser session for the same learner, has no maximum size, and adding many items stays fast.
- Removing items from a draft and clearing it work, and clearing asks for confirmation.
- Add to review changes no item's marks, statuses, ratings, schedule, or sessions.
- No basket bar, basket page, or basket data appears anywhere in the app.

### Step 2: Setting up a draft

**What it delivers:** The draft's page with name, description, and per-kind limits settable at any time, live availability, validation, the not-eligible line, and the **+ Add items** view filtered to eligible collection items.

**Tests**

- The draft's page shows its items grouped by collection with counts, and its name, description, and limits, all editable with no save step.
- A review created without a name is named "Custom Review N", using the next unused number, and renaming never causes a conflict.
- Each limit shows the eligible count of its kind, for example "Words: 4 of 9 available".
- Words and phrases have separate limits, and both are checked against their own counts.
- A limit cannot exceed the eligible count, a zero limit leaves the kind out, and at least one kind must be above zero before an attempt can start.
- Lowering a limit below the eligible count of that kind asks which items to drop, and nothing is dropped silently.
- Screen and server validation agree, and a disagreeing write is refused with a clear message.
- Items not eligible yet appear in their own line with the reason.
- **+ Add items** opens the Collections pages filtered to eligible items, and each item there adds itself to this review.
- Removing an item from the review leaves it in its collection and in every other review.
- Items added to Collections later do not join a review by themselves, and nothing here changes any item.

### Step 3: Listing, search, filters, and cards

**What it delivers:** The Custom reviews listing with search by name, status filter, sorting, the card with its front content, the draft card with its label and Delete action, and the new custom review action.

**Tests**

- The Review page links to the listing, and the listing shows all custom reviews, drafts included, as cards.
- Search by name and the status filter work together and are kept in the page address.
- The card shows name, current attempt number, created date, previous attempt status, and the attempt history summary with a View history link.
- A card for a review with no items shows the Draft label and a Delete action instead of attempt actions, and the label goes once it has items.
- The card's status describes custom attempts only, and never reflects a collection item's status.
- Sorting by most recently attempted, name, and created date works.
- Empty and no-match states are friendly.

### Step 4: Attempts and attempt history

**What it delivers:** Starting an attempt from a review, the exercise screen scoped to the attempt, attempt numbering, attempt statuses, counts, snapshots, and the attempt history page.

**Tests**

- Starting an attempt builds a fixed list from the review's items and limits, using the daily rules for mixing, shares, and no repeats.
- A review with no items cannot be started and says so, and a review with no limit above zero cannot be started and names the kinds available to set.
- Each item's text is read at the start of the attempt, and later edits appear only in later attempts.
- The stepper shows one step per kind present in the attempt, and the screen is the same as Review's.
- Ratings are stored on the attempt only, and the item's schedule, marks, status, and other reviews are unchanged.
- Undo, early finish, and resuming work within the attempt only.
- Attempt numbers count every attempt started, so an unfinished attempt is visible as the latest.
- Statuses are Not started, In progress, Completed, and Finished early, with descriptive wording and no pass or fail language.
- Each attempt records its start and finish dates in local time, counts of Easy, Okay, and Hard, skipped items, and its status.
- The attempt history lists attempts most recent first, and opening one shows its items with snapshot text and ratings.
- "See original session" opens in a new tab and keeps the attempt.

### Step 5: Availability, deletion of items, and deletion of reviews

**What it delivers:** Archived items marked not available and skipped, deleted items removed from reviews with a Deleted indicator in past attempts, reviews emptied returning to being drafts, and the permanent deletion of reviews with their attempts.

**Tests**

- An archived item shows as Not available right now and is skipped in new attempts, and it returns in full when its session is restored.
- A deleted item is removed from every review permanently, and its snapshot in past attempts keeps the text with a Deleted indicator.
- A limit higher than the eligible count after an item is archived is shown, and the learner is asked to adjust before the next attempt.
- A review whose items are all deleted, or all removed by the learner, becomes a draft with a note, and past attempts keep their snapshots.
- Deleting a review asks for confirmation, states how many attempts will be deleted, and then removes the review and all its attempts.
- Deleting an empty draft confirms and removes it with nothing else affected.
- Deleting a review changes nothing else: items, ratings, schedules, statuses, and marks are untouched.

### Step 6: Exclusivity, staying in step, and polish

**What it delivers:** Proof that custom reviews are exclusive, a check that nothing else changes, and a full pass over speed, wording, accessibility, and consistency.

**Tests**

- Custom attempt ratings never count toward the daily schedule, status suggestions, recent results, streaks, practice days, Progress totals, marks, or the "waiting for a fix" note.
- Custom reviews never appear in recent results, and the daily review's suggestion is unchanged by any custom activity.
- Editing a session, archiving, restoring, and deleting a session each update custom reviews on the next load, and nothing else changes.
- Drafts and other custom reviews are never stale, and only the latest request updates the screen.
- Exports include custom reviews, drafts, and attempts without changing anything in the app.
- A full journey works: create a draft, gather items from several collections, set the name and limits, attempt it, undo one rating, finish early, see the attempt in history, archive a session and see its items become not available, delete an item and see the Deleted indicator in past attempts, and delete the review with its attempts.
- Every screen works on phones, tablets, and computers, in light and dark mode, with the keyboard and a screen reader, and with no sideways scrolling.
- With about 5,000 items and many reviews, the listing, draft pages, and attempts stay fast, with query counts that do not grow with the number of items.

## Open Questions

None at the moment.

## Decisions Made

- **Custom reviews are saved, named, and repeatable.** Quick review remains a one-off chooser in the Review feature.
- **Custom reviews are exclusive.** Nothing done inside them affects any other page or calculation.
- **Custom attempts do not change item marks, ratings, schedules, or statuses**, and never feed the daily suggestion, recent results, status suggestions, streaks, or Progress.
- **A draft custom review is the staging area.** Creating a review makes it immediately, named "Custom Review N", and Add to review fills it. The basket, its storage, its bar, its page, and the "save as custom review" step are gone.
- **A draft is a custom review with no items**, and a review emptied of items becomes a draft again. Being a saved review is what makes a draft survive refresh and page changes, with no special handling and no size limit.
- **One draft is used without asking; several open a small picker defaulting to the most recent.** The default keeps adding quick.
- **Empty drafts stay visible on the listing with a Draft label and are deleted like any other review.** Nothing is deleted automatically, because silent deletion would be surprising.
- **Items reach a review only through the learner's choice** — Add to review in a collection, or + Add items from the review's own page, filtered to eligible items.
- **Limits are set per exercise kind**, with flashcards split into a word limit and a phrase limit. Each limit is validated against its own eligible count, and a limit can be zero. At least one kind must be above zero before an attempt starts.
- **A custom review has no maximum size.**
- **Default names are sequential**, using the next unused number, given when the draft is created.
- **Deleted items are removed permanently** from reviews. Past attempts keep a snapshot of the text with a Deleted indicator.
- **Archived items are not available** while archived, and return in full on restore.
- **Deleting a review deletes its attempts** as well, after confirmation.
- **Attempt statuses are Not started, In progress, Completed, and Finished early**, described without pass or fail wording.
- **Custom review attempts never appear in recent results.**

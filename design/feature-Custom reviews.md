# Custom reviews

## Summary

Custom reviews let the learner build **their own named practice sets** from the items they choose, and practise them again and again whenever they want. The learner picks items from Collections (words, phrases, grammar notes, mistakes, and questions), sets how many of each exercise kind they want, saves the set with a name, and attempts it as often as they like. Every attempt is recorded, so the learner can see their history over time.

Custom reviews are **completely separate from the rest of the app**. Ratings made inside a custom review, its attempts, its statuses, its limits, and the basket of items being gathered affect nothing else. They do not change item marks, schedules, the daily suggestion, recent results, status suggestions, streaks, or Progress.

This is different from the **Quick review** described in the Review feature. Quick review is a one-off chooser that starts straight away and is not saved. Custom reviews are saved, named, and repeatable.

### Assumptions

- The app is for **one learner**. Custom reviews belong to that learner.
- The Practice sessions, Library, Collections, and Review features already exist, and the item identity, the "study sessions" rule, and the exercise kinds from Review are available.
- Items are added from Collections by the learner. Nothing is added automatically.
- A **basket** is the temporary list of items being gathered before a custom review is saved. It is not a review, has no name, and has no attempts. It survives page changes and refresh, has no maximum size, and is discarded or converted when the learner saves a review.
- A saved custom review stores **references to items** (their identities), not their text. Attempts keep a **snapshot** of the text they were attempted with.
- **Limits are per exercise kind**, set by the learner. Flashcards are split into a **word limit** and a **phrase limit**, so words and phrases are limited separately. Fix-the-mistake, say-it-again, and fill-in-the-blank each have their own limit.
- A **saved custom review has no maximum size.** The limits control how many items each attempt uses.
- **Custom review attempts never change item ratings, schedules, statuses, or marks**, and never feed the daily suggestion, recent results, status suggestions, streaks, or Progress.
- A custom review's attempt is **self-rated** (Hard, Okay, or Easy), like the rest of Review.
- Archived sessions' items are **not available** while archived. They return in full when the session is restored.
- Deleted items are **removed permanently** from every saved custom review.
- The learner's local day is used for dates.
- Custom reviews need an internet connection (offline use is an open question in the spec).

### Dependencies

- **Practice sessions:** the source of questions, natural versions, and all entries, and the reading page for opening the original.
- **Library:** the study sessions rule, archiving, restoring, and deleting, all of which must be reflected in custom reviews.
- **Personal collections:** the item identity, the "Needs attention" rule, the learner's stars and statuses (which are shown, never changed, by custom reviews), and the bulk-selection pattern from Library Step 6, which the "Add to review" action reuses.
- **Review and re-practice:** the exercise kinds, the sections and stepper, the exercise shell (ratings, undo, early finish, saving), and the Quick review chooser, which remains separate.
- **Base app layout:** navigation (a link from the Review page), the page header, and the focus layout used for attempts.
- **Export and backup (F7):** saved custom reviews and their attempts should be included.

### Related notes

- **Relationship to Quick review:** Quick review is a one-off chooser that is not saved. Custom reviews are saved and repeatable. Both use the same exercise screen.
- **Relationship to the Review feature's status suggestions:** custom attempt ratings do not count toward the "runs of ratings" that trigger suggestions to mark an item Learned or Still difficult.
- **Naming:** the sequential default name is "Custom Review N", using the next unused number, so deleting a review never causes a duplicate name. The learner can rename at any time.
- **Experience principles that apply:** simple first, calm and encouraging, quick on any device, and distraction-free during attempts.

## Goals

- Let the learner create their own practice sets from any items they choose, across all collections.
- Let the learner decide how much of each kind of exercise a set contains.
- Make it easy to gather items from many places, and keep the picks safe while doing so.
- Let the learner practise a set repeatedly and see their history over time.
- Keep custom practice completely separate, so it never disturbs the daily plan, statuses, marks, or progress.

## Requirements

### Navigation and the listing page

1. The **Review** page has a link to **Custom reviews**, which opens the Custom reviews listing.
2. The listing shows all the learner's saved custom reviews as cards, with a **New custom review** action.
3. The listing can be **searched by name** and **filtered by status** (see Attempt statuses). Search and filter choices are kept in the page address.
4. The listing has friendly empty states: when there are no custom reviews yet, and when a search or filter matches nothing.
5. The learner can open any saved custom review and attempt it.

### The basket

6. The **Add to review** action in each Collections list adds the selected items to the **basket**. It works with the existing bulk-selection pattern, and an item can be added from any collection.
7. While the basket has items, a **basket bar** (for example "Building Custom Review · 5 items") stays visible on Collections, Review, and the custom review pages. It shows the number of items and opens the basket.
8. The basket **survives page changes and refresh**, and is kept for the learner's account, not only the browser.
9. The basket has **no maximum size**.
10. The basket is never shown in Collections, Review, Progress, or any other place, and it changes nothing anywhere else.
11. Items already in the basket show as added, and adding them again does nothing.
12. The learner can remove items from the basket, or clear it. Clearing asks for confirmation.
13. Adding to the basket never changes an item's marks, statuses, ratings, schedule, or any session.

### Building a custom review

14. Opening the basket shows the items grouped by collection, with counts, and a **Save as custom review** action.
15. When saving, the learner can give the review a **name** and an optional **description**. If no name is given, the review is named **"Custom Review N"**, using the next number not already used by one of the learner's reviews.
16. The learner sets a **limit for each exercise kind**:
    - **Words** (flashcards from words)
    - **Phrases** (flashcards from phrases)
    - **Fix the mistake** (from mistakes)
    - **Say it again** (from questions)
    - **Fill in the blank** (from vocabulary examples, phrase examples, corrections, and grammar notes)
17. Each limit shows how many eligible items of that kind are in the basket, for example "Words: 4 of 9 available" and "Phrases: 2 of 5 available".
18. A limit **cannot be higher than the number of eligible items** of that kind. The message says how many are available.
19. A limit **can be set to zero**, which leaves that kind out of the review.
20. At least **one kind** must have a limit above zero before the review can be saved, and a clear message says so.
21. If the learner lowers a limit below the number already selected for a kind, they are asked which items to drop, and nothing is dropped silently.
22. The screen and the server both validate the limits. A review cannot be saved with limits that disagree with the items.
23. Items that are in the basket but **not eligible yet** (for example a mistake with no correction, or a question with no natural version) are shown in a separate line with the reason, so the learner understands why they cannot be used.
24. Saving stores the selected item identities and the limits, and the basket is emptied. Items added to Collections later do **not** join the saved review.
25. Saving a review never changes any item, and the saved review has no attempts until it is first attempted.
26. A saved review with **no items** is kept as a **draft** that cannot be attempted yet, with a friendly note.

### Editing a saved custom review

27. The learner can rename a saved review, change its description, change its limits, and add or remove items at any time.
28. Changes to items and limits affect **future attempts only**. Past attempts are never rewritten.
29. Adding items to a saved review goes through the same basket as creating one.
30. Removing an item from a saved review does not affect any other review or any collection.

### Attempting a custom review

31. Starting an attempt builds the attempt's fixed list from the saved review's items and limits, using the same rules as the daily review for mixing kinds, allocating shares, and not repeating an item. Each item's text is read at that moment.
32. An attempt uses the **same exercise screen, sections, stepper, rating buttons, undo, early finish, and saving** as Review. The stepper shows one step per kind that has items in the attempt.
33. Ratings made in an attempt are stored **on that attempt only**. They do not change the item's schedule, marks, statuses, or any other review.
34. Undo, early finish, and resuming a half-finished attempt work as in Review, but only within the attempt.
35. An attempt is **complete** when every item is rated or skipped. Early finish is allowed once an item is rated, and an unfinished attempt remains in the history as unfinished.
36. Every attempt is numbered. The screen shows the **current attempt number** ("1st attempt", "2nd attempt", "3rd attempt"), counting **every attempt started**, so an unfinished attempt is visible as the latest one.
37. An attempt keeps a **snapshot** of each item's text, meaning, example, and kind as it was attempted, so the history stays readable after items are edited.
38. An item that has been **archived** is shown as **Not available right now** and skipped in new attempts. It returns in full when its session is restored.
39. An item that has been **deleted** is removed from the saved review permanently. In **past attempts** that included it, the item stays in its snapshot with a **Deleted** indicator, so the learner knows why it is no longer part of the review.
40. An item that is still available but whose text was edited shows its current text in the next attempt, and its snapshot in past attempts keeps the older text.
41. Attempts never change the learner's marks: stars, statuses, and notes stay exactly as they were.
42. A learner can open the **source item** from an attempt (for example "See original session") to see its context, in a new tab, without losing the attempt.

### Attempt history and attempt statuses

43. Each attempt records its **number**, **start and finish dates** (local time), the **counts** of Easy, Okay, and Hard ratings, how many items were **skipped**, and its **status**.
44. Attempt statuses are:
    - **Not started**: created but nothing rated yet. Not counted as an attempt in the history.
    - **In progress**: started and some items rated, not yet complete.
    - **Completed**: every item rated or skipped.
    - **Finished early**: finished with some items unrated, by the learner's choice.
45. Statuses are descriptive only. The app does not use pass or fail wording, and no status counts as a score.
46. The **attempt history** lists every attempt, most recent first, with its number, date, status, and counts. It can be opened to show the items in that attempt, with their snapshot text, their ratings from that attempt, and any **Deleted** indicators.

### The custom review card

47. Each saved custom review is shown as a **card** on the listing page. The front of the card shows:
    - the **name**
    - the **current attempt number** (the latest attempt, for example "2nd attempt")
    - the **created date**
    - the **status of the previous attempt**, where there is one (for example "Previous attempt: Completed")
    - a short **summary of the attempt history** (for example "3 attempts · last on 12 Oct"), with a **View history** link to the full list
48. The card's status is **specific to custom reviews**. It describes the custom review's own attempts only, and never reflects, changes, or is changed by any collection item's status.
49. The card shows the learner's **description** when there is one, shortened if long.
50. Cards can be sorted by **most recently attempted** (the default), by **name**, and by **created date**.

### Deleting a saved custom review

51. A saved custom review can be **deleted** from its card or its page. Deleting asks for confirmation first, and the safe choice (Cancel) is the default.
52. The confirmation states the number of attempts that will be deleted with it, and says that this cannot be undone.
53. Deleting a custom review **deletes its attempt history** as well. Nothing else is affected: the items remain in their collections, and no rating, schedule, status, or mark changes.

### Exclusivity

54. Custom reviews are **exclusive**. Nothing done inside a custom review (attempts, ratings, statuses shown, limits, the basket, names, or history) affects any other page or calculation in the app, including Collections, the daily review, Quick review, the schedule, the "waiting for a fix" note, recent results, status suggestions, streaks, practice days, Progress totals, and marks.
55. Custom review ratings never count toward the "runs of ratings" that trigger status suggestions.
56. Custom review attempts never appear in **recent results**. The Review page's recent results list shows only daily and Quick reviews.
57. Custom reviews appear in **exports** with their attempts, but exports do not change anything in the app.

### Staying in step

58. Saved reviews reflect the learner's sessions straight away. When an item's session is finished, edited, archived, restored, or deleted, the review, its counts, and its availability change on the next load, as described above.
59. Eligibility uses the same rules as Review: finished sessions that are not archived, and the same rules for corrections, natural versions, bold parts, and meanings.
60. The basket and the saved reviews are never stale, and only the latest request updates the screen.

### Experience

61. The listing, basket, builder, and attempt screens work on a phone, tablet, and computer, in light and dark mode, with no sideways scrolling.
62. Everything can be done with the keyboard, with visible focus, and screen readers announce the basket count, the limits and their availability, the attempt number, statuses, and warnings calmly.
63. Wording is friendly and calm. Attempts are described as practice, never as tests or scores.

### Edge cases

- **A basket left open for weeks:** still there, with items unchanged unless they were deleted.
- **Items in the basket that were archived:** shown as not available right now, and not counted as eligible.
- **A limit higher than the eligible count after an item is archived:** the limit is shown as higher than available, and the learner is asked to adjust before the next attempt. Past attempts are unaffected.
- **A saved review whose items are all deleted:** it becomes a draft with a friendly note, and past attempts keep their snapshots with the Deleted indicators.
- **Two attempts started at once:** each is its own attempt and gets its own number.
- **Renaming to a name already used:** allowed, since the sequential names only apply to new reviews without a name.
- **Deleting the only custom review:** the listing shows its empty state.
- **A session deleted that contained items in a saved review:** those items are removed from the review permanently, and the Deleted indicator appears in past attempts only.
- **Very large reviews:** the attempt is still built quickly, and the list is shown in pages.
- **Unfinished attempt left open:** remains In progress, and resumes where the learner left it.

## Technical

This describes the intended approach at a high level, using the agreed stack (Laravel, Inertia, Vue, Tailwind, shadcn-vue). It is a plan to refine, not a final design.

1. **Build on what exists.** Use the item identity, the study sessions rule, the eligibility rules from Review, the exercise sections and stepper, the exercise shell, and the bulk-selection pattern from Collections.
2. **Keep custom reviews in their own space.** Store custom reviews, their items, their limits, their attempts, and each attempt's ratings separately from the daily review's ratings, schedules, and history, so no code path can mix them up. Mark attempt ratings so the schedule, suggestions, recent results, and Progress never read them.
3. **Store the basket for the learner.** Keep the basket on the server with the learner's account, so it survives refresh and devices, with no size cap. Keep it separate from any review.
4. **Store references and snapshots.** A saved review keeps item identities and limits. Each attempt keeps a snapshot of each item's text and kind at the time of the attempt, so history stays readable after edits or deletions.
5. **Build the attempt list.** Reuse the Review piece that chooses items, extended with per-kind limits and eligibility, to build the fixed list of an attempt, and check that a limit never exceeds the eligible count.
6. **Build the basket and the builder.** The basket bar, the basket page, the grouped list, the limits with live availability, validation on screen and on the server, the "not eligible yet" line, and naming.
7. **Build the listing and the card.** Search, filters, sorting, the card's front, and the attempt history page.
8. **Build attempts.** Reuse the exercise screen with its stepper, ratings, undo, early finish, and resume, scoped to the attempt. Record attempt numbers, statuses, and counts.
9. **Handle availability.** Archived items are marked not available and skipped; deleted items are removed from saved reviews, with the Deleted indicator shown only in past attempts' snapshots.
10. **Handle deletion of a saved review.** Confirm, then delete the review and its attempts, touching nothing else.
11. **Keep everything exclusive.** Add tests that prove custom attempts, ratings, statuses shown, limits, and the basket change nothing outside custom reviews.
12. **Connect exports.** Include saved reviews and attempts in the exports planned in F7, without changing anything in the app.
13. **Make it work everywhere.** Test on phone, tablet, and computer, in light and dark mode, with the keyboard and a screen reader, and with large numbers of items.

### UI components: use shadcn-vue wherever possible

All interface pieces should come from **shadcn-vue**. Only build a custom piece when shadcn-vue has nothing suitable, and then compose it from shadcn-vue parts with Tailwind. Do not add any other component library.

| Custom reviews need                           | shadcn-vue component to use                            |
| --------------------------------------------- | ------------------------------------------------------ |
| Custom review cards                           | Card                                                   |
| Search by name and status filter              | Input, Select, Toggle Group                            |
| Basket bar                                    | Card or Sheet, with Badge for the count                |
| Basket page and grouped items                 | Card, Checkbox, Separator                              |
| Limits per kind                               | Input (number), Label, with Badge showing availability |
| Name and description                          | Input, Textarea, Label                                 |
| Not eligible yet line                         | Alert, or Card section                                 |
| Attempt stepper                               | The existing Stepper, linear option off                |
| Attempt history                               | Table on wide screens, stacked cards on phones         |
| Deleted indicator and Not available right now | Badge, with text                                       |
| Delete and clear confirmations                | Alert Dialog                                           |
| Save, Start attempt, Done, Finish early       | Button                                                 |
| Messages                                      | Sonner (toast) and Alert                               |
| Loading and empty states                      | Skeleton, and the existing empty-state piece           |

## Implementation Steps

The steps are ordered so each one leaves a usable, reviewable piece of the feature.

### Step 1: The basket and "Add to review"

**What it delivers:** The Add to review action in Collections, the basket bar, and the basket page, with the basket kept on the server, surviving refresh, with no size cap, and changing nothing else.

**Tests**

- Add to review adds selected items from every collection, and items already in the basket show as added.
- The basket bar appears on Collections, Review, and custom review pages with the correct count, and opens the basket.
- The basket survives page changes, refresh, and a different browser session for the same learner.
- The basket has no maximum size, and adding many items stays fast.
- Removing items and clearing the basket work, and clearing asks for confirmation.
- Adding to the basket changes no item's marks, statuses, ratings, schedule, or sessions.
- The basket appears nowhere else in the app.

### Step 2: Building and saving a custom review

**What it delivers:** Saving the basket as a named custom review, with descriptions, sequential default names, per-kind limits with live availability, validation, the not-eligible line, and draft reviews.

**Tests**

- A review saved without a name is named "Custom Review N", using the next unused number, and renaming never causes a conflict.
- Each limit shows the eligible count of its kind, for example "Words: 4 of 9 available".
- Words and phrases have separate limits, and both are checked against their own counts.
- A limit cannot exceed the eligible count, a zero limit leaves the kind out, and at least one kind must be above zero to save.
- Lowering a limit below the selected count asks which items to drop, and nothing is dropped silently.
- Screen and server validation agree, and a disagreeing save is refused with a clear message.
- Items not eligible yet appear in their own line with the reason.
- Saving stores identities and limits, empties the basket, and changes no item.
- Items added to Collections later do not join the saved review.
- A review saved with no items is a draft that cannot be attempted, with a friendly note.

### Step 3: Listing, search, filters, and cards

**What it delivers:** The Custom reviews listing with search by name, status filter, sorting, the card with its front content, and the new custom review action.

**Tests**

- The Review page links to the listing, and the listing shows all saved reviews as cards.
- Search by name and the status filter work together and are kept in the page address.
- The card shows name, current attempt number, created date, previous attempt status, and the attempt history summary with a View history link.
- The card's status describes custom attempts only, and never reflects a collection item's status.
- Sorting by most recently attempted, name, and created date works.
- Empty and no-match states are friendly.

### Step 4: Attempts and attempt history

**What it delivers:** Starting an attempt from a saved review, the exercise screen scoped to the attempt, attempt numbering, attempt statuses, counts, snapshots, and the attempt history page.

**Tests**

- Starting an attempt builds a fixed list from the review's items and limits, using the daily rules for mixing, shares, and no repeats.
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

**What it delivers:** Archived items marked not available and skipped, deleted items removed from saved reviews with a Deleted indicator in past attempts, and the permanent deletion of saved reviews with their attempts.

**Tests**

- An archived item shows as Not available right now and is skipped in new attempts, and it returns in full when its session is restored.
- A deleted item is removed from every saved review permanently, and its snapshot in past attempts keeps the text with a Deleted indicator.
- A limit higher than the eligible count after an item is archived is shown, and the learner is asked to adjust before the next attempt.
- A review whose items are all deleted becomes a draft with a note, and past attempts keep their snapshots.
- Deleting a saved review asks for confirmation, states how many attempts will be deleted, and then removes the review and all its attempts.
- Deleting a review changes nothing else: items, ratings, schedules, statuses, and marks are untouched.

### Step 6: Exclusivity, staying in step, and polish

**What it delivers:** Proof that custom reviews are exclusive, a check that nothing else changes, and a full pass over speed, wording, accessibility, and consistency.

**Tests**

- Custom attempt ratings never count toward the daily schedule, status suggestions, recent results, streaks, practice days, Progress totals, marks, or the "waiting for a fix" note.
- Custom reviews never appear in recent results, and the daily review's suggestion is unchanged by any custom activity.
- Editing a session, archiving, restoring, and deleting a session each update custom reviews on the next load, and nothing else changes.
- The basket and saved reviews are never stale, and only the latest request updates the screen.
- Exports include saved reviews and attempts without changing anything in the app.
- A full journey works: gather items from several collections, save a review with limits, attempt it, undo one rating, finish early, see the attempt in history, archive a session and see its items become not available, delete an item and see the Deleted indicator in past attempts, and delete the review with its attempts.
- Every screen works on phones, tablets, and computers, in light and dark mode, with the keyboard and a screen reader, and with no sideways scrolling.
- With about 5,000 items and many reviews, the basket, builder, listing, and attempts stay fast, with query counts that do not grow with the number of items.

## Open Questions

None at the moment.

## Decisions Made

- **Custom reviews are saved, named, and repeatable.** Quick review remains a one-off chooser in the Review feature.
- **Custom reviews are exclusive.** Nothing done inside them affects any other page or calculation.
- **Custom attempts do not change item marks, ratings, schedules, or statuses**, and never feed the daily suggestion, recent results, status suggestions, streaks, or Progress.
- **The basket survives page changes and refresh**, is kept for the learner's account, and has no maximum size. It is not a review and changes nothing.
- **Limits are set per exercise kind**, with flashcards split into a word limit and a phrase limit. Each limit is validated against its own eligible count, and a limit can be zero.
- **A saved review has no maximum size.**
- **Default names are sequential**, using the next unused number.
- **Deleted items are removed permanently** from saved reviews. Past attempts keep a snapshot of the text with a Deleted indicator.
- **Archived items are not available** while archived, and return in full on restore.
- **Deleting a saved review deletes its attempts** as well, after confirmation.
- **Attempt statuses are Not started, In progress, Completed, and Finished early**, described without pass or fail wording.
- **Custom review attempts never appear in recent results.**

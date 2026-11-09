# Custom reviews

## Summary

Custom reviews let the learner build **their own named practice sets** from the items they choose, and practise them again and again whenever they want. Creating one saves it immediately, empty and ready for items, so the learner picks items from Collections (words, phrases, grammar notes, mistakes, and questions) and adds them straight into it, names it whenever they like, marks it **Finished** when it is ready to practise, and attempts it as often as they wish. Every attempt is recorded, so the learner can see their history over time.

Custom reviews are **completely separate from the rest of the app**. Ratings made inside a custom review, its attempts, its statuses, and the items being gathered affect nothing else. They do not change item marks, schedules, the daily suggestion, recent results, status suggestions, streaks, or Progress.

This is different from the **Quick review** described in the Review feature. Quick review is a one-off chooser that starts straight away and is not saved. Custom reviews are saved, named, and repeatable.

### Assumptions

- The app is for **one learner**. Custom reviews belong to that learner.
- The Practice sessions, Library, Collections, and Review features already exist, and the item identity, the "study sessions" rule, and the exercise kinds from Review are available.
- Items are added from Collections by the learner. Nothing is added automatically.
- A review the learner is still building is a real, saved custom review, created the moment they start one and named **"Custom Review N"** by default. It needs no separate storage, bar, or page.
- **There is no basket.** The reviews themselves are the staging area, and every item reaches one through **Add to review** in a collection or through that review's own page. There is no "save as custom review" step, because the review already exists.
- A custom review stores **references to items** (their identities), not their text. Attempts keep a **snapshot** of the text they were attempted with.
- Every review carries exactly one of three statuses — **Empty**, **Draft**, or **Finished** — and only **Finished** can be practised. **Empty** follows the item count automatically, **Draft** is what a review becomes automatically when its first item arrives, and **Finished** is chosen only by the learner.
- **There are no per-kind exercise limits.** An attempt uses every eligible item in the review; the review's size is bounded only by the maximum below.
- **The recommended size is 20 items.** Past it the app warns while adding, and tags the review **"Too Many Items"**. The number is advice, not a rule: the warning never refuses an add and no attempt is truncated.
- **The maximum size is 50 items**, counting every item the review holds, including items not eligible yet and items not available right now. An add that would cross it is refused as a whole, with the room left named. An attempt is never truncated.
- **A review allows all item kinds by default**, or a chosen subset of them. The choice is shown on the review and can be changed later; a kind the review still holds items of cannot be turned off until those items are removed.
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
- **Export and backup (F7):** custom reviews, their statuses, and their attempts should be included.

### Related notes

- **Relationship to Quick review:** Quick review is a one-off chooser that is not saved. Custom reviews are saved and repeatable. Both use the same exercise screen.
- **Relationship to the Review feature's status suggestions:** custom attempt ratings do not count toward the "runs of ratings" that trigger suggestions to mark an item Learned or Still difficult.
- **Naming:** the sequential default name is "Custom Review N", using the next unused number and given when the review is created, so deleting a review never causes a duplicate name. The learner can rename at any time.
- **Three statuses, and only one of them belongs to the app.** **Empty** follows the item count, **Draft** is where a review with items begins, and **Finished** is the learner's own "this is ready to practise" mark. They are not the attempt statuses. The words **Finished** and **Finished early** mean different things and are never used for each other: one is a review ready to practise, the other is an attempt ended before every item was rated.
- **The recommended size is spoken of, never enforced; the maximum is enforced, never silent.** Twenty items is roughly a quarter of an hour of practice, so the app informs and tags rather than blocks. Fifty is the hard ceiling: an add that would cross it is refused as a whole and says how much room is left.
- **Experience principles that apply:** simple first, calm and encouraging, quick on any device, and distraction-free during attempts.

## Goals

- Let the learner create their own practice sets from any items they choose, across all collections.
- Make it easy to gather items from many places, with one place that already exists to keep the picks safe.
- Let the learner say plainly when a set is ready to practise, and tell at a glance which sets are ready and which are still being built.
- Warn calmly when a set has grown long enough to take real time, and refuse clearly only at the hard maximum.
- Let the learner focus a review on chosen kinds of items, with the choice visible and changeable.
- Let the learner practise a set repeatedly and see their history over time.
- Keep custom practice completely separate, so it never disturbs the daily plan, statuses, marks, or progress.

## Requirements

### Navigation and the listing page

1. The **Review** page has a link to **Custom reviews**, which opens the Custom reviews listing.
2. The listing shows all the learner's custom reviews as cards, with a **New custom review** action that creates a review immediately, named "Custom Review N" by default, and opens it.
3. The listing can be **searched by name** and **filtered by status** — **Empty**, **Draft**, or **Finished**. Search and filter choices are kept in the page address.
4. The listing has friendly empty states: when there are no custom reviews yet, and when a search or filter matches nothing.
5. The learner can open any custom review. Only one marked **Finished** that has an eligible item can be attempted; an **Empty** or **Draft** review says what it still needs — items, or the Finished mark.

### Starting a review and filling it

6. The **Add to review** action in each Collections list adds the selected items to a review the learner is still building. It works with the existing bulk-selection pattern, and an item can be added from any collection.
7. If there is **one** such review, the items go into it without asking. If there are **several**, a small picker asks which one, with the most recently created already chosen. If there are **none**, a review is created and named "Custom Review N", and the items go into it.
8. A review the learner is still building is one whose status is **Empty** or **Draft**. A **Finished** review is not offered here, because the learner has already said it is ready; to add to one, open it and use **+ Add items**, which never asks.
9. Adding is always to a review the learner chose or is standing in. Nothing joins a review without the learner choosing it.
10. A new review is a saved custom review from the first moment, so it **survives page changes, refresh, and a different device** without any special handling, and it holds at most **50 items**.
11. A review with no items keeps its **Empty** status on the listing and stays there until the learner deletes it. The app never deletes one silently or after a delay.
12. Items already in the review show as added, and adding them again does nothing.
13. The learner can remove items, or clear the review. Clearing asks for confirmation.
14. Adding items never changes an item's marks, statuses, ratings, schedule, or any session.
15. When an add would take a review past the recommended **20 items**, the app warns first — for example "This review will hold 26 items. Long reviews take a while to practise." — and offers **Continue** and **Cancel**. Continue adds the items anyway.
16. The warning never blocks anything below the maximum. No item is left out by it; the recommendation only says how long the practice is likely to be. The hard maximum is a separate, clearly named refusal (requirements 78–80).
17. Custom reviews are never shown in Collections, Review, Progress, or any other place, and they change nothing anywhere else. There is no basket bar and no separate basket page — every review lives on the listing like any other.

### Empty, Draft, and Finished

18. Every custom review has exactly one status: **Empty**, **Draft**, or **Finished**. Only a **Finished** review can be practised.
19. **Empty** is the status of a review with no items, and a new review starts there. The app sets it again the moment a review loses its last item, and the learner never chooses or clears it.
20. The first item added changes **Empty** to **Draft** by itself. With Empty, that is the only status change the app ever makes on its own.
21. The learner marks a review **Finished** when they want to practise it, and can put it back to **Draft** whenever they like. The control sits on the review's page and on its card, and the change takes effect at once — there is no save step, as with the name.
22. Only the learner moves a review between Draft and Finished. Growing past the recommended size, an attempt finishing, adding items, and archiving or restoring a session all leave the choice alone. Losing the last item is the one exception, and it moves the review to Empty.
23. A **Draft** or **Empty** review cannot be started. Its card and page offer no Start action, and the page says what would make it ready — add items, or mark it Finished. A **Finished** review with no eligible item left cannot be started either, and says its items are unavailable right now.
24. Putting a Finished review back to Draft removes its Start action everywhere on the next load. An attempt already in progress stays resumable and finishes normally; only new attempts are blocked.
25. A review that was emptied and then filled again comes back as **Draft**, so the learner looks at the new set before practising it. Its past attempts stay exactly as they were.

### Setting up a review

26. Opening a review shows its items grouped by collection with counts, its **name**, its **description**, and its **status** with the Draft-or-Finished control. All three can be set or changed at any time, and changes take effect immediately — there is no save step.
27. The learner can give the review a **name** and an optional **description**. A review created without a name is named **"Custom Review N"**, using the next number not already used by one of the learner's reviews.
28. There are **no counts to set per exercise kind**. What the learner adds is what the review holds, and an attempt uses every eligible item in it.
29. A review of more than 20 items carries a **"Too Many Items"** tag beside its name, with its item count. The tag is worded exactly as on the card, and it goes away once the review holds 20 items or fewer.
30. Items that are in the review but **not eligible yet** (for example a mistake with no correction, or a question with no natural version) are shown in a separate line with the reason, so the learner understands why they are not used.
31. **+ Add items** on a review's page opens the Collections pages **filtered to eligible items of the kinds the review allows**, so the learner only sees items that can be used. Every item there has the same **Add to review** action, and its picks land in this review.
32. Removing an item from the review's page does not affect the item in its collection or in any other review.
33. Items the learner did not add do not appear. An item added to Collections later does **not** join any review by itself.
34. None of this changes any item, and a review has no attempts until it is first attempted.

### Editing a custom review

35. The learner can rename a review, change its description, change its status, and add or remove items at any time.
36. Changes to items affect **future attempts only**. Past attempts are never rewritten.
37. Adding items uses the same **Add to review** action, the same picker, the same **+ Add items** filtered view, the same recommended-size warning, and the same maximum and allowed-kind checks.
38. Removing every item leaves the review **Empty**, and emptying a review never rewrites its history — past attempts keep their snapshots and their **Deleted** indicators exactly as they were.

### Attempting a custom review

39. **Start practice** appears on a review's card and page only while it is **Finished** with at least one eligible item, and it names how many items the attempt holds — for example "26 items".
40. Starting an attempt builds the attempt's fixed list from **every eligible item in the review**, using the same rules as the daily review for mixing kinds and never repeating an item. Each item's text is read at that moment.
41. An attempt uses the **same exercise screen, sections, stepper, rating buttons, undo, early finish, and saving** as Review. The stepper shows one step per kind that has items in the attempt.
42. Ratings made in an attempt are stored **on that attempt only**. They do not change the item's schedule, marks, statuses, or any other review.
43. Undo, early finish, and resuming a half-finished attempt work as in Review, but only within the attempt.
44. An attempt is **complete** when every item is rated or skipped. Early finish is allowed once an item is rated, and an unfinished attempt remains in the history as unfinished.
45. Every attempt is numbered. The screen shows the **current attempt number** ("1st attempt", "2nd attempt", "3rd attempt"), counting **every attempt started**, so an unfinished attempt is visible as the latest one.
46. An attempt keeps a **snapshot** of each item's text, meaning, example, and kind as it was attempted, so the history stays readable after items are edited.
47. An item that has been **archived** is shown as **Not available right now** and skipped in new attempts. It returns in full when its session is restored.
48. An item that has been **deleted** is removed from the review permanently. In **past attempts** that included it, the item stays in its snapshot with a **Deleted** indicator, so the learner knows why it is no longer part of the review.
49. An item that is still available but whose text was edited shows its current text in the next attempt, and its snapshot in past attempts keeps the older text.
50. Attempts never change the learner's marks: stars, statuses, and notes stay exactly as they were. They never change the review's own status either.
51. A learner can open the **source item** from an attempt (for example "See original session") to see its context, in a new tab, without losing the attempt.

### Attempt history and attempt statuses

52. Each attempt records its **number**, **start and finish dates** (local time), the **counts** of Easy, Okay, and Hard ratings, how many items were **skipped**, and its **status**.
53. Attempt statuses are:
    - **Not started**: created but nothing rated yet. Not counted as an attempt in the history.
    - **In progress**: started and some items rated, not yet complete.
    - **Completed**: every item rated or skipped.
    - **Finished early**: finished with some items unrated, by the learner's choice.
54. These statuses describe one attempt each. The review's own **Empty**, **Draft**, and **Finished** status is a different thing, and neither ever sets or changes the other.
55. Statuses are descriptive only. The app does not use pass or fail wording, and no status counts as a score.
56. The **attempt history** lists every attempt, most recent first, **in pages of 10 with a Load more action**, each with its number, date, status, and counts. It can be opened to show the items in that attempt, with their snapshot text, their ratings from that attempt, and any **Deleted** indicators.

### The custom review card

57. Each custom review is shown as a **card** on the listing page. The front of the card shows:
    - the **name**
    - the **status** — Empty, Draft, or Finished
    - the **current attempt number** (the latest attempt, for example "2nd attempt")
    - the **created date**
    - the **status of the previous attempt**, where there is one (for example "Previous attempt: Completed")
    - a short **summary of the attempt history** (for example "3 attempts · last on 12 Oct"), with a **View history** link to the full list
58. The card's status label always agrees with the review's own page, and the Draft-or-Finished control works from both places.
59. A review of more than 20 items carries the **"Too Many Items"** tag on its card with its item count, in the same words and at the same threshold as on the review's page.
60. A card whose review is **Draft** or **Empty** offers no way to start practice. It shows its item count and created date, and a **Delete** action in place of the attempt actions.
61. The card's attempt status is **specific to custom reviews**. It describes the custom review's own attempts only, and never reflects, changes, or is changed by any collection item's status.
62. The card shows the learner's **description** when there is one, shortened if long. It also shows how many items the review holds.
63. Cards can be sorted by **most recently attempted** (the default), by **name**, and by **created date**.

### Deleting a custom review

64. Any custom review, including an empty one, can be **deleted** from its card or its page. Deleting asks for confirmation first, and the safe choice (Cancel) is the default.
65. The confirmation states the number of attempts that will be deleted with it, and says that this cannot be undone. A review that has never been attempted simply says that it will be deleted.
66. Deleting a custom review **deletes its attempt history** as well. Nothing else is affected: the items remain in their collections, and no rating, schedule, status, or mark changes.

### Exclusivity

67. Custom reviews are **exclusive**. Nothing done inside a custom review (attempts, ratings, statuses shown, its own Empty, Draft, or Finished status, names, or history) affects any other page or calculation in the app, including Collections, the daily review, Quick review, the schedule, the "waiting for a fix" note, recent results, status suggestions, streaks, practice days, Progress totals, and marks.
68. Custom review ratings never count toward the "runs of ratings" that trigger status suggestions.
69. Custom review attempts never appear in **recent results**. The Review page's recent results list shows only daily and Quick reviews.
70. Custom reviews appear in **exports** with their attempts, but exports do not change anything in the app.

### Staying in step

71. Custom reviews reflect the learner's sessions straight away. When an item's session is finished, edited, archived, restored, or deleted, the review, its counts, its Empty status, and its availability change on the next load, as described above.
72. Eligibility uses the same rules as Review: finished sessions that are not archived, and the same rules for corrections, natural versions, bold parts, and meanings.
73. Custom reviews are never stale, and only the latest request updates the screen.

### Experience

74. The listing, review pages, and attempt screens work on a phone, tablet, and computer, in light and dark mode, with no sideways scrolling.
75. Everything can be done with the keyboard, with visible focus, and screen readers announce the item counts, the review's status, the allowed kinds, the size warning, the maximum refusal, the attempt number, the attempt statuses, and the other warnings calmly.
76. Status wording stays literal and calm: **Empty** means nothing in it yet, **Draft** means still being built, and **Finished** means ready to practise. None of them is a score, and none is used to mean an attempt's status.
77. Wording is friendly and calm. Attempts are described as practice, never as tests or scores.

### The maximum and the allowed kinds

78. A custom review holds at most **50 items**. Every item it holds counts, including items not eligible yet and items not available right now, so the number the learner sees is the number the maximum uses.
79. An add that would take the review past 50 is **refused as a whole** — nothing is added, and no partial subset is slipped in — with a message naming the room left, for example "This review has room for 5 more items." An add that lands exactly on 50 is allowed.
80. The maximum is enforced on every way in — **Add to review**, the picker, and **+ Add items** — with the same message in each, and the refusal changes nothing outside the review.
81. A review allows **all item kinds by default** — words, phrases, grammar notes, mistakes, and questions. The learner can choose a subset when creating the review and change it at any time later.
82. The review's page and its card **show which kinds the review allows**, in the collections' own words — for example "Words only", "Words + Phrases", or "All kinds".
83. An item of a kind the review does not allow is never added. The add is refused with a message naming the kinds the review allows, and **+ Add items** never shows items of other kinds.
84. Turning off a kind the review still holds items of is **refused until those items are removed**, with a message naming them — for example "Remove the 4 phrase items first." Turning a kind on is always allowed.
85. Changing the allowed kinds affects **future adds and future attempts only**. Past attempts keep their snapshots, and an attempt in progress finishes normally.

### Edge cases

- **A review left unfinished for weeks:** still there, with its items unchanged unless they were deleted, and still marked Draft or Empty.
- **Items that were archived:** shown as not available right now, and not counted as eligible.
- **A review whose items are all deleted:** it becomes **Empty** with a friendly note, and past attempts keep their snapshots with the Deleted indicators.
- **A review whose remaining items are all archived:** none are eligible, so an attempt cannot be started even when it is marked Finished, and the page says the items are unavailable right now. They return in full when the session is restored.
- **The last item removed from a Finished review:** it becomes **Empty** at once, its Start action disappears everywhere, and filling it again brings it back as **Draft** rather than Finished.
- **Marking a review Finished with nothing eligible in it:** allowed, since the status is the learner's, but no attempt can start until an item becomes eligible.
- **An attempt in progress when the review goes back to Draft:** the attempt resumes and finishes normally; only new attempts are blocked.
- **Adding to a review already above 20 items:** the warning shows again on every add that crosses the line, and the **Too Many Items** tag stays while the count is above 20.
- **A review far above the recommendation:** nothing truncates it. The tag and the item count are visible before starting, and the attempt still carries every eligible item.
- **An add that would cross 50 items:** refused as a whole, with the room left named; nothing is added and the review is unchanged.
- **A bulk add with room for only some of it:** the whole add is refused rather than a partial subset added, so the count never surprises.
- **A review at 50 whose items are archived:** it still holds 50, so nothing new can be added until items are removed; an attempt simply carries whatever is eligible.
- **Turning off a kind the review still holds:** refused with the count and kind named, until those items are removed.
- **Adding an item of a kind the review does not allow:** refused with the allowed kinds named; **+ Add items** never shows it.
- **Several unfinished reviews at once:** Add to review asks which one, with the most recent already chosen; the listing shows each one's name, status, and item count so they are tellable apart.
- **Empty and Draft reviews left behind:** they stay visible on the listing with their status and are deleted like any other review. Nothing is removed automatically, because silent deletion would be surprising.
- **Two attempts started at once:** each is its own attempt and gets its own number.
- **Renaming to a name already used:** allowed, since the sequential names only apply to reviews created without a name.
- **Deleting the only custom review:** the listing shows its empty state.
- **A session deleted that contained items in a review:** those items are removed from the review permanently, and the Deleted indicator appears in past attempts only.
- **A review at the maximum:** an attempt carries every eligible item and is still built quickly. The item list is shown in full — 50 items need no pages — and the attempt history is shown in pages.
- **Unfinished attempt left open:** remains In progress, and resumes where the learner left it.

## Technical

This describes the intended approach at a high level, using the agreed stack (Laravel, Inertia, Vue, Tailwind, shadcn-vue). It is a plan to refine, not a final design.

1. **Build on what exists.** Use the item identity, the study sessions rule, the eligibility rules from Review, the exercise sections and stepper, the exercise shell, and the bulk-selection pattern from Collections.
2. **Keep custom reviews in their own space.** Store custom reviews, their items, their statuses, their attempts, and each attempt's ratings separately from the daily review's ratings, schedules, and history, so no code path can mix them up. Mark attempt ratings so the schedule, suggestions, recent results, and Progress never read them.
3. **Stage in the review itself.** A review the learner is still building is an ordinary custom review record, so the existing review storage is the staging area. There is no basket table, no basket bar, and no save-as-review conversion.
4. **One status column, three values, two automatic moves.** Store the status on the review as an enum — `Empty`, `Draft`, `Finished` — with `Empty` for a new review. Adding the first item moves `Empty` to `Draft`; losing the last item moves any status to `Empty`; only the learner's own control moves `Draft` to `Finished` and back. Nothing else in the app writes the column.
5. **One answer to "can this be practised".** A single read helper — `Finished` status and at least one eligible item — gates the card's Start action, the page's Start action, and the attempt-creation Action, so the three can never disagree. The listing's status filter reads the stored column straight from the query.
6. **Keep the size advice advisory and the maximum enforced.** Hold 20 as the recommended size and 50 as the hard maximum in one place. The add path returns "needs confirmation" with the resulting count when an add would cross the recommendation, and refuses with the room left when it would cross the maximum — checked against every item the review holds, inside the same transaction as the add. The front end shows the warning and re-sends the same request confirmed. Nothing in the attempt build reads either number, so no attempt is ever shortened.
7. **Store references and snapshots.** A review keeps item identities. Each attempt keeps a snapshot of each item's text and kind at the time of the attempt, so history stays readable after edits or deletions.
8. **Build the attempt list.** Reuse the Review piece that chooses items, extended with the eligibility rules, to build the fixed list from every eligible item in the review.
9. **Build the review flow.** New custom review creating a named review at once, the Add to review action with its single-review shortcut and its picker over the reviews still being built, the status control, the review's page with grouped items, name, and description, the "not eligible yet" line, and the **+ Add items** view filtered to eligible collection items.
10. **Build the listing and the card.** Search, status filters, sorting, the card's front with its status label and the Draft-or-Finished control, the "Too Many Items" tag, and the attempt history page.
11. **Build attempts.** Reuse the exercise screen with its stepper, ratings, undo, early finish, and resume, scoped to the attempt. Refuse to start when the review is not Finished or has nothing eligible, and record attempt numbers, statuses, and counts.
12. **Handle availability.** Archived items are marked not available and skipped; deleted items are removed from reviews, with the Deleted indicator shown only in past attempts' snapshots. Every path that drops an item — the learner's own removal, a session deletion, an archive — recomputes whether the review still has any, and moves it to `Empty` when it does not.
13. **Handle deletion of a review.** Confirm, then delete the review and its attempts, touching nothing else. Empty reviews use the same path.
14. **Keep everything exclusive.** Add tests that prove custom attempts, ratings, statuses shown, review statuses, and the recommended-size warning change nothing outside custom reviews.
15. **Connect exports.** Include custom reviews, their statuses, and their attempts in the exports planned in F7, without changing anything in the app.
16. **Make it work everywhere.** Test on phone, tablet, and computer, in light and dark mode, with the keyboard and a screen reader, and at the maximum size.
17. **Store the allowed kinds on the review.** One column on the review holds the chosen kinds, empty for all of them. The add Action checks each item's kind against it, the **+ Add items** view filters by it, and narrowing is refused while the review holds items of a kind being turned off. Past attempts and an attempt in progress never read it.
18. **Page the attempt history.** Reuse the listing's Load more pattern, 10 attempts per page, most recent first, keeping the history's query count flat however many attempts there are.

### UI components: use shadcn-vue wherever possible

All interface pieces should come from **shadcn-vue**. Only build a custom piece when shadcn-vue has nothing suitable, and then compose it from shadcn-vue parts with Tailwind. Do not add any other component library.

| Custom reviews need                               | shadcn-vue component to use                            |
| ------------------------------------------------- | ------------------------------------------------------ |
| Custom review cards                               | Card                                                   |
| Search by name and status filter                  | Input, Select, Toggle Group                            |
| Status label on a card — Empty, Draft, Finished   | Badge, with text                                       |
| "Too Many Items" tag with the count               | Badge, with text                                       |
| Draft-or-Finished control on card and page        | Toggle Group, with a clear label                       |
| Recommended-size warning when adding              | Alert Dialog, with Continue and Cancel                 |
| Maximum and allowed-kind refusals                 | Alert, naming the room left or the kinds allowed       |
| Allowed-kinds chooser                             | Toggle Group, multiple selection, with a clear label   |
| Allowed-kinds label on card and page              | Badge, with text                                       |
| "Which review?" picker over unfinished reviews    | Dialog, with Select or Command, the most recent chosen |
| Review page and grouped items                     | Card, Checkbox, Separator                              |
| + Add items, filtered to eligible items           | The existing Collections lists, with filter controls   |
| Name and description                              | Input, Textarea, Label                                 |
| Not eligible yet line                             | Alert, or Card section                                 |
| Attempt stepper                                   | The existing Stepper, linear option off                |
| Attempt history                                   | Table on wide screens, stacked cards on phones         |
| Deleted indicator and Not available right now     | Badge, with text                                       |
| Delete and clear confirmations                    | Alert Dialog                                           |
| Add to review, Start practice, Done, Finish early | Button                                                 |
| Messages                                          | Sonner (toast) and Alert                               |
| Loading and empty states                          | Skeleton, and the existing empty-state piece           |

## Implementation Steps

The steps are ordered so each one leaves a usable, reviewable piece of the feature.

### Step 1: New reviews, the three statuses, and "Add to review"

**What it delivers:** New custom review creating a named review at once in the Empty status, the Add to review action in Collections routing items into a review still being built (asking only when there is a choice), the automatic Empty and Draft moves, the recommended-size warning, and the item list with remove and clear — all stored as ordinary custom reviews, with no basket bar, basket page, or basket storage anywhere.

**Tests**

- New custom review creates a review named "Custom Review N" with the next unused number, and it appears on the listing as Empty.
- Adding the first item moves the review from Empty to Draft by itself, and losing the last item moves it back to Empty from whichever status it was in.
- Nothing else changes the status. Further adds, an archive, a restore, and an attempt all leave a Draft review a Draft.
- Add to review adds selected items from every collection into the only unfinished review without asking.
- With several unfinished reviews, a picker asks which one and has the most recently created already chosen. With none, a review is created and named.
- A review marked Finished is never offered by the picker; topping one up happens from its own page.
- Items already in the review show as added, and adding them again does nothing.
- An add that would take a review past 20 items warns first and names the resulting count. Cancel adds nothing, and Continue adds every item asked for.
- Crossing the recommended size again warns again, and a review already above it warns on every further add.
- An add of any size is never refused, and no item is ever left out because of the recommendation. _(Superseded by Step 7: the recommendation still never refuses; the 50-item maximum does.)_
- A review survives page changes, refresh, and a different browser session for the same learner, has no maximum size, and adding many items stays fast. _(Superseded by Step 7: a review holds at most 50 items.)_
- Removing items and clearing a review work, and clearing asks for confirmation.
- Add to review changes no item's marks, statuses, ratings, schedule, or sessions.
- No basket bar, basket page, or basket data appears anywhere in the app.

### Step 2: Naming, describing, setting the status, and topping up a review

**What it delivers:** The review's page with its grouped items, its name and description settable at any time, the Draft-or-Finished control, the "Too Many Items" tag, the not-eligible line, and the **+ Add items** view filtered to eligible collection items.

**Tests**

- The review's page shows its items grouped by collection with counts, and its name, description, and status, all changeable with no save step.
- A review created without a name is named "Custom Review N", using the next unused number, and renaming never causes a conflict.
- Marking the page's review Finished takes effect at once, and putting it back to Draft does the same.
- No limit controls exist on the review's page or in its requests — nothing caps how many items an attempt uses.
- A review of 21 items shows the "Too Many Items" tag with its count, and the tag is gone at 20 or fewer.
- Items not eligible yet appear in their own line with the reason.
- **+ Add items** opens the Collections pages filtered to eligible items, and each item there adds itself to this review.
- Removing an item from the review leaves it in its collection and in every other review.
- Items added to Collections later do not join a review by themselves, and nothing here changes any item.

### Step 3: Listing, search, filters, and cards

**What it delivers:** The Custom reviews listing with search by name, the Empty/Draft/Finished filter, sorting, the card with its front content, its status label and control, its "Too Many Items" tag, and the new custom review action.

**Tests**

- The Review page links to the listing, and the listing shows every custom review, unfinished ones included, as a card.
- Search by name and the status filter work together and are kept in the page address, and each of Empty, Draft, and Finished filters correctly.
- The card shows name, status, current attempt number, created date, previous attempt status, and the attempt history summary with a View history link.
- A card whose review is Draft or Empty has no Start action and offers Delete instead of the attempt actions; a Finished card has Start.
- The status control on a card and the one on the review's page change the same value, and the labels on both agree after any change.
- A card for a review of more than 20 items shows the "Too Many Items" tag with the same threshold and wording as the review's page.
- The card's attempt status describes custom attempts only, and never reflects a collection item's status.
- Sorting by most recently attempted, name, and created date works.
- Empty and no-match states are friendly.

### Step 4: Attempts and attempt history

**What it delivers:** Starting an attempt from a Finished review, the exercise screen scoped to the attempt, attempt numbering, attempt statuses, counts, snapshots, and the attempt history page.

**Tests**

- Starting an attempt builds a fixed list from every eligible item in the review, using the daily rules for mixing and no repeats.
- A Draft review and an Empty review offer no Start action anywhere, and a request to start one is refused.
- A Finished review with nothing eligible — every item archived, or none of them usable yet — cannot be started and says its items are unavailable right now.
- The Start control names how many items the attempt holds.
- Putting a Finished review back to Draft mid-attempt leaves the running attempt resumable and finishable, and blocks only new attempts.
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

**What it delivers:** Archived items marked not available and skipped, deleted items removed from reviews with a Deleted indicator in past attempts, the automatic move to Empty when the last item goes whatever its status was, and the permanent deletion of reviews with their attempts.

**Tests**

- An archived item shows as Not available right now and is skipped in new attempts, and it returns in full when its session is restored.
- Archiving every item in a Finished review leaves it Finished with its items present, and stops it starting an attempt because nothing is eligible.
- A deleted item is removed from every review permanently, and its snapshot in past attempts keeps the text with a Deleted indicator.
- A review whose items are all deleted, or all removed by the learner, becomes Empty with a note, whatever status it held, and past attempts keep their snapshots.
- Filling that review again shows Draft, not the status it had before it was emptied.
- Deleting a review asks for confirmation, states how many attempts will be deleted, and then removes the review and all its attempts.
- Deleting an Empty review confirms and removes it with nothing else affected.
- Deleting a review changes nothing else: items, ratings, schedules, statuses, and marks are untouched.

### Step 6: Exclusivity, staying in step, and polish

**What it delivers:** Proof that custom reviews are exclusive, a check that nothing else changes, and a full pass over speed, wording, accessibility, and consistency.

**Tests**

- Custom attempt ratings never count toward the daily schedule, status suggestions, recent results, streaks, practice days, Progress totals, marks, or the "waiting for a fix" note.
- Custom reviews never appear in recent results, and the daily review's suggestion is unchanged by any custom activity.
- A review's Empty, Draft, or Finished status never appears on, or changes, anything in Collections, Review, or Progress.
- Editing a session, archiving, restoring, and deleting a session each update custom reviews on the next load, and nothing else changes.
- Custom reviews are never stale, and only the latest request updates the screen.
- Exports include custom reviews, their statuses, and their attempts without changing anything in the app.
- A full journey works: create a review, gather items from several collections with the size warning answered, mark it Finished, attempt every item it holds, undo one rating, finish early, see the attempt in history, put the review back to Draft and see Start disappear, archive a session and see its items become not available, delete an item and see the Deleted indicator in past attempts, and delete the review with its attempts.
- Every screen works on phones, tablets, and computers, in light and dark mode, with the keyboard and a screen reader, and with no sideways scrolling.
- The status, the item count, the "Too Many Items" tag, and the warning are worded the same everywhere they appear, and nothing calls a practice session a test.
- With about 5,000 items and many reviews, the listing, review pages, and attempts stay fast, with query counts that do not grow with the number of items. _(Superseded by Step 7: the scale guarantee is now the 50-item maximum.)_

### Step 7: The item maximum, allowed kinds, and paginated history

**What it delivers:** The 50-item hard maximum enforced on every add path, the per-review allowed kinds with their chooser and label, the refusal messages, and the attempt history in pages of 10 with Load more.

**Tests**

- An add that would cross 50 items is refused as a whole and names the room left; an add landing exactly on 50 succeeds.
- The maximum counts every item held, including not-eligible and archived items, and is enforced the same way through Add to review, the picker, and + Add items.
- The recommended-size warning still appears past 20 and never blocks below the maximum.
- A new review allows all kinds by default, and the chosen kinds are shown on the review's page and card in the same words.
- An item of a disallowed kind is refused from every add path, and + Add items shows only eligible items of the allowed kinds.
- Turning off a kind the review holds items of is refused until they are removed; turning kinds on always works.
- Changing the allowed kinds leaves past attempts and an attempt in progress unchanged.
- The attempt history shows 10 attempts per page, most recent first, with Load more, in a flat number of queries.
- A review at the maximum renders and starts an attempt quickly.
- The refusals and the allowed-kinds label work with the keyboard and are announced by screen readers, and nothing about them changes anything outside custom reviews.

## Open Questions

None at the moment.

## Decisions Made

- **Custom reviews are saved, named, and repeatable.** Quick review remains a one-off chooser in the Review feature.
- **Custom reviews are exclusive.** Nothing done inside them affects any other page or calculation.
- **Custom attempts do not change item marks, ratings, schedules, or statuses**, and never feed the daily suggestion, recent results, status suggestions, streaks, or Progress.
- **A review still being built is the staging area.** Creating one makes it immediately, named "Custom Review N", and Add to review fills it. The basket, its storage, its bar, its page, and the "save as custom review" step are gone.
- **Three statuses — Empty, Draft, and Finished — and only Finished can be practised.** Empty and Draft follow the item count automatically; Finished is the learner's mark alone. Keeping the app's own moves down to those two means the learner's intent is never overruled, while a set that was emptied still has to be looked at before it is practised again.
- **An emptied and refilled review comes back as Draft, not Finished.** The rebuilt set is not the set the learner marked ready, so the mark is asked for again. This is the one place where Empty overrides a Finished choice.
- **An attempt already running is never cut short by a status change.** Putting a review back to Draft blocks new attempts only, because stopping halfway would throw away the practice the learner had just done.
- **One unfinished review is used without asking; several open a small picker defaulting to the most recent.** Finished reviews stay out of the picker, so a set the learner closed is never topped up by accident.
- **Twenty items is the recommended size, and it is only advice.** Crossing it warns and can be continued past; being above it tags the review. Twenty is roughly a quarter of an hour of practice. Blocking or truncating on the number would refuse something the learner clearly asked for.
- **The warning, the tag, the card, and the page all read the same number.** A threshold that disagreed between places would be read as a bug rather than as guidance.
- **Empty reviews stay visible on the listing with their status and are deleted like any other review.** Nothing is deleted automatically, because silent deletion would be surprising.
- **Items reach a review only through the learner's choice** — Add to review in a collection, or + Add items from the review's own page, filtered to eligible items.
- **There are no per-kind exercise limits.** An attempt uses every eligible item the review holds. Exercise kinds still group the attempt's sections and stepper, and the overall size is bounded by the maximum below.
- **The maximum is 50 items, counting everything the review holds.** It keeps every review page and every attempt fast at a size the learner can actually reach, and it is enforced as a whole-add refusal that names the room left. The recommended 20 stays advice; only the maximum blocks.
- **A review allows all kinds by default, or a chosen subset.** The choice is shown on the card and page and changeable later; a kind still held cannot be turned off until its items are removed, so narrowing never silently drops anything.
- **The attempt history is paginated; the item list is not.** Ten attempts per page with Load more bounds a history that can grow forever, while the 50-item maximum makes paging the item list unnecessary.
- **Default names are sequential**, using the next unused number, given when the review is created.
- **Deleted items are removed permanently** from reviews. Past attempts keep a snapshot of the text with a Deleted indicator.
- **Archived items are not available** while archived, and return in full on restore.
- **Deleting a review deletes its attempts** as well, after confirmation.
- **Attempt statuses are Not started, In progress, Completed, and Finished early**, described without pass or fail wording.
- **Custom review attempts never appear in recent results.**

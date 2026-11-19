# Progress and motivation

## Summary

Progress and motivation gives the learner an honest, encouraging view of what their practice is adding up to. It shows the **streak**, total **practice days**, finished **sessions**, and the **words, phrases, and grammar points** collected in finished sessions. It also shows how the learner's **common mistakes** are changing over recent weeks, a **weekly summary**, and one small highlight to celebrate. The Home screen shows the same streak and practice-day numbers, so the two screens cannot disagree.

The session-derived measures in this feature are part of the first version. Notebook entries are not sessions and do not create session totals, practice days, or streaks. Practice in a daily, Review a bit more, or Quick review appears as **separate review-activity reporting**: items practised, review days, and reviews finished, kept apart from every session-derived number.

This document specifies F5 and does not claim that the feature is implemented or tested. It is written to be planned and reviewed in detail before implementation.

## Goals

- Show the learner that their practice is adding up, without turning progress into a score or a judgement.
- Keep the meaning of every number clear: a finished session, a practice day, a collected word, or a reviewed item are different things.
- Show the same streak and practice-day numbers wherever they appear, so Progress and Home never contradict each other.
- Make the learner's streak feel motivating but honest, including when it is short or has been broken.
- Help the learner notice which recurring mistakes are fading and which still need attention.
- Give a short weekly summary and one genuine highlight that encourage the learner to come back.
- Keep session-derived progress stable and predictable when sessions are archived, restored, edited, or deleted.
- Give daily, Review a bit more, and Quick review activity from both sessions and notebooks its own clearly labelled place, separate from every session-derived number.
- Work comfortably on a phone, tablet, and computer, in light and dark mode, with the keyboard and a screen reader.

## Requirements

### Session-derived progress

1. **Progress** is reachable from the main navigation and opens a single Progress page. The page is read-only: opening or refreshing it must never create, change, or repair content.
2. The top of the page shows the learner's **current streak** and **total practice days** as large, friendly numbers, with a short plain-language explanation of what each one means.
3. A **practice day** is a day on which at least one practice session was **finished**. The day is the learner's own local day, based on when the session was finished, not the server's day.
4. The **current streak** is the run of consecutive practice days ending today, or ending yesterday when the learner has not practised today yet. If neither today nor yesterday is a practice day, the current streak is `0`.
5. Several sessions finished on the same day count as **one practice day**.
6. The page shows a **finished-session count**. It counts sessions that have been finished, including finished sessions that are archived. Unfinished sessions never count.
7. The page shows the existing session-content totals: **words**, **phrases**, and **grammar points** from finished sessions, including archived ones.
8. These totals are **content collected in sessions**, not the learner's manual **Learned** labels and not proof of mastery. Marking an item Learned or Still difficult in Collections does not change them.
9. Typing notebook entries, adding comma-separated meanings, creating notebooks, or reviewing notebook items does not increase or decrease the session-content totals, the session count, the practice days, or the streak.
10. **Archiving** a finished session changes none of these session-derived measures. **Restoring** it changes none either.
11. Deleting a **finished** session lowers the totals by the content that session contained at the moment it was deleted, removes its contribution to the finished-session count, and removes its practice day when no other finished session was finished on that day. This can lower the streak.
12. Deleting an **unfinished** session changes none of these measures, because unfinished sessions never count.
13. A **notebook is not a finished session**. Creating, editing, or deleting a notebook must never invent a session, a practice day, or a session-content total.
14. When no finished session exists, the page explains what will appear here in a friendly way and points the learner to recording or finishing a session. A learner who has notebooks but no finished sessions is not told that their saved notebook content does not exist; the page simply keeps the session-derived measures at zero and explains the difference.
15. The finished-session count and the content totals are shown as separate, clearly labelled measures, so the learner can tell a session apart from a word, phrase, or grammar point.

### Mistakes over time

16. Progress includes a simple **Mistakes over time** chart. It shows a small set of the learner's most common session mistakes and how often each one appeared in each recent week.
17. A mistake is grouped using the existing session mistake identity: the pair of **what the learner said** and **the correction**, ignoring capitalization, accents, extra spaces, and formatting. The same mistake with different reasons is one group; the same correction for different things the learner said is a separate group.
18. A repeated mistake row in the same box of the same question counts as **one appearance**, matching the existing Collections rule. The chart must use the same identity and appearance rules as Collections, not a separate approximation.
19. The chart uses **session dates** to place appearances in weeks, because that is when the practice conversation happened. It includes mistakes from finished sessions, including archived ones, and excludes unfinished sessions.
20. The chart shows up to a small number of common mistakes (initially five) across the recent weeks (initially six). They are the mistake groups with the most appearances across the displayed weeks, with ties following the existing Collections order. These starting numbers may be tuned after use without changing the meaning of the chart.
21. When a session is edited, archived, restored, or deleted, the chart reflects its current mistake content on the next load. Deleting a finished session removes its mistake appearances; deleting an unfinished session changes nothing.
22. Notebooks contribute **no mistake entries** to the chart. A notebook-only learner sees an honest empty state rather than invented mistakes.
23. The chart is accompanied by a plain-text summary and an accessible table or list of the same values, so the trend is understandable without seeing the bars and without relying on colour alone.
24. When there are no mistakes, only one week of data, or too few appearances to show a trend, the page says so calmly and explains that the chart will grow as sessions are recorded. It must not fake a downward trend.

### Weekly summary and encouragement

25. Progress includes a **weekly summary card**. It shows the current week when at least one finished session has its **session date** in that week; otherwise it shows the most recent week with a finished session, clearly labelled with the week it covers, so a learner returning after a gap still sees a meaningful summary.
26. The weekly summary reports the learner's real activity for that week, such as:
    - finished sessions whose session date falls in that week, including archived ones;
    - words, phrases, and grammar points collected in sessions dated that week;
    - how many mistake appearances that week held compared with the previous week, when enough data exists, using the same mistake identity and session-date rules as the chart.
27. The weekly summary uses **session dates** for its activity, content, and mistakes, and the learner's local calendar week. Practice days and the streak remain separate measures based on the finish date.
28. The summary must not call collected words “learned” or “mastered”, and must not claim mistakes were “fixed” unless the data actually supports that statement. It may celebrate what the learner did: sessions finished, words collected, phrases saved, or a recurring mistake appearing less often.
29. The weekly summary offers one short **highlight** drawn from real data, for example the longest streak so far, the most sessions dated in a week, or a recurring mistake that appeared less often than the week before. It never invents a win.
30. When there is not enough data for a comparison, the card shows the activity that does exist and uses encouraging wording without a false comparison.
31. The summary is written in a calm, encouraging tone. It contains no score, grade, ranking, or wording that implies the learner has failed.

### Review-activity reporting

32. Progress shows **daily, Review a bit more, and Quick review activity** as its own clearly labelled area, visibly separate from session-content totals and from the session-based streak.
33. That reporting must treat **session-sourced and notebook-sourced** daily/Quick practice consistently. A notebook word and a session word with the same spelling remain separate scoped items, and neither one's practice is silently counted as the other.
34. Only the learner's **started review sittings** belong in review-activity reporting: the daily suggestion, **Review a bit more**, and **Quick review**, which all use the ordinary review record. Saved **custom-review attempts** keep their own rating records and never contribute, even when they use the same items.
35. Review activity must not change the learner's session count, session-content totals, practice days, or session-based streak.
36. The area uses three all-time measures, with no week filter or start date:
    - **Items practised** — one for every rating the review history holds. Rating the same item again **in the same sitting** replaces its rating rather than adding a second; practising it again in another sitting adds another; undoing a rating removes it.
    - **Review days** — the learner's local days on which at least one rating was first given, taken from the rating's own timestamp. A rating changed later in an open sitting keeps its first day.
    - **Reviews finished** — sittings that ended with every item rated or an early finish and that still hold at least one rating.

    A review left part-way still adds its items and its day; only the finished-review count stays put, so leaving early is never a penalty. Undoing every rating removes that sitting's items and day, and it no longer counts as a finished review. Repeated practice counts each rating, and the area never shows a “words learned” figure.

37. **Deleting a source never lowers these measures.** A deleted session or notebook leaves its past ratings as practice that happened, so the three numbers stay; editing an item follows the existing typo-following rule for ratings, which moves the same rating record with its item, so neither the counts nor the day change; and archiving or restoring a session changes nothing. This is deliberately different from session-derived totals, which describe what the learner has now and do fall when a finished session is deleted. The measures count the rating history that already exists from when Review was introduced, need no backfill, and are worded “practised”, never “learned”.

### Experience

38. Progress works comfortably on a phone, tablet, and computer, with no sideways scrolling. The stat tiles, chart, weekly summary, and any review-activity area stack cleanly on narrow screens.
39. The page supports light and dark mode, visible keyboard focus, meaningful headings, and controls with accessible names.
40. The chart has a text alternative and announces its meaning calmly. The streak, practice days, totals, weekly summary, and any highlight are available to screen readers as plain text, not only as icons or colours.
41. The page stays fast and responsive with a large history: totals, chart buckets, and review activity are aggregated in the database, and the number of queries stays fixed as sessions, mistakes, or reviews grow.
42. Loading shows a calm skeleton or progress state. A failure shows a friendly message with a way to try again; it never leaves a blank page or made-up numbers.
43. Wording is encouraging and uncluttered. Hard weeks and broken streaks are described gently, and no number is presented as a judgement of the learner.

### Assumptions

- The app is for **one learner** in this version. All progress belongs to that learner.
- **Practice sessions** already provide sessions, questions, answers, feedback entries, session dates, finished state, archived state, and content counts.
- A session counts for Progress only when it is **finished**. Archived finished sessions still count toward totals, the finished-session count, practice days, the mistakes chart, and the weekly summary. Unfinished sessions never count.
- Progress totals can be higher than the content shown in Collections and Review, because archived sessions count in Progress while those features hide them. This is by design.
- The **session date** is the day the practice conversation happened. The **finish date** is when the learner marked the session finished. Practice days and the streak use the finish date; mistake weeks and weekly content summaries use the session date.
- The learner's **local day** decides day and week boundaries, including around midnight and daylight-saving changes. A week starts on Monday.
- **Collections** already defines session mistake identity, appearance counting, and the “Seen N times” rules. Progress reuses those rules rather than defining its own.
- **Notebooks** supply no mistakes, questions, answers, natural versions, session dates, or finished sessions. Notebook entries do not change the session-derived measures.
- Daily, Review a bit more, and Quick **review** sittings already record which scoped items were reviewed, how they were rated, and when. A Quick sitting is stored as a review marked custom by the Review feature; that flag predates the Quick rename and does not make it a saved review. Saved custom reviews keep their own attempts, ratings, and snapshots on separate records.
- Progress is a **read-only view**. It does not repair data, create missing sessions, or change a learner's marks.
- The app needs an internet connection; offline progress is not introduced here.
- The app contains **no AI** and does not generate meanings, grades, or progress claims.

### Dependencies

- **[Practice sessions](feature-Practice%20sessions%20%28copy%20and%20paste%20entry%29.md):** finished state, session date, finish date, archived state, content counts, and the source of all session-derived progress.
- **[Library](feature-F2%20Library%20of%20saved%20sessions.md):** archive, restore, delete, and the exact effect of deleting a finished session, including the deletion preview that must agree with Progress.
- **[Collections](feature-F3-Personal%20collections.md):** session mistake identity, appearance counting, seen counts, and the distinction between collected content and mastery. Collections' “Learned” marks do not change Progress totals.
- **[Review and re-practice](feature-F4-Review%20and%20re-practice.md):** daily, Review a bit more, and Quick review history, ratings, local-day handling, and the boundary that reviewing never extends the session-based streak. Saved custom attempts remain isolated.
- **[Notebooks](feature-Notebooks.md):** the rule that notebook entries are not finished sessions, notebooks have no mistakes, and notebook daily/Quick practice counts in review-activity reporting under the same agreed measures as session practice.
- **[Custom reviews](feature-Custom%20reviews.md):** saved attempts and snapshots that must never appear in Progress, streaks, or session totals. Their finished activity may appear in recent results as read-only history, which changes none of these measures.
- **Base app layout:** the main navigation with the Progress link, page header, stat tiles, cards, empty states, and responsive layout.
- **Home screen:** its streak and practice-day tiles move to the same calculation in Step 1, so the two screens cannot disagree.

### Edge cases

- **No sessions at all:** all session-derived numbers are `0`, with a friendly explanation and a way to start a session.
- **Only unfinished sessions:** the session count, practice days, streak, totals, and mistakes chart stay at zero because unfinished sessions never count.
- **Only notebooks, no sessions:** session-derived numbers stay at zero, and the page does not imply that notebook content was lost or that a session must be invented.
- **Several sessions finished on one day:** that day counts once for practice days and once in the streak.
- **No practice today but practice yesterday:** the streak continues through yesterday; it does not reset at midnight.
- **A missed day:** the streak becomes `0` when neither today nor yesterday is a practice day.
- **A finished session deleted:** totals, finished-session count, the mistakes chart, and possibly the streak and practice days change. The deletion warning must match the result.
- **An unfinished session deleted:** nothing on Progress changes.
- **A finished session archived or restored:** totals, session count, practice days, streak, and the mistakes chart do not change.
- **A finished session edited:** totals and the mistakes chart reflect the current content on the next load, including mistakes added or removed.
- **The same word or phrase in two finished sessions:** it counts once for each session in the content totals. Deleting one session lowers the totals by that session's contribution even though the same item remains elsewhere.
- **A session finished after its session date:** practice day and streak use the finish date; weekly content and mistake weeks use the session date.
- **An imported session with an older date:** its content and mistakes sit in the week of its session date; its practice day sits on the day it was finished.
- **The same mistake in several sessions:** one chart series, with appearances counted under the existing mistake identity.
- **The same mistake with different reasons:** one group, matching Collections.
- **A repeated mistake row in one box:** counted once.
- **A mistake without a correction:** grouped by what the learner said alone, as Collections does.
- **No mistakes yet:** an honest empty state; no invented downward line.
- **Only one week of mistakes:** show the data that exists and explain that a trend needs more weeks.
- **Notebooks contain the same words as sessions:** they do not change session totals, the mistakes chart, practice days, or the streak.
- **A notebook item marked Learned:** it does not change session-content totals or prove mastery.
- **A daily, Review a bit more, or Quick review completed:** it does not extend the session-based streak, practice days, or session totals.
- **A daily/Quick review left part-way:** its rated items and its review day count; the finished-review count does not.
- **A rating changed in an open sitting after midnight:** its review day stays the day it was first given; a rating first given after midnight starts a new day.
- **Every rating in a finished sitting undone:** its items and day go, and it no longer counts as a finished review.
- **A saved custom review completed:** it changes nothing in Progress.
- **Review activity has no data:** the review-activity area has an honest empty state, not zeroes that look like a failure.
- **A review source is deleted later:** its past ratings stay counted as practice that happened, so the review-activity numbers do not fall; they never turn into a session-derived total or a custom-review result.
- **A very long streak or very large total:** the number stays readable and does not break the layout.
- **A very large history:** the page stays responsive, with a fixed number of queries.
- **Two tabs open:** each shows correct numbers after its next load; no real-time subscription is promised.
- **Slow or failed loading:** the learner sees a calm loading or error state with a retry, never invented sample data.

### Related notes

- **Spec goal 4:** help the learner see progress over time and stay motivated, while distinguishing collected material from actual practice.
- **Spec success measures:** the learner practices review several times a week, repeats the same mistakes less often, and remembers more of the new words and phrases they have learned. Progress supports the mistakes-over-time measure directly, and review frequency through the review-activity area. Progress cannot prove that words and phrases are remembered.
- **Spec journeys:** finishing a session contributes to session-based practice days and the streak. Reviewing its items later does not extend the streak. Progress can show a recurring mistake fading over time without changing the learner's Learned labels.
- **Experience principles:** simple first, calm and encouraging, quick on any device, distraction-free, and rewarding.
- **Feature-list user flow:** open Progress, see the streak and total practice days, then finished-session and content totals, a mistakes chart over recent weeks, a weekly summary, and one encouraging highlight.
- **Feature-list screen:** large numbers for the streak and practice days, stat tiles for sessions and session-derived content, one mistakes chart, and a weekly summary card with a small celebration icon for a genuine win.
- **Existing placeholder:** the current Progress page is a visual placeholder using sample data, and Home's streak and practice-day tiles also show sample values. This specification replaces both with real session-derived progress and adds the review-activity area separately.
- **Review-activity decision:** all-time items practised, review days, and reviews finished; a review left part-way counts its items and day; undoing every rating removes it; deleting a source session or notebook never lowers the numbers; custom attempts never contribute. Confirmed 16 November 2026, with the full definition in [Notebooks — Open Questions](feature-Notebooks.md#open-questions).
- **Not part of this feature:** changing session totals, streaks, practice days, or session grouping; word-sense splitting; AI-generated insights; grades, scores, or rankings; detailed learning analytics; saved custom-review history; Notebook storage limits; import, export, reminders, sharing, voice, or learning goals.

## Technical

This describes the intended approach at a high level, using the agreed stack (Laravel, Inertia, Vue, Tailwind, shadcn-vue). It is a plan to refine, not a final design.

1. **Build on the existing progress calculation.** The app already has a progress reader that loads finished sessions, including archived ones, and a calculator that produces words, phrases, mistakes, grammar notes, practice days, and the current streak from a list of finished sessions. Reuse those rules as the single source of truth so Progress, the Library deletion warning, and Home cannot disagree.
2. **Replace the placeholder page with a real read model.** Keep the existing `/progress` address and main-navigation link. Replace the sample data with a server-side read query and an Inertia payload resource. Keep the page read-only: no writes, repairs, or task creation during rendering.
3. **Add the missing session-derived measures.** Extend the existing progress result with the finished-session count and the longest streak, both worked out in the same calculator from the same practice-day set, plus any values the weekly summary needs. Derive them from the same finished-session source, not from a second tally. Home's streak and practice-day tiles read the same result.
4. **Make day and week handling explicit.** Use the learner's timezone for the local day and the Monday-based week. Practice days and streak use the finish date; weekly content, mistake weeks, and the weekly summary use the session date. Keep this rule in one place and test midnight and daylight-saving boundaries.
5. **Build the mistakes trend as a bounded read.** Read session mistake entries from finished sessions, including archived ones, group them with the shared mistake identity, and bucket appearances by the local week of the session date in the database, the same way Notebooks groups its review candidates. Select the top common mistakes there and return only their weekly counts plus a plain-text/table representation for accessibility. Do not load every entry or session into the page just to draw the chart.
6. **Build the weekly summary from the same data.** Use the session-derived counts and the mistake trend for the week being summarised and the week before it. Show the current week when a finished session has its session date in it; otherwise show the most recent week with a finished session. Do not create a separate tally that can drift. Keep the wording honest: report sessions, collected content, and measured changes, not “mastery” or unsupported “fixed” claims.
7. **Choose the highlight from real measures.** The longest streak can be calculated from the practice-day set. A weekly or mistake highlight must be backed by the same data. When the data cannot support a highlight, show the activity that exists without inventing a win.
8. **Add a separate review-activity area.** Add a read query over the started review sittings and their ratings — daily, Review a bit more, and Quick — producing items practised, review days, and reviews finished, and expose them as their own payload area. Keep it visually and conceptually separate from session-content totals and the streak. The Quick chooser's sittings are stored as reviews marked custom by the Review feature, which is not a saved F13 review; saved attempts keep their ratings on their own attempt records. Read the review sittings and their ratings directly, so saved attempts cannot leak in and a flag name is never mistaken for the distinction.
9. **Respect scoped review sources.** Review activity must use the same scoped identities as Review and Collections, so a notebook word and a same-spelling session word stay separate. Do not guess a source from text.
10. **Present the page with shared pieces.** Use the existing page header, stat tiles, cards, badges, empty states, and loading pieces. Use shadcn-vue components where possible. Build the chart from accessible cards, text, and bars or an accessible chart component; always provide the same values as text. Do not add a new component library.
11. **Keep it mobile-first and accessible.** Stack tiles and cards on narrow screens, keep numbers readable, support dark mode, make the chart available to screen readers, and verify keyboard focus and heading order.
12. **Keep reads efficient and honest.** Filter and aggregate in the database, keep the number of queries fixed as the history grows, and never show sample or stale numbers. Progress should reflect source changes on the next load without keeping a duplicate copy that can drift.

## Implementation Steps

These steps are ordered so each one leaves a usable, reviewable piece of Progress. The tests are acceptance requirements; they are not evidence that the feature is already implemented. Session-derived measures follow the rules above, and review-activity reporting follows the agreed measures without changing them.

### Step 1: The session-derived Progress dashboard

**What it delivers:** A real Progress page reachable from the main navigation, showing the current streak, total practice days, finished-session count, and session-derived words, phrases, and grammar points. The page replaces the sample data, handles the no-session state honestly, and keeps archived and unfinished sessions in their correct places. Home's streak and practice-day tiles show the same calculated numbers.

#### Tests

- The Progress link in the main navigation opens the Progress page.
- Home and Progress show the same streak and practice days, including at zero before any session is finished.
- The page shows the current streak and total practice days as plain, readable numbers.
- A learner with no finished sessions sees `0` for the streak, practice days, session count, and content totals, with a friendly explanation and a way to start a session.
- A learner with only unfinished sessions sees the same zero values; unfinished sessions never count.
- A learner with only notebooks and no sessions sees zero session-derived values, and the page does not imply that notebook content is missing.
- Finished sessions are counted, including archived finished sessions.
- Words, phrases, and grammar points from finished sessions match the saved session content, including archived sessions.
- Marking an item Learned or Still difficult in Collections does not change any Progress total.
- Typing notebook entries, adding comma-separated meanings, and creating notebooks do not change the session count, practice days, streak, or totals.
- Several sessions finished on the same local day count as one practice day.
- The streak continues when the learner practised yesterday but not yet today, and resets when neither today nor yesterday is a practice day.
- Practice days near midnight and across daylight-saving changes use the learner's local day.
- Archiving and restoring a finished session change none of the numbers.
- Deleting a finished session lowers the totals and session count, and removes its practice day when no other session was finished that day; the resulting streak matches the Library deletion warning.
- Deleting an unfinished session changes nothing.
- Opening or refreshing Progress performs no writes and creates no records.
- The page works on a phone, tablet, and computer, in light and dark mode, without sideways scrolling.

### Step 2: Mistakes over time and the weekly summary

**What it delivers:** The Mistakes over time chart, the weekly summary card, and one honest highlight. The chart uses session-only mistakes and the same identity and appearance rules as Collections. The summary reports current and previous week activity without claiming mastery.

#### Tests

- The chart shows the learner's most common session mistakes across the recent weeks.
- The same mistake in several sessions appears as one series, using the pair of what the learner said and the correction.
- The same mistake with different reasons remains one group, and the same correction for different mistakes remains separate.
- A repeated mistake row in the same box of the same question counts once.
- Mistakes from finished, non-archived and archived sessions appear; unfinished sessions never appear.
- Notebooks contribute no mistake rows, and a notebook-only learner sees an honest empty state.
- The same word or phrase in a notebook does not create a mistake series or change the chart.
- Mistakes are bucketed by the local week of the session date, including sessions near midnight and daylight-saving changes.
- Editing a finished session updates its mistake appearances on the next load.
- Archiving or restoring a finished session leaves the chart unchanged.
- Deleting a finished session removes its mistake appearances; deleting an unfinished session changes nothing.
- With no mistakes, the page shows an honest empty state and does not draw a fake downward trend.
- With only one week of mistake data, the page shows that week and explains that a trend needs more weeks.
- The chart has a plain-text summary and an accessible table or list with the same values.
- The weekly summary shows the current week when a finished session has its session date in it, and otherwise shows the most recent week with a finished session, clearly labelled with the week it covers.
- The weekly summary reports finished sessions dated in that week, including archived ones, plus collected words, phrases, and grammar points, accurately.
- Weekly content and mistake comparisons use the session date; practice days and streak continue to use the finish date.
- The summary does not describe collected content as “learned” or “mastered”, and does not claim mistakes were “fixed” without supporting data.
- When there is not enough data for a comparison, the summary shows the activity that exists and avoids a false comparison.
- The highlight is drawn from real data, such as the longest streak so far or a mistake appearing less often, and is not fabricated.
- The page remains readable on a phone, and the chart and summary work in dark mode and with a screen reader.

### Step 3: Review-activity reporting

**What it delivers:** A separate, clearly labelled review-activity area on Progress for daily, Review a bit more, and Quick sittings from session and notebook sources. It shows items practised, review days, and reviews finished as all-time totals, excludes saved custom attempts, and never changes session-derived totals or the streak.

#### Tests

- The area shows items practised, review days, and reviews finished as separate, plainly explained numbers, each an all-time total with no week filter.
- The review-activity area is visually and conceptually separate from session-content totals and the session-based streak.
- Daily review activity appears for session-sourced items.
- Quick review activity appears for session-sourced items.
- A Review a bit more sitting counts under the same measures as daily and Quick sittings.
- Daily and Quick activity from notebook items appears consistently with session-sourced activity under the agreed measures.
- A notebook item and a same-spelling session item remain separate scoped items; practising one does not count as practising the other.
- Saved custom-review attempts never appear in Progress, even when they use the same items.
- A Quick sitting is counted even though the Review feature marks it custom when it stores it.
- A learner with only custom-review history sees an honest empty review-activity state, not custom-attempt numbers.
- A review left part-way adds its rated items and its day, but not a finished review.
- Rating the same item again in the same sitting counts once with its latest rating; rating it in another sitting adds another.
- Undoing a rating removes it, and its day drops only when it was that day's only rating; re-rating never counts twice.
- A rating changed in an open sitting after midnight keeps its first day; a rating first given after midnight starts a new day.
- Undoing every rating in a finished sitting removes its items and day and stops it counting as a finished review.
- Deleting a source session or notebook leaves all three numbers unchanged, as does archiving or restoring a session.
- Completing a review sitting does not change the session count, session-content totals, practice days, or the session-based streak.
- The review-activity area has a clear empty state when there is no review-sitting history.
- The area uses the learner's own calendar days for review days, consistent with the rest of Progress.
- The labels make clear that review activity is not proof of mastery and not the same as session content.
- The page still works on a phone, in dark mode, and with a screen reader.

### Step 4: Experience, performance, and regression checks

**What it delivers:** A polished, accessible, fast Progress page that stays consistent with the rest of the app and does not regress session, notebook, review, or custom-review behaviour.

#### Tests

- The page has a clear heading order, visible keyboard focus, and accessible names for every card, chart, and action.
- The chart's values are available as text, and the page does not rely on colour alone to communicate a trend.
- The streak, totals, weekly summary, and highlight are announced calmly and correctly by a screen reader.
- Long streak numbers, large totals, long words, and long mistake text do not break the layout.
- With a large history, the page loads quickly and the number of queries does not grow as sessions, mistakes, or reviews grow.
- Loading shows a calm skeleton or progress state; a failure shows a friendly message with a retry and never shows invented numbers.
- The page works on a phone, tablet, and computer, in light and dark mode, with no sideways scrolling.
- Refreshing after finishing, editing, archiving, restoring, or deleting a session shows the correct updated values.
- Archiving or deleting a session does not change a same-spelling notebook item, and notebook activity does not change any session-derived measure.
- Completing a daily or Quick review changes no session-derived measure or streak.
- Completing a saved custom review changes nothing on Progress.
- The Library deletion warning and Progress show the same session-derived totals and streak after a deletion.
- Home and Progress show the same session-based streak and practice days.
- Opening Progress performs no writes, and no sample progress values remain on Progress or Home.
- Existing session, Library, Collections, Review, Notebooks, and Custom reviews behaviour is unchanged; Home changes only in its streak and practice-day tiles.

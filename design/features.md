# Features: Spoken English Practice Companion

A plain-language specification of the app's intended behaviour, written so anyone on the team can follow it. Each feature gives a short description, what the learner can do, a **step-by-step user flow** showing how it feels to use, and a **UI overview** describing what the screen looks like.

The Notebook extension below is planned behaviour, not a claim of completed implementation or testing. Its detailed requirements and remaining decisions are in [Notebooks](feature-Notebooks.md). Priority labels do not report implementation status.

**Priority key:** 🟢 First version · ⚪ Idea for the future

---

## Summary

| #   | Feature                                  | Priority          |
| --- | ---------------------------------------- | ----------------- |
| F1  | Practice sessions (copy and paste entry) | 🟢                |
| F2  | Library of saved sessions                | 🟢                |
| F3  | Collections                              | 🟢                |
| F4  | Review and re-practice                   | 🟢                |
| F5  | Progress and motivation                  | 🟢                |
| F6  | Import from spreadsheets                 | 🟢                |
| F7  | Export and backup                        | 🟢                |
| F8  | Voice and pronunciation                  | ⚪                |
| F9  | Reminders                                | ⚪                |
| F10 | Sharing                                  | ⚪                |
| F11 | Smart sorting of pasted chats            | ⚪                |
| F12 | Learning goals and levels                | ⚪                |
| F13 | Custom reviews                           | ⚪                |
| F14 | Notebooks                                | Planned extension |

---

## F1. Practice Sessions (Copy and Paste Entry) 🟢

Lets the learner record a practice session by pasting the tutor's questions, the learner's answers, and the tutor's feedback into organized boxes.

**What the learner can do**

- Start a session by choosing or creating a **topic** (for example "Travel," "Job interview," "Daily routine"). Topics are shared with notebooks: renaming reaches every reference, and deletion is allowed only when no session or notebook uses the topic, including archived/unfinished sessions and empty notebooks.
- Add each **question** from the tutor and paste in their own **answer**.
- Write and paste in a simple **rich text editor** (bold, italic, bulleted and numbered lists) for the question, answer, natural version, and grammar notes, so formatting from the tutor's reply is kept.
- Paste the tutor's feedback into separate boxes: mistakes and corrections, new vocabulary, new phrases, grammar notes, and a more natural version.
- Leave out any box they do not need.
- Paste a whole list at once (for example, ten words, one per line) and have it turn into separate entries.
- See **mistakes in a table** (what I said, the correction, and an optional reason). Pasted lines split at an arrow or a spaced dash, and lines that cannot be split reliably stay whole and are marked **"Needs attention."**
- Rely on **automatic saving**, so nothing is lost if the page is closed by accident.
- **Edit** anything later to fix typos or tidy messy pasted text.

**User flow**

1. The learner finishes a practice chat with the AI tutor, outside the app.
2. They open the app and tap **"New session."**
3. They pick an existing topic or type a new one, for example "Ordering at a restaurant."
4. They tap **"Add question"** and paste the tutor's first question.
5. They paste their own answer in the answer box.
6. They paste the tutor's feedback into the matching boxes (mistakes, vocabulary, phrases, grammar, natural version). Empty boxes are simply skipped.
7. For lists like vocabulary, they paste everything at once and the app splits it into separate items, one per line.
8. They check the result, fix any messy text, and tap **"Add another question"** to repeat steps 4 to 7.
9. When finished, they tap **"Finish session."** A confirmation shows how many new words, phrases, and mistakes were saved.

**Optional shortcut: "Paste in order"**

1. Instead of the full layout, the learner chooses the guided mode.
2. The app shows one box at a time: question, then answer, then mistakes, then vocabulary, and so on.
3. After each paste, the learner taps **"Next."** The app moves on to the following box.
4. At the end, the learner reviews the whole question on one screen and saves it.

**UI overview**

_Screen: New Session_

- **Top bar:** topic name (tap to change), a small "Saved" indicator, and a **Finish session** button.
- **Question cards:** one card per question, stacked down the page. Each card has a large box for the question, a box for the learner's answer, and the feedback boxes underneath. The question, answer, natural version, and grammar notes boxes show a small formatting toolbar (bold, italic, lists).
- **Feedback boxes:** each has a clear label and a small colored tag (for example red for mistakes, blue for vocabulary) so they are easy to tell apart. Empty boxes are collapsed to keep the page tidy. Mistakes appear as a three-column table (What I said, Correction, Why) that becomes stacked cards on a phone, and rows needing attention show a clear marker.
- **Bottom of the page:** a prominent **Add question** button.
- **Guided mode:** a toggle at the top switches to "Paste in order," which shows one large box at a time with a progress dots row and **Back** and **Next** buttons.

```
┌─────────────────────────────────────┐
│ Topic: Ordering at a restaurant  ✓Saved │ [Finish]
├─────────────────────────────────────┤
│ Question 1                          │
│ [ Tutor's question ............... ] │
│ [ My answer ...................... ] │
│ ▸ Mistakes   ▸ Vocabulary   ▸ Phrases │
│ ▸ Grammar    ▸ Natural version      │
├─────────────────────────────────────┤
│        [ + Add question ]           │
└─────────────────────────────────────┘
```

---

## F2. Library of Saved Sessions 🟢

A single place to browse, search, and reopen every saved practice session. The Library stays **session-only**; notebooks have their own listing, and Collections brings both sources together. Archiving or deleting a session does not affect matching words in notebooks. A topic can disappear from the Library's session filter while remaining in use by a notebook and therefore protected from deletion.

**What the learner can do**

- See all sessions in one place, newest first.
- Filter by **topic**, **date**, or **type of content** (mistakes, vocabulary, and so on).
- **Search** for any word, phrase, or mistake saved in a session. Notebook content is searched in Notebooks or Collections.
- Open a session and read it again from start to finish in a clean layout.
- Archive finished sessions they no longer want to see, or delete any session. Unfinished sessions cannot be archived.

**User flow**

1. The learner taps **"Library"** in the main menu.
2. They see a list of all sessions, each showing its topic, date, and a short summary.
3. To narrow the list, they pick a topic, a date range, or a content type.
4. To find something specific, they type a word or phrase into the search box. Matching sessions and items appear as they type.
5. They tap a session to open it and read it from start to finish.
6. From there, they can edit the session, archive it (finished sessions only), or delete it. Deleting a finished session shows a clear warning first, including the exact effect on totals and streak, and the learner must tick "I understand this cannot be undone" before it goes ahead.

**UI overview**

_Screen: Library_

- **Top:** a search box and a row of filter chips (Topic, Date, Content type).
- **Main area:** a list of session cards, newest first. Each card shows the topic, the date, and small counts such as "6 questions · 12 words · 4 mistakes."
- **Opening a session:** a clean reading page with the questions and feedback laid out in the same order as when they were entered, plus **Edit**, **Archive**, and **Delete** in a small menu.
- **Empty state:** if nothing is saved yet, a friendly message and a **Start your first session** button.

```
┌─────────────────────────────────────┐
│ 🔍 Search words, phrases, mistakes  │
│ [Topic ▾] [Date ▾] [Type ▾]         │
├─────────────────────────────────────┤
│ Ordering at a restaurant            │
│ 12 Oct · 6 questions · 12 words     │
├─────────────────────────────────────┤
│ Job interview practice              │
│ 10 Oct · 8 questions · 15 words     │
└─────────────────────────────────────┘
```

---

## F3. Collections 🟢

Automatically gathers vocabulary, phrases, grammar notes, and common mistakes from **finished, non-archived sessions**, together with saved **notebook vocabulary, phrases, and grammar notes**, into one study index. Collections are not the notebooks themselves: entries are edited at their source.

**What the learner can do**

- Browse **Vocabulary**, **Phrases and expressions**, **Grammar notes**, and **My common mistakes**. Mistakes remain session-sourced.
- Filter by **Source: All / Sessions / Notebooks**, a particular notebook, or shared topics. All is the default; selecting a notebook selects notebook sources, and switching to Sessions clears that notebook choice.
- **Star** items, add formatted **personal notes**, and mark them **Learned** or **Still difficult**. These marks are shared across the app only for the same scoped item.
- Keep a notebook's word separate from the same word in a session or another notebook. Existing grouping across sessions is unchanged; grammar notes are never merged.
- Open the original session or notebook at the entry, and add selected scoped items to a custom review.

**User flow**

1. The learner opens **Collections** and chooses Vocabulary.
2. With All selected, “bank” from a session and “bank” from Nature reading appear as separate rows with clear source labels.
3. They choose Notebooks and then Nature reading to focus on that notebook's items.
4. They star its “bank”, add a personal note, and mark it Still difficult. The session's “bank” stays unchanged.
5. The panel offers **Open notebook** to edit its source; a session item's panel instead offers **See original session**.
6. Returning from the source restores the list context. In My common mistakes, session appearances still show which errors recur.

**UI overview**

_Screen: Collections_

- **Top:** four tabs and source, notebook, shared-topic, starred, status, and attention filters.
- **Main area:** source-labelled items with meaning/correction, star, and status. Counts, search, and selection distinguish scoped identities rather than combining matching spellings across sources.
- **Item detail:** its own content, notes, marks, and an appropriate source link. Notebook items never borrow meanings or examples from sessions.
- **Sorting and provenance:** existing session dates and seen counts keep their meaning. Notebook items show notebook provenance, not fabricated session counts. Mixed-source date/frequency presentation and duplicate notebook-row content resolution remain open in the Notebook specification.
- **Empty states:** notebook-only learners see their saved content, not a demand to finish a session first.
- **My mistakes tab:** session-sourced groups retain their frequency counters; Notebooks filtering produces an honest empty result.

**Full details:** [Collections](feature-F3-Personal%20collections.md) and [Notebooks](feature-Notebooks.md).

```
┌─────────────────────────────────────┐
│ [Vocabulary] Phrases Grammar Mistakes │
│ ☆ Starred only   Sort: Newest ▾     │
├─────────────────────────────────────┤
│ ★ reluctant — unwilling  [Difficult]│
│ ☆ affordable — not too expensive    │
│ ☆ catch up — talk after a long time │
└─────────────────────────────────────┘
```

---

## F4. Review and Re-Practice 🟢

Turns eligible saved material from finished, non-archived sessions and from notebooks into short exercises, and brings harder items back more often. Notebook vocabulary, phrases, and grammar notes use their own scoped content and daily-review schedules; mistakes and past questions remain session-sourced. A learner with notebooks but no sessions can still review.

**Quick review** is the unsaved chooser described here. **Custom reviews (F13)** are saved sets with isolated attempts. Daily and Quick ratings affect the scoped item's schedule, never another notebook's or a session's same-spelling item. The shared topic filter includes notebook topics, and source actions open the notebook or session as appropriate.

**What the learner can do**

- Practice with **flashcards**, **fix-the-mistake** exercises, **say-it-again** challenges, and **fill-in-the-blank** exercises.
- Rate each item **Easy, Okay, or Hard**, so hard items return sooner and easy ones less often.
- Get a **daily review** of around five minutes.
- Choose what to review: a topic, a collection, or only starred items.

**User flow: daily review**

1. The learner opens the app and sees today's suggestion on the home screen: "5 minutes: 8 items to review."
2. They tap **"Start review."**
3. The app shows the first item, for example a flashcard with a word on the front.
4. The learner tries to recall the meaning, then taps **"Show answer."**
5. They rate how it went: **Easy, Okay, or Hard.**
6. The app moves to the next item, which may be a different exercise type.
7. After the last item, a short summary shows how many were easy and how many need more practice. Hard items are scheduled to come back soon.

**User flow: fix-the-mistake**

1. The app shows a sentence the learner got wrong in a past session.
2. The learner types or says the corrected version.
3. They tap **"Check."** The app reveals the original correction and the tutor's explanation.
4. They rate how well they did and move on.

**User flow: say-it-again**

1. The app shows a question from a past session.
2. The learner answers out loud, in their own words.
3. They tap **"Show natural answer"** and compare it with what they said.
4. They rate the attempt and move on.

**User flow: choosing what to review**

1. From the review screen, the learner taps **"Quick review."**
2. They pick shared topics, collections, or "starred items only." Matching notebook items participate alongside session items without merging identities.
3. They choose how many items they want, then tap **"Start."** This does not create a saved custom review.

**UI overview**

_Screen: Review Home_

- **Top card:** today's suggested review ("5 minutes · 8 items") with a large **Start review** button.
- **Below:** a **Quick review** option, a link to saved **Custom reviews**, and recent daily/Quick review results. Saved custom attempts never enter this recent-results list.

_Screen: Exercise_

- **Distraction-free:** only one item on screen at a time, with a thin progress bar at the top and an **Exit** button.
- **Flashcard:** a big card in the center; tapping it or pressing **Show answer** flips it.
- **Fix-the-mistake:** the wrong sentence is shown, with an input box underneath and a **Check** button.
- **Say-it-again:** the question is shown with a **Show natural answer** button.
- **Rating buttons:** three large, clearly colored buttons after each answer: **Hard**, **Okay**, **Easy**.
- **Finish screen:** a friendly summary with counts and a **Done** button.

```
┌─────────────────────────────────────┐
│ ▓▓▓▓▓░░░░░  4 of 8            [Exit]│
├─────────────────────────────────────┤
│                                     │
│          reluctant                  │
│       (tap to show answer)          │
│                                     │
├─────────────────────────────────────┤
│   [ Hard ]   [ Okay ]   [ Easy ]    │
└─────────────────────────────────────┘
```

---

## F5. Progress and Motivation 🟢

Shows session-derived totals, streaks, and weekly summaries. The Notebook extension also requires **daily/Quick review-activity reporting**, with its exact measures still to be defined separately from session content totals.

**What the learner can see**

- Number of finished sessions and session-based practice days. A notebook is not a finished session.
- A **streak** of consecutive practice days. A practice day is a day on which a session was finished. Unfinished sessions do not count. Archiving never changes the streak, and deleting a finished session can lower it. Notebook entry editing and reviewing do not extend this streak.
- Existing word, phrase, and grammar-point totals from finished sessions, including archived ones. These are session-content totals, not the learner's manual Learned labels or proof of mastery; deleting a finished session lowers them. Typing notebook entries or adding comma-separated meanings does not increase these totals.
- **Mistakes reduced over time**, based on session-sourced mistakes; notebooks introduce no mistake entries.
- A **weekly summary**.
- Planned review-activity reporting that treats notebook and session items consistently for **daily and Quick reviews**. Saved custom-review attempts remain excluded.

**Open decision before implementing review-activity reporting:** define which measures and labels F5 shows, whether they count rated items or completed reviews, how repeated practice is represented, and what source deletion does to those measures. Do not invent a new “words learned” formula, change session-derived totals/streaks, or copy custom-attempt ratings into Progress. See [Notebook Progress requirements and open questions](feature-Notebooks.md#open-questions).

**User flow**

1. The learner taps **"Progress"** in the main menu.
2. The top of the screen shows the current streak and total practice days.
3. Below, they see counts for sessions completed, words learned, phrases learned, and grammar points learned.
4. A simple chart shows how often each common mistake appeared over recent weeks, so the learner can see which ones are fading.
5. At the end of each week, a summary appears, for example: "This week you learned 18 new words and fixed 6 recurring mistakes."
6. A short encouraging message highlights one win, for example "Your longest streak so far."

**UI overview**

_Screen: Progress_

- **Top row:** large friendly numbers for the current **streak** and total practice days.
- **Stat tiles:** existing cards for sessions and session-derived word, phrase, and grammar-point totals. Keep planned daily/Quick review-activity measures visibly separate once their definitions are agreed.
- **Mistakes chart:** one simple chart showing how often common mistakes appear week by week, going down over time.
- **Weekly summary card:** a short sentence with the week's highlights and a small celebration icon when there is a win.
- **Tone:** encouraging and uncluttered. No scores that make the learner feel judged.

```
┌─────────────────────────────────────┐
│ 🔥 4-day streak      📅 23 days     │
├─────────────────────────────────────┤
│ [24 sessions] [180 words] [65 phrases] │
├─────────────────────────────────────┤
│ Mistakes over time  ╲___            │
├─────────────────────────────────────┤
│ This week: 18 new words, 6 fixed 🎉 │
└─────────────────────────────────────┘
```

---

## F6. Import from Spreadsheets 🟢

Brings the learner's existing spreadsheet session records into the app so past work is not lost. This import path still creates sessions; spreadsheet-to-notebook import is outside the Notebook extension.

**What the learner can do**

- Bring in existing spreadsheet data.
- See imported items appear in the library and collections just like any other session.
- Preview the result before anything is saved.

**User flow**

1. The learner goes to **"Settings"** and taps **"Import."**
2. They choose their spreadsheet file.
3. The app shows a preview with the first rows laid out as sessions, words, phrases, and mistakes.
4. If the columns do not match, the learner tells the app which column holds what (for example, "this column is vocabulary").
5. The app highlights any rows with problems, such as empty cells, and lets the learner fix or skip them.
6. The learner taps **"Import."**
7. A confirmation shows how many sessions and items were added. They can open the library to see them.

**UI overview**

_Screen: Import (inside Settings)_

- **Step indicator** at the top: Choose file, Match columns, Preview, Done.
- **Choose file:** a large drop area with a **Choose file** button and a short note about what kind of file works.
- **Match columns:** a simple two-column layout. On the left, the spreadsheet's column names. On the right, a dropdown to say what each one is (topic, question, answer, mistakes, vocabulary, and so on).
- **Preview:** a small table with the first few rows. Problem rows are highlighted with a short explanation and options to **Fix** or **Skip**.
- **Done screen:** a confirmation with counts and a button to **Open library**.

```
┌─────────────────────────────────────┐
│ ① File  ② Match  ③ Preview  ④ Done  │
├─────────────────────────────────────┤
│ "Column A"  →  [ Topic        ▾ ]   │
│ "Column B"  →  [ Question     ▾ ]   │
│ "Column C"  →  [ Vocabulary   ▾ ]   │
├─────────────────────────────────────┤
│                  [ Next ]           │
└─────────────────────────────────────┘
```

---

## F7. Export and Backup 🟢

Lets the learner download their data as a personal backup. **An export described as “all data” must include notebooks**, their shared-topic references, rich descriptions, entries, scoped identities and marks, as well as session data, review state, and custom-review attempt snapshots within the existing backup scope.

Notebook export delivery and the representation of scoped relationships must be confirmed before this extension ships with an all-data backup promise. A session-only export must say what it includes rather than silently omit notebooks. This is a coverage requirement, not a claim that the current exporter already supports notebook parents; spreadsheet-to-notebook import is not introduced here.

**What the learner can do**

- Download all their data at any time in a common format that opens in a spreadsheet.
- Use the download as a personal backup.

**User flow**

1. The learner goes to **"Settings"** and taps **"Export."**
2. They choose what to export: everything, or only certain collections.
3. They tap **"Download."**
4. The file saves to their device, and a message confirms it is ready to open in a spreadsheet.

**UI overview**

_Screen: Export (inside Settings)_

- **Simple panel** with a short explanation: "Your data is yours. Download it any time."
- **Choices:** name exactly which sources and data are included, including notebooks when supported. Filtering vocabulary or phrases must retain their source scopes rather than merging same-spelling items across sources. Export format and notebook delivery details remain to be confirmed.
- **Action:** one large **Download** button.
- **After download:** a small confirmation message with the file name and the date of the last backup, shown for reassurance.

```
┌─────────────────────────────────────┐
│ Export your data                    │
│ ☑ Sessions  ☑ Vocabulary  ☑ Phrases │
│ ☑ Grammar   ☑ Mistakes              │
│            [ Download ]             │
│ Last backup: 12 Oct                 │
└─────────────────────────────────────┘
```

---

## Future Ideas

These are not part of the first version. They are listed so the design leaves room for them.

### F8. Voice and Pronunciation ⚪

- Record answers and listen back to their own speaking.
- Hear words and sentences read aloud with correct pronunciation.

**Possible flow:** The learner taps the microphone on an answer, speaks, plays the recording back, and compares it with a spoken version of the natural answer.

**Possible UI:** a round microphone button beside each answer, and a small play bar for the recording next to the natural answer.

### F9. Reminders ⚪

- Gentle nudges to practice or review at a chosen time.

**Possible flow:** The learner picks a time and days in Settings. At that time, a reminder invites them to start their daily review.

**Possible UI:** a simple settings card with a time picker, day buttons (Mon to Sun), and an on/off switch.

### F10. Sharing ⚪

- Share a session or collection with a teacher or friend.

**Possible flow:** The learner opens a session, taps **"Share,"** and sends a link. The other person can read it, and the learner can stop sharing at any time.

**Possible UI:** a **Share** button on the session page that opens a small panel with a link, a copy button, and a **Stop sharing** switch.

### F11. Smart Sorting of Pasted Chats ⚪

- Paste a whole tutor chat once and have the app split it into answers, mistakes, vocabulary, and natural versions automatically.
- Requires adding AI, which has a small running cost, so it is deliberately left out of the first version.

**Possible flow:** The learner pastes the full chat into one box and taps **"Sort it for me."** The app fills in all the boxes, and the learner checks and saves.

**Possible UI:** one large paste box with a **Sort it for me** button, followed by the usual session screen pre-filled for checking.

### F12. Learning Goals and Levels ⚪

- Set a goal such as preparing for an exam or a job interview.
- Support multiple learners or additional languages.

**Possible flow:** The learner picks a goal on first use, and the home screen suggests topics and reviews that match it.

**Possible UI:** a goal picker on first use (cards with icons), and a home screen banner showing progress toward the chosen goal.

---

## Tech Stack

The app is built with a modern, single-codebase web stack:

| Technology       | Role in the app                                                                                                                                                  |
| ---------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Laravel**      | The backend. Handles saving sessions, collections, and progress, as well as importing and exporting data.                                                        |
| **Inertia**      | The bridge between backend and frontend. Lets the app feel like a fast, smooth single-page app without a separate API.                                           |
| **Vue**          | The frontend. Builds the interactive screens, such as the session editor, review exercises, and progress views.                                                  |
| **Tailwind CSS** | Styling. Gives the app a clean, consistent, responsive look on phone, tablet, and computer.                                                                      |
| **shadcn-vue**   | Free, ready-made interface pieces such as buttons, cards, tabs, dialogs, and forms. Built on Tailwind, so they can be restyled freely to fit the app.            |
| **Tiptap**       | A free, open-source rich text editor for Vue. Gives the app simple formatting (bold, italic, lists) in longer text boxes, such as questions, answers, and notes. |

---

## F13. Custom reviews ⚪

**Description:** Saved, named practice sets built from the learner's chosen scoped items, with Empty/Draft/Finished status, repeatable attempts, and snapshot history. Membership is fixed unless explicitly edited, an identity follows a source edit under the shared rules, or a source item is permanently deleted. A saved review is not a live subscription to its source notebook. Custom attempts remain separate from daily/Quick practice: they never change item marks, daily ratings or schedules, statuses, streaks, or Progress.

**What the learner can do**

- Start an ordinary review and get it saved straight away as **Empty**, named "Custom Review N", surviving refresh. Adding its first item moves it to Draft; the learner chooses Finished when ready.
- Add session- or notebook-sourced items from Collections with **Add to review**, choosing which unfinished review takes them, or use **+ Add items** on a review's page for eligible items of allowed kinds.
- Choose **Create review and practise** on a notebook to create a **new** saved review and explicitly mark it Finished as part of that action. It requires 1–50 distinct selected members and at least one eligible item, after saving pending source edits. More than 50 candidates opens selection before any review is created; nothing is silently truncated.
- Name and describe a review at any time. An attempt uses every **eligible** member; ineligible members remain listed with reasons and still count toward the maximum.
- Keep the **recommended 20-item** warning and tag. Continue allows a valid set above 20, but cannot override the **50-item maximum**. Ordinary adds that cross 50 remain refused as a whole, with the room left named.
- Choose allowed kinds, with all kinds allowed by default. A notebook's populated boxes do not automatically narrow this setting; kinds already represented cannot be turned off until those items are removed.
- Add or remove members explicitly. New notebook entries never join automatically. Current source edits affect future attempts; started attempts, including those in progress, retain their snapshots.
- Attempt a Finished review with eligible content and browse its paged history. A notebook source link opens that notebook, never a same-spelling session item.
- Keep existing attempt snapshots with Deleted indications if a source notebook/item is deleted. Its references are removed; losing the last member makes the review Empty, not deleted.
- Search/filter reviews and delete a review with confirmation. Deleting the review removes its attempts but not source notebooks, sessions, or their items.

**Full details:** [Custom reviews](feature-Custom%20reviews.md) and [Notebooks](feature-Notebooks.md).

---

## F14. Notebooks

**Status:** Planned extension. [The Notebook specification](feature-Notebooks.md) records the agreed behaviour, implementation stages, acceptance criteria, and remaining decisions; this section does not claim it is implemented.

Notebooks are named containers for language found outside conversations. They reuse familiar entry editors without requiring a session, question, answer, or finish step. Collections remains the combined index; the Library remains session-only.

**What the learner can do**

- Create a notebook with a required **name**, one shared **topic** defaulting to **General**, and an optional **rich-text description**.
- After creation, enter **Vocabulary**, **Phrases**, and **Grammar notes** on **one autosaving board**. There are no mistakes or natural-version boxes.
- Edit name, topic, description, entries, and examples later. Save failures and two-tab conflicts use the same visible recovery patterns as session editing.
- Put comma-separated meanings in one word's meaning field, keeping it one scoped study item. Matching words in other notebooks or sessions stay independent; grammar notes remain separate even when their text matches.
- Search notebooks by name, topic, description, and entry content, including meanings and examples; open a matching entry directly.
- Use their notebook items in Collections, daily/Quick Review, and saved custom reviews, with marks shared only for each scoped item.
- Create a ready saved review and practise immediately when constraints are met; choose a subset above 50 distinct candidates. Later notebook additions do not join that review automatically.
- Hard-delete a notebook after an impact preview and explicit acknowledgement. The shared topic and other sources survive; custom-review references are pruned within scope and existing attempt snapshots remain readable.

**User flow**

1. After reading a book, the learner opens **Notebooks** and chooses **New notebook**.
2. They name it Nature reading, choose a shared topic or keep General, and optionally format a description.
3. Creation saves the notebook and opens its three-box board. They add “bank” with “river edge, raised ground beside water” as its single meaning field, and the work saves automatically.
4. In Collections, selecting Notebooks and Nature reading shows that scoped word. Its star and status do not affect the session's financial “bank”.
5. **Create review and practise** offers a valid immediate start or the required selection/size guidance. The resulting review's membership stays fixed while future source edits remain live.

**UI overview**

- **Notebooks listing:** searchable names, topics, item counts, last-updated information, and plain-text description previews, with New notebook and a topic filter.
- **Notebook page:** editable metadata and the full rich-text description above one familiar entry board, save feedback, Create review and practise, and a deliberate Delete action.
- **Source context:** Collections and practice show the notebook name and Open notebook at the entry, not invented session dates or questions.
- **Accessibility:** mobile-first layout, dark mode, visible keyboard focus, accessible editors, and clear save, selection, and deletion announcements.

**Remaining decisions:** parent representation, exact daily/Quick Progress measures, mixed-source sorting, duplicate-row content resolution, metadata limits/General default lifecycle, and export delivery remain in [Notebook Open Questions](feature-Notebooks.md#open-questions). Neither the 20-item recommendation nor the 50-item review maximum limits notebook storage.

---

## Not Included in the First Version

- Any built-in AI or automatic tutor. Practice conversations happen elsewhere.
- Accounts for several people. The first version is for one learner.

# Features: Spoken English Practice Companion

A plain-language list of everything the app does, written so anyone on the team can follow it. Each feature gives a short description, what the learner can do, a **step-by-step user flow** showing how it feels to use, and a **UI overview** describing what the screen looks like.

**Priority key:** 🟢 First version · ⚪ Idea for the future

---

## Summary

| # | Feature | Priority |
|---|---|---|
| F1 | Practice sessions (copy and paste entry) | 🟢 |
| F2 | Library of saved sessions | 🟢 |
| F3 | Personal collections | 🟢 |
| F4 | Review and re-practice | 🟢 |
| F5 | Progress and motivation | 🟢 |
| F6 | Import from spreadsheets | 🟢 |
| F7 | Export and backup | 🟢 |
| F8 | Voice and pronunciation | ⚪ |
| F9 | Reminders | ⚪ |
| F10 | Sharing | ⚪ |
| F11 | Smart sorting of pasted chats | ⚪ |
| F12 | Learning goals and levels | ⚪ |

---

## F1. Practice Sessions (Copy and Paste Entry) 🟢

Lets the learner record a practice session by pasting the tutor's questions, the learner's answers, and the tutor's feedback into organized boxes.

**What the learner can do**
- Start a session by choosing or creating a **topic** (for example "Travel," "Job interview," "Daily routine").
- Add each **question** from the tutor and paste in their own **answer**.
- Paste the tutor's feedback into separate boxes: mistakes and corrections, new vocabulary, new phrases, grammar notes, and a more natural version.
- Leave out any box they do not need.
- Paste a whole list at once (for example, ten words, one per line) and have it turn into separate entries.
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

*Screen: New Session*
- **Top bar:** topic name (tap to change), a small "Saved" indicator, and a **Finish session** button.
- **Question cards:** one card per question, stacked down the page. Each card has a large box for the question, a box for the learner's answer, and the feedback boxes underneath.
- **Feedback boxes:** each has a clear label and a small colored tag (for example red for mistakes, blue for vocabulary) so they are easy to tell apart. Empty boxes are collapsed to keep the page tidy.
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

A single place to browse, search, and reopen every saved practice session.

**What the learner can do**
- See all sessions in one place, newest first.
- Filter by **topic**, **date**, or **type of content** (mistakes, vocabulary, and so on).
- **Search** for any word, phrase, or mistake ever saved.
- Open a session and read it again from start to finish in a clean layout.
- Delete or archive sessions they no longer want.

**User flow**
1. The learner taps **"Library"** in the main menu.
2. They see a list of all sessions, each showing its topic, date, and a short summary.
3. To narrow the list, they pick a topic, a date range, or a content type.
4. To find something specific, they type a word or phrase into the search box. Matching sessions and items appear as they type.
5. They tap a session to open it and read it from start to finish.
6. From there, they can edit the session, archive it, or delete it. Deleting asks for confirmation first.

**UI overview**

*Screen: Library*
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

## F3. Personal Collections 🟢

Automatically gathers vocabulary, phrases, grammar notes, and common mistakes from all sessions into personal study lists.

**What the learner can do**
- Browse automatic collections: **Vocabulary**, **Phrases and expressions**, **Grammar notes**, and **My common mistakes**.
- **Star** the items that matter most.
- Add **personal notes** to any item.
- Mark items as **"learned"** or **"still difficult."**
- Jump from any item back to the session it came from.

**User flow**
1. The learner taps **"Collections"** in the main menu.
2. They choose a collection, for example "Vocabulary."
3. They see every word saved so far, each with its meaning and an example sentence.
4. They tap the **star** on words they want to focus on.
5. They tap a word to open it, add a personal note (for example, "Sounds like the word in my language"), and mark it "still difficult."
6. If they want more context, they tap **"See original session"** to view the conversation where the word first appeared.
7. In "My common mistakes," they can see which errors appear most often, so they know what to work on.

**UI overview**

*Screen: Collections*
- **Top:** four large tabs: Vocabulary, Phrases, Grammar, My mistakes.
- **Main area:** a list of items. Each row shows the item, its meaning or correction, a **star**, and a small status label ("Learned" or "Still difficult").
- **Item detail:** opens as a panel with the full explanation, an example, a notes box, status buttons, and a **See original session** link.
- **Sorting and filtering:** a small control to show only starred items, only difficult items, or the newest first.
- **My mistakes tab:** items are grouped, with the most frequent errors at the top and a counter such as "Seen 5 times."

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

Turns saved material into short exercises such as flashcards and mistake-fixing, and brings harder items back more often.

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
1. From the review screen, the learner taps **"Custom review."**
2. They pick a topic, a collection, or "starred items only."
3. They choose how many items they want, then tap **"Start."**

**UI overview**

*Screen: Review Home*
- **Top card:** today's suggested review ("5 minutes · 8 items") with a large **Start review** button.
- **Below:** a **Custom review** option and a short list of recent review results.

*Screen: Exercise*
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

Shows streaks, learning totals, and weekly summaries so the learner can see how they are improving.

**What the learner can see**
- Number of sessions and practice days.
- A **streak** of consecutive practice days.
- How many words, phrases, and grammar points have been learned.
- **Mistakes reduced over time**, showing that repeated errors are fading.
- A **weekly summary**.

**User flow**
1. The learner taps **"Progress"** in the main menu.
2. The top of the screen shows the current streak and total practice days.
3. Below, they see counts for sessions completed, words learned, phrases learned, and grammar points learned.
4. A simple chart shows how often each common mistake appeared over recent weeks, so the learner can see which ones are fading.
5. At the end of each week, a summary appears, for example: "This week you learned 18 new words and fixed 6 recurring mistakes."
6. A short encouraging message highlights one win, for example "Your longest streak so far."

**UI overview**

*Screen: Progress*
- **Top row:** large friendly numbers for the current **streak** and total practice days.
- **Stat tiles:** small cards for sessions, words, phrases, and grammar points learned.
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

Brings the learner's existing spreadsheet records into the app so past work is not lost.

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

*Screen: Import (inside Settings)*
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

Lets the learner download all of their data at any time as a personal backup.

**What the learner can do**
- Download all their data at any time in a common format that opens in a spreadsheet.
- Use the download as a personal backup.

**User flow**
1. The learner goes to **"Settings"** and taps **"Export."**
2. They choose what to export: everything, or only certain collections.
3. They tap **"Download."**
4. The file saves to their device, and a message confirms it is ready to open in a spreadsheet.

**UI overview**

*Screen: Export (inside Settings)*
- **Simple panel** with a short explanation: "Your data is yours. Download it any time."
- **Choices:** checkboxes for what to include (all sessions, vocabulary, phrases, grammar, mistakes).
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

| Technology | Role in the app |
|---|---|
| **Laravel** | The backend. Handles saving sessions, collections, and progress, as well as importing and exporting data. |
| **Inertia** | The bridge between backend and frontend. Lets the app feel like a fast, smooth single-page app without a separate API. |
| **Vue** | The frontend. Builds the interactive screens, such as the session editor, review exercises, and progress views. |
| **Tailwind CSS** | Styling. Gives the app a clean, consistent, responsive look on phone, tablet, and computer. |
| **shadcn-vue** | Free, ready-made interface pieces such as buttons, cards, tabs, dialogs, and forms. Built on Tailwind, so they can be restyled freely to fit the app. |

---

## Not Included in the First Version

- Any built-in AI or automatic tutor. Practice conversations happen elsewhere.
- Accounts for several people. The first version is for one learner.

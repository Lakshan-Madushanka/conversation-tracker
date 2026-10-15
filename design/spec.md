# Spoken English Practice Companion: Product Spec

## 1. Overview

A personal web app that helps a learner **practice spoken English, keep a record of every practice session, and revisit mistakes and new vocabulary until they stick.**

Today, the learner practices by chatting with an AI tutor and then copies the results into spreadsheets. This app replaces that manual work with a single, organized, interactive place where practice is saved automatically and review is built in.

**Decision for this version:** the app contains **no AI**. The learner keeps practicing with the AI tutor in its own chat, then **copies and pastes** the questions, answers, and feedback into the app. The app's job is to store, organize, and help review that content.

## 2. The Problem

- Practice conversations with an AI tutor are valuable, but they disappear into a long chat history.
- Saving them in spreadsheets is slow, messy, and boring to review.
- Mistakes get repeated because there is no easy way to go back and practice them again.
- New words and phrases are learned once and forgotten.

## 3. Who It Is For

A learner who wants to improve spoken English and is comfortable practicing on their own, one person using the app for their own learning. (Sharing with others may come later.)

## 4. Goals

1. Make it effortless to **record** a practice session.
2. Keep everything **organized and searchable**.
3. Make **re-practicing** easy and enjoyable, not like homework.
4. Help the learner **see progress** over time and stay motivated.

**Not goals (for the first version):**
- Replacing the AI tutor. The app is the learner's notebook and revision coach. The live conversation with the tutor happens elsewhere.
- Any automatic AI features. Everything is entered by the learner through copy and paste.

## 5. How Practice Works Today (the flow we are preserving)

1. The learner and the tutor pick a **topic** and start talking.
2. The tutor asks a **question**.
3. The learner **answers** it.
4. The tutor gives **feedback**:
   - the **mistakes** the learner made, with corrections
   - **new words** and **phrases** worth learning
   - **grammar points** to remember
   - a **more natural version** of the learner's answer
5. The conversation continues with the next question.

The app captures all of this, step by step, in a clean and structured way.

## 6. Key Screens

| Screen | Purpose |
|---|---|
| **Home / Dashboard** | Today's review suggestion, streak, quick "Start new session" button, recent sessions |
| **New Session** | Choose a topic and record questions, answers, and feedback |
| **Session Detail** | Read back one full session in a clear, easy-to-scan layout |
| **Library** | Browse and search all past sessions |
| **Collections** | Vocabulary, phrases, grammar notes, and common mistakes |
| **Review** | Flashcards and exercises for re-practice |
| **Progress** | Charts and summaries of improvement |
| **Settings** | Preferences, import, and export |

## 7. Experience Principles

- **Simple first.** A learner should be able to record a session in a couple of minutes.
- **Calm and encouraging.** Mistakes are shown as learning opportunities, never as failures.
- **Quick to use on any device.** Phone, tablet, or computer, since practice can happen anywhere.
- **Distraction-free.** Clean layout, readable text, no clutter.
- **Rewarding.** Small wins such as streaks and progress summaries keep the learner coming back.

## 8. Example User Journey

1. Sara finishes a practice chat about "Ordering at a restaurant."
2. She opens the app and creates a session with that topic.
3. For each question, she enters her answer and the tutor's feedback.
4. The app adds the new words and phrases to her collections.
5. The next morning, the Home screen suggests a 5-minute review: three flashcards, two mistake-fixing exercises, and one "say it again" question.
6. She completes it, rates the items, and her streak grows to 4 days.
7. At the end of the week, she sees that she has stopped making a mistake she repeated in earlier sessions.

## 9. Success Measures

The app is successful if the learner:
- Records sessions regularly, without feeling it is a chore.
- Practices review several times a week.
- Repeats the same mistakes less often over time.
- Remembers more of the new words and phrases they have learned.
- Finds copy and paste fast enough that recording a session takes only a few minutes.
- Stops needing spreadsheets.

## 10. Decisions Made

- Feedback is entered by **manual copy and paste**; no AI is built into the app.
- The app is for **one learner** (personal use) in the first version.

## 11. Open Questions

1. How is the tutor's reply usually formatted? If it is consistent (for example, always the same headings), the paste boxes can mirror it exactly and save the learner effort.
2. How important is recording and replaying the learner's own voice in the first version?
3. Which matters more at launch: strong review exercises or detailed progress reports?
4. Should the app work without an internet connection?

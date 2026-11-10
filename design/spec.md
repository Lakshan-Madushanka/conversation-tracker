# Spoken English Practice Companion: Product Spec

## 1. Overview

A personal web app that helps a learner **practice spoken English, record practice sessions, collect language from everyday reading and listening, and revisit it until it sticks.**

The learner practices by chatting with an AI tutor and copies the results into the app instead of spreadsheets. They can also create **Notebooks** to save vocabulary, phrases, and grammar notes from books, videos, and other sources without inventing a conversation session. Both paths provide automatically saved material for review.

**Decision for this version:** the app contains **no AI**. Tutor conversations happen elsewhere; the learner copies and pastes their questions, answers, and feedback. Notebook entries are written or pasted by the learner. The app stores, organizes, and helps review that content; it does not generate meanings or grade answers.

This is a product specification, including the planned [Notebook extension](feature-Notebooks.md), not an implementation or test report.

## 2. The Problem

- Practice conversations with an AI tutor are valuable, but they disappear into a long chat history.
- Saving them in spreadsheets is slow, messy, and boring to review.
- Mistakes get repeated because there is no easy way to go back and practice them again.
- New words and phrases are learned once and forgotten.
- Useful language from books or videos needs somewhere to live without recording a fictional session.
- The same word can have different meanings in different contexts; combining every occurrence into one item can replace the meaning the learner intended to practise.

## 3. Who It Is For

A learner who wants to improve spoken English and is comfortable practicing on their own, one person using the app for their own learning. (Sharing with others may come later.)

## 4. Goals

1. Make it effortless to **record** a practice session or save language in a notebook.
2. Keep everything **organized and searchable**, including notebook entry content.
3. Make **re-practicing** easy and enjoyable, without mixing meanings across notebook and session sources.
4. Help the learner **see progress** over time and stay motivated, while distinguishing collected material from actual practice.

**Not goals (for the first version):**

- Replacing the AI tutor. The app is the learner's notebook and revision coach. The live conversation with the tutor happens elsewhere.
- Any automatic AI features. Everything is written or pasted by the learner.

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

The app captures all of this, step by step, in a clean and structured way. Finished sessions that are not archived feed Collections and Review; the existing session grouping rules remain unchanged.

### Language from outside conversations

The learner can instead create a **Notebook** with a name, one shared topic (General by default), and an optional rich-text description. After creation, one autosaving board provides Vocabulary, Phrases, and Grammar notes, without questions, answers, mistakes, natural versions, or a session finish step.

Saved notebook items feed Collections and, when eligible, daily/Quick Review. Each notebook is its own word/phrase scope: its “bank” stays separate from a session's “bank” and another notebook's “bank”. Comma-separated meanings in one word's meaning field remain one item; grammar notes are individual items. Stars, statuses, personal notes, and daily-review schedules are shared only for that scoped item.

The explicit **Create review and practise** action creates a new saved custom review and marks it Finished as the learner's readiness choice. It requires 1–50 distinct selected items and at least one eligible item; above 50 the learner selects a smaller set, and above the recommended 20 the existing warning applies. Later notebook additions do not join that review automatically. Included source edits affect future attempts, while started attempts keep snapshots.

## 6. Key Screens

| Screen               | Purpose                                                                                                                                     |
| -------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| **Home / Dashboard** | Today's review suggestion from eligible session and notebook items, session-based streak, quick "Start new session" button, recent sessions |
| **New Session**      | Choose a topic and record questions, answers, and feedback                                                                                  |
| **Session Detail**   | Read back one full session in a clear, easy-to-scan layout                                                                                  |
| **Library**          | Browse and search past sessions only                                                                                                        |
| **Notebooks**        | Create, search, and filter named notebooks by their shared topics and content                                                               |
| **Notebook**         | Edit name, topic, rich description, and one autosaving entry board; create a review or delete with an impact warning                        |
| **Collections**      | Combined study index with All/Sessions/Notebooks, notebook and topic filters, scoped marks, and source-specific panels                      |
| **Review**           | Daily and Quick review exercises using eligible content from both sources                                                                   |
| **Custom reviews**   | Saved practice sets with fixed membership, repeatable attempts, and snapshot history                                                        |
| **Progress**         | Session-derived totals and streaks; planned daily/Quick review-activity reporting with measures still to be defined                         |
| **Settings**         | Preferences, import, and export                                                                                                             |

## 7. Experience Principles

- **Simple first.** A learner should be able to record a session in a couple of minutes.
- **Calm and encouraging.** Mistakes are shown as learning opportunities, never as failures.
- **Quick to use on any device.** Phone, tablet, or computer, since practice can happen anywhere.
- **Distraction-free.** Clean layout, readable text, no clutter.
- **Rewarding.** Small wins such as streaks and progress summaries keep the learner coming back.

## 8. Example User Journeys

### From a conversation to daily review

1. The learner finishes a practice chat about "Ordering at a restaurant."
2. They create a session with that topic and enter the questions, answers, and tutor feedback.
3. They mark the session finished. Its items enter Collections, and the finished session contributes to session-based practice days and streaks.
4. The next morning, Home suggests a five-minute review of eligible items, including words and mistakes from the session.
5. They complete it and rate the items; difficult items return sooner. Reviewing itself does not extend the session-based streak.
6. Later summaries help them see which material is becoming easier, without automatically changing their Learned labels.

### From reading to notebook practice

1. While reading, the learner creates Nature reading, chooses a shared topic, and writes a formatted chapter description.
2. They add vocabulary, phrases, and grammar notes to its one autosaving board. One word can have comma-separated meanings in its meaning field and still counts as one item.
3. In Collections, they choose Notebooks and Nature reading. The notebook's “bank” appears separately from the session word, with its own meaning and marks.
4. They choose **Create review and practise**. A set over 50 items opens selection; a valid set with at least one eligible item can start, after the size warning above 20 where needed.
5. A later word added to the notebook appears in Collections and can feed daily Review, but does not join the saved custom review automatically.
6. Editing an included meaning affects future custom attempts, not the snapshots of attempts already started. Custom attempts stay separate from daily schedules and Progress.

## 9. Success Measures

The app is successful if the learner:

- Records sessions regularly and captures useful language in notebooks without unnecessary form-filling.
- Practices review several times a week.
- Repeats the same mistakes less often over time.
- Remembers more of the new words and phrases they have learned.
- Finds copy and paste fast enough that recording a session takes only a few minutes.
- Stops needing spreadsheets.

## 10. Decisions Made

- Session feedback is entered by **manual copy and paste**; notebook content is written or pasted. No AI is built into the app.
- The app is for **one learner** (personal use) in the first version.
- Longer text boxes, including notebook descriptions and grammar notes, use the shared **simple rich text editor** with server-side sanitisation and plain-text search projections.
- **Notebooks** are named editable containers, distinct from **Collections**, the combined study index. The Library remains session-only.
- Notebooks and sessions share topics. Renaming a topic affects all its references; deleting one requires no notebook or session references, including empty notebooks and unfinished/archived sessions. Source deletion does not automatically delete its topic.
- Notebook word/phrase identities and marks stay scoped to their notebook. Several comma-separated meanings remain one item; identical grammar notes remain separate items.
- Notebooks use hard deletion with an impact preview and explicit acknowledgement, never archiving. Other scopes are unaffected, and existing custom-attempt snapshots survive source deletion.
- Saved custom-review membership does not track later notebook additions. The 50-item limit counts every held scoped item, including ineligible items; a started attempt uses eligible members and preserves its snapshot.
- Daily/Quick notebook practice belongs in planned review-activity reporting. Merely collecting entries does not prove learning or create finished sessions. Exact Progress measures remain open; session-derived totals, practice days, and streaks keep their existing definitions.
- Saved custom-review attempts do not change marks, daily schedules, recent daily/Quick results, streaks, or Progress.

## 11. Open Questions

1. How is the tutor's reply usually formatted? If it is consistent (for example, always the same headings), the paste boxes can mirror it exactly and save the learner effort.
2. How important is recording and replaying the learner's own voice in the first version?
3. Which matters more at launch: strong review exercises or detailed progress reports?
4. Should the app work without an internet connection?
5. If the learner pastes raw markdown symbols as plain text, should the app convert them into formatting?
6. Which daily/Quick review-activity measures should Progress show for both notebook and session sources, and how should repeated practice and source deletion affect them? Do not silently revise session streaks or include saved custom attempts.
7. Before implementing Notebooks, resolve the [Notebook open decisions](feature-Notebooks.md#open-questions): parent representation, mixed-source date/frequency presentation, duplicate-row content resolution, metadata limits and the General default's lifecycle, and export/backup delivery. These remain questions, not approved implementation choices.

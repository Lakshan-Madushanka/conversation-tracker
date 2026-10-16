# Practice sessions (copy and paste entry)

## Summary

Practice sessions are how everything gets into the app. After a conversation with the AI tutor, the learner opens the app, picks a topic, and copies and pastes the tutor's questions, their own answers, and the tutor's feedback (mistakes, vocabulary, phrases, grammar notes, and a more natural version) into clearly separated boxes. The app saves the work automatically and keeps it editable. Longer text boxes use a simple rich text editor, so formatting from the tutor's reply (such as bold corrections and lists) is kept.

This feature has to be fast and low-effort. If recording a session feels like a chore, the learner will go back to spreadsheets.

### Assumptions

- The app is for **one learner** (personal use) in this version.
- The app contains **no AI**. The live conversation with the tutor happens elsewhere; everything enters the app through manual copy and paste.
- The learner needs an internet connection to use the app (offline use is an open question in the spec, so it is not assumed to be supported).
- Each question has the same set of feedback boxes: mistakes and corrections, new vocabulary, new phrases and expressions, grammar notes, and a more natural version.
- A topic is a short label (for example "Ordering at a restaurant") that can be reused across sessions.
- Finishing a session does not lock it. Sessions stay editable afterward.
- Pasted text may be messy (extra spaces, bullets, numbering, blank lines). The learner is expected to be able to clean it up.
- Longer free-text fields (the tutor's question, the learner's answer, the more natural version, and grammar notes) use a **rich text (WYSIWYG) editor**. Short fields (topic and the mistakes, vocabulary, and phrases entries) stay plain text.
- Text copied from an AI chat often carries formatting such as bold and lists. Keeping it preserves meaning, since tutors often bold the corrections.
- Grammar notes are explanations that can run over several lines, so each grammar note is one formatted entry rather than one line of a pasted list.

### Dependencies

- **Base app layout:** the app shell, navigation, and the focus layout used by guided mode.
- **Library of saved sessions:** where saved sessions are listed, searched, and reopened. This feature creates the sessions; the library presents them.
- **Personal collections:** vocabulary, phrases, grammar notes, and common mistakes saved here feed the collections automatically.
- **Review and re-practice:** the mistakes, phrases, and natural versions saved here become the material for exercises.
- **Progress and motivation:** session counts and practice days come from sessions recorded here.
- **Import from spreadsheets:** imported records must end up in the same shape as sessions created here, so both are treated identically afterward.

### Related notes

- **Experience principles that apply:** simple first (a session recordable in a couple of minutes), calm and encouraging (mistakes are learning opportunities), works on phone, tablet and computer, and distraction-free.
- **Success measure:** copy and paste is fast enough that recording a session takes only a few minutes.
- **Open question that affects this feature:** if the tutor's reply usually follows consistent headings, the feedback boxes can mirror them exactly to save the learner effort. The tutor's typical reply format is still to be confirmed.
- **Open question about markdown:** if the learner pastes raw markdown symbols as plain text (for example asterisks around a word), should the app convert them to formatting? Not assumed for now.
- **Future ideas to leave room for (not built now):** smart sorting of a whole pasted chat into the right boxes, and recording or playing back the learner's voice on an answer. The structure of a session should not make these hard to add later.
- **Existing visual reference:** the layout mockup shows the New Session screen's general direction (top bar, question cards, colored section tags, collapsed empty boxes), and the focus layout used by guided mode.

## Goals

- Let the learner turn a finished tutor conversation into a saved, organized session in a few minutes.
- Keep every part of the conversation separated by type so it can later be searched, collected, and reviewed.
- Make pasting forgiving: no work is lost, mistakes in pasted text are easy to fix, and lists do not have to be split by hand.
- Offer a guided way to paste a whole chat in order for learners who prefer one step at a time.
- Make the learner confident that nothing they entered will be lost.

## Requirements

### Starting a session

1. The learner can start a new session from the Home screen and from the main navigation.
2. A session must have a topic. The learner can pick an existing topic or type a new one.
3. A new session opens with one empty question ready to fill in.

### Recording questions and answers

4. The learner can add any number of questions to a session.
5. Each question has a place to paste the tutor's question and a place to paste the learner's own answer, both in a rich text editor.
6. The learner can add another question at any time, and questions appear in the order they were added.
7. The learner can remove a question from a session. Removing a question that already has content asks for confirmation.

### Recording the tutor's feedback

8. Under each question, separate boxes exist for: mistakes and corrections, new vocabulary, new phrases and expressions, grammar notes, and a more natural way to say it.
9. Any feedback box can be left empty. Empty boxes do not appear as errors and do not create empty entries.
10. Feedback boxes are visually distinguishable from each other (clear labels, and a color tag per type).
11. Empty feedback boxes stay compact, so a question does not look cluttered.

### Pasting lists

12. For list-type boxes (mistakes, vocabulary, phrases), the learner can paste several items at once, one per line, and each line becomes a separate entry.
13. Blank lines are ignored. Leading bullets, dashes, and numbering are removed from each entry.
14. After pasting, the learner can see the resulting separate entries and can edit, remove, or add entries individually.
15. The "more natural version" box and each grammar note are kept as formatted text and are not split into lines. Pasting into the grammar notes box creates one note, and the learner can add more notes one at a time.

### Saving

16. Work is saved automatically as the learner types or pastes, so nothing is lost if the page is closed or refreshed.
17. A visible "Saved" indicator tells the learner their work is safe, and shows clearly when saving is in progress or has failed.
18. If saving fails, the learner's text stays on screen, a clear message appears, and the app retries or lets the learner retry.

### Finishing a session

19. The learner can finish a session. A confirmation shows how many new words, phrases, mistakes, and grammar notes were saved.
20. A finished session appears in the library and its items appear in the relevant collections.
21. Finishing is not final. The session remains editable.

### Editing afterward

22. The learner can reopen any saved session and edit its topic, questions, answers, and every feedback entry, using the same layout as when recording.
23. Changes made later are reflected in the library, collections, and review material.
24. Imported sessions can be edited the same way as sessions created here.

### Guided mode: "Paste in order"

25. The learner can switch to a guided mode that shows one box at a time in a fixed order: question, answer, mistakes, vocabulary, phrases, grammar notes, natural version.
26. In guided mode, the learner moves forward and back with Next and Back, and can skip any box.
27. Guided mode shows the learner's progress through the current question.
28. At the end of a question, the learner sees the full question on one screen to review before saving, then can add another question or finish.
29. Switching between guided mode and the normal layout never loses entered content.
30. Guided mode uses a distraction-free layout with no main navigation.

### Rich text editing

31. These fields use a rich text (what-you-see-is-what-you-get) editor: the tutor's question, the learner's answer, the more natural version, and each grammar note. The topic and the mistakes, vocabulary, and phrases entries stay plain text.
32. The editor offers a small, simple set of formatting: bold, italic, bulleted list, numbered list, and line breaks.
33. Formatting appears as the learner sees it while typing, with no code or markup visible. Common keyboard shortcuts (such as bold, italic, undo, and redo) work.
34. Pasting formatted text (for example copied from an AI chat) keeps the supported formatting. Unsupported formatting (colors, fonts, sizes, images, tables) is removed so the text stays clean and consistent.
35. Pasting plain text keeps its paragraphs and line breaks.
36. Formatting is saved with the content and looks the same when reopened, when read in a session, in collections, and when shown in review.
37. Searching, counting, and review exercises use the words only and ignore formatting.
38. A rich text field that contains only blank space or empty formatting is treated as empty.
39. The editor works on phone, tablet, and computer, with a compact, easy-to-tap toolbar, and can be used with a keyboard and a screen reader.

### Edge cases

- **Finishing with nothing entered:** a session with no questions, or only empty questions, cannot be finished without a warning; the learner can fix it or discard the session.
- **Leaving unsaved or unfinished work:** closing the page, refreshing, or navigating away does not lose entered content. A session that was started but not finished is kept as an unfinished session the learner can continue.
- **Very long pasted text:** long answers or feedback are accepted and remain readable and editable.
- **Messy pasted text:** extra spaces, mixed bullet styles, numbering, and trailing line breaks are cleaned. In plain boxes (topic, mistakes, vocabulary, phrases), text copied with formatting is shown as plain text. In rich text fields, supported formatting is kept and everything else is simplified.
- **Duplicate entries:** pasting the same word or phrase twice in one list is allowed but flagged, so the learner can remove it. The same word appearing in different sessions is allowed.
- **A pasted list with one very long line:** treated as a single entry, which the learner can edit.
- **Topic variations:** topics that differ only by capitalization or extra spaces are treated as the same topic, to avoid accidental duplicates.
- **Removing a question or entry by mistake:** destructive actions on content ask for confirmation, or can be undone.
- **Saving problems or lost connection:** text is never discarded while saving is failing, and the learner is told clearly what happened.
- **Two browser tabs open on the same session:** the app should not silently overwrite newer work with older work. At minimum, the learner is warned.
- **Small screens:** pasting, editing, and guided mode all work comfortably on a phone.
- **Only some feedback available:** the tutor may have given only some types of feedback; the app works with any combination.
- **Very large or complicated pasted content:** a big paste from a web page or chat (many sections, tables, images) is accepted and simplified to the supported formatting without freezing the page.
- **Unsafe pasted content:** anything that could run as code or load outside content is removed when pasting, and never saved.
- **Formatting-only changes:** changing only the formatting of text (for example making a word bold) is saved like any other edit.
- **Mixed styles after pasting:** text pasted from several sources looks consistent, with one font and size across the app.
- **Heading-like text:** pasted headings are simplified (for example to bold text), since headings are not part of the supported formatting.
- **Undo and redo:** the learner can undo an accidental paste or formatting change within the editor.
- **Imported or older plain text:** sessions imported from spreadsheets are plain text and display normally in the editor.

## Technical

This describes the intended approach at a high level, using the agreed stack (Laravel, Inertia, Vue, Tailwind, shadcn-vue) plus Tiptap, a free, open-source rich text editor for Vue. It is a plan to refine, not a final design.

1. **Model the content.** Define the core things the app stores: topics, practice sessions, questions within a session (with the learner's answer), and feedback entries attached to each question. Feedback entries have a type (mistake, vocabulary, phrase, grammar note, natural version) so collections, search, and review can use them later. Formatted fields keep their formatting along with a plain-text version used for search, counting, and review.
2. **Create the backend pieces.** Add routes and controller actions to create, load, update, and delete sessions, questions, and entries, with validation for required topics and sensible limits on text length. Clean and check formatted text on the server as well, so only the supported formatting is ever saved.
3. **Create the New Session page.** A Vue page inside the main app layout with a top bar (topic, Saved indicator, Finish button), a stack of question cards, and an Add question button. Use shadcn-vue components for inputs, collapsible sections, buttons, and confirmation dialogs.
4. **Build one reusable rich text editor.** Use Tiptap with a deliberately small feature set (bold, italic, bulleted and numbered lists, line breaks) and a compact toolbar styled with Tailwind and shadcn-vue. Use it for the tutor's question, the learner's answer, the more natural version, and grammar notes, so every later step and feature reuses the same editor and the same paste behavior.
5. **Define paste behavior for the editor.** Keep supported formatting, remove everything else (colors, fonts, sizes, images, tables, anything unsafe), keep paragraphs for plain text, and show a consistent look.
6. **Build reusable pieces.** A question card, a feedback box (with its type label and color tag), a plain list-entry editor, and a Saved indicator, so Edit and Guided modes reuse them.
7. **Add list pasting for plain boxes.** When the learner pastes into the mistakes, vocabulary, or phrases boxes, clean the text (remove bullets, numbering, blank lines, extra spaces) and split it into separate entries the learner can still edit. Grammar notes and the natural version are not split.
8. **Add automatic saving.** Save changes shortly after the learner stops typing, pasting, or formatting, update the Saved indicator, and keep content on screen if saving fails. Save early so an unfinished session is never lost.
9. **Add finishing.** Mark a session as finished and show a confirmation with counts of what was saved. Finished sessions remain editable.
10. **Reuse the screen for editing.** Opening a saved session for editing loads it into the same layout, so there is one entry experience for both new and existing sessions.
11. **Add guided mode.** A separate Vue view using the focus layout that walks through the same fields one at a time, with the same editor, data, and saving as the normal layout.
12. **Show formatted text everywhere it appears.** Read-only views (session reading page, collections, review) display the saved formatting safely, while search and review use the plain-text version.
13. **Connect to other features.** Make sure saved sessions and entries are available to the library, collections, review, and progress features, and that imported sessions use the same structure.
14. **Make it work everywhere.** Test on phone, tablet, and computer; confirm keyboard use, focus states, screen-reader support, and readable text in light and dark mode.

## Implementation Steps

The steps are ordered so each one leaves a usable, reviewable piece of the feature.

### Step 1: Create a session with a topic and one question

**What it delivers:** The learner can start a new session, choose or create a topic, add a question and answer, and save it. This gives the basic storage for sessions, topics, and questions, and a New Session page in the app layout. The question and answer boxes use the rich text editor, which becomes the reusable editor for later steps.

**Tests**

- Starting a new session from Home and from the main navigation opens an empty New Session page with one empty question.
- A session cannot be saved without a topic, and a clear message explains why.
- Choosing an existing topic and typing a new topic both work.
- Topics that differ only by capitalization or spacing are treated as the same topic.
- A question and answer entered and saved are still there after reloading the page.
- Bold, italic, bulleted lists, and numbered lists applied in the question and answer are saved and look the same after reloading.
- Pasting formatted text (for example from an AI chat) keeps the supported formatting and removes colors, fonts, sizes, images, and tables.
- Pasting plain text keeps its paragraphs and line breaks.
- Pasted content that could run as code is removed and never saved.
- Keyboard shortcuts for bold, italic, undo, and redo work, and the toolbar can be used with the keyboard.
- A field containing only blank space or empty formatting is treated as empty.
- Pasting a very long question or answer is accepted and stays readable.
- The page is usable on a phone-sized screen.

### Step 2: Multiple questions and feedback boxes

**What it delivers:** Each session can contain many questions, and each question has the five feedback boxes (mistakes, vocabulary, phrases, grammar notes, natural version) with labels and color tags. Empty boxes stay compact. The more natural version and grammar notes use the rich text editor.

**Tests**

- Adding a second, third, and tenth question keeps them in the order added.
- Each question shows all five feedback boxes, with clear labels and distinct color tags.
- Empty boxes appear compact and do not show errors.
- Leaving every feedback box empty for a question still saves the question and answer.
- Any combination of filled and empty boxes saves and reloads correctly.
- The more natural version and each grammar note keep their formatting when saved and reloaded.
- An empty formatted field counts as empty and does not create an empty entry.
- Removing an empty question happens immediately; removing a question with content asks for confirmation.
- Cancelling the removal confirmation keeps the question and all its content.

### Step 3: Automatic saving and the Saved indicator

**What it delivers:** Everything the learner types or pastes is saved automatically, with a visible Saved indicator and safe handling when saving fails. Unfinished sessions are kept.

**Tests**

- Typing or pasting into any box is saved without pressing a button, and the indicator moves from "Saving" to "Saved."
- Refreshing the page or closing and reopening the app restores everything entered, including unfinished sessions.
- If saving fails (for example, a lost connection), the text stays on screen and a clear message is shown.
- When the connection returns, saving resumes without the learner re-entering anything.
- Rapid typing and pasting do not cause duplicated or missing content.
- Navigating away mid-session never loses entered content.
- Opening the same session in two tabs warns the learner rather than silently overwriting newer work.

### Step 4: Pasting lists into separate entries

**What it delivers:** For mistakes, vocabulary, and phrases, pasting several lines creates separate, individually editable entries. Grammar notes become one formatted note per paste instead of being split by line. Pasted text is cleaned automatically.

**Tests**

- Pasting ten lines into vocabulary creates ten separate entries.
- Blank lines are ignored.
- Bullets, dashes, and numbering at the start of lines are removed.
- Extra spaces and trailing line breaks are removed.
- In the plain list boxes, text copied with formatting is shown as plain text.
- A single very long line becomes one entry that can be edited.
- Pasting the same word twice in one list flags the duplicate so it can be removed.
- Each entry can be edited, removed, or added individually after pasting.
- The natural version box keeps pasted text as one formatted piece, including line breaks.
- Pasting several lines into grammar notes creates one note with its formatting, and further notes can be added one at a time.
- The same behavior works when pasting on a phone.

### Step 5: Finishing a session

**What it delivers:** The learner can finish a session and see a confirmation showing what was saved. Finished sessions appear in the library, and their items become available to collections, review, and progress.

**Tests**

- Finishing a session with content shows counts of new words, phrases, mistakes, and grammar notes that match what was entered.
- Finishing a session with no questions, or only empty questions, shows a warning and does not finish silently.
- The learner can discard an empty session instead of finishing it.
- A finished session appears in the library with the correct topic, date, and counts.
- The session's entries become available in the relevant collections.
- Finishing is not final: the session can be reopened and the learner can add more content.
- Unfinished sessions are clearly distinguishable from finished ones and can be continued.

### Step 6: Editing saved sessions

**What it delivers:** Any saved session, including imported ones, can be reopened in the same layout used for recording, with all fields editable.

**Tests**

- Opening a saved session shows the same layout as recording, with all existing content filled in.
- The topic, questions, answers, and every feedback entry can be edited.
- Entries can be added, edited, and removed after the session was finished.
- Changes appear in the library, collections, and review material.
- Formatted text reopens with its formatting, and changes to formatting are saved.
- Edits are saved automatically with the same Saved indicator.
- Sessions created by spreadsheet import can be edited the same way.
- Removing a whole question from a saved session asks for confirmation and can be cancelled.
- The edit experience works on phone, tablet, and computer.

### Step 7: Guided "Paste in order" mode

**What it delivers:** A distraction-free, one-box-at-a-time way to enter a question, using the focus layout.

**Tests**

- The learner can switch into guided mode from a new or existing session.
- Guided mode shows one box at a time in the fixed order: question, answer, mistakes, vocabulary, phrases, grammar notes, natural version.
- Next and Back move through the boxes, and any box can be skipped.
- Progress through the current question is visible.
- At the end of a question, the full question is shown for review before saving.
- After a question, the learner can add another question or finish the session.
- Content entered in guided mode matches what appears in the normal layout, and vice versa.
- Switching modes in the middle of a question loses nothing.
- Lists pasted in guided mode are cleaned and split exactly as in the normal layout.
- Rich text boxes in guided mode use the same editor and formatting as in the normal layout.
- Guided mode shows no main navigation and works comfortably on a phone.

### Step 8: Final polish and quality checks

**What it delivers:** The whole feature is checked end to end for speed, clarity, and accessibility, and the main user journey is confirmed.

**Tests**

- A full session of five questions with all feedback types can be recorded in a few minutes.
- The complete flow works from start to finish: new session, add questions, paste feedback, finish, then see it in the library and collections.
- All text is readable, buttons are easy to tap, and keyboard navigation and focus indicators work.
- Wording is encouraging; mistakes are presented as learning opportunities.
- Empty, loading, and error states look friendly and tell the learner what to do next.
- Light and dark modes both look correct on every screen of this feature, including the editor toolbar and formatted text.
- Formatted text displays correctly in the session reading view, collections, and review.
- No page scrolls sideways at a phone-sized width.
- Every destructive action asks for confirmation or can be undone.

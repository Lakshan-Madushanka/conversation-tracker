# Practice Sessions Specification

## Purpose

Recording and reading back a practice session: its topic, the learner's questions and answers, the structured feedback boxes attached to each question, and the question-level overall feedback that sits beside them.

## Requirements

### Requirement: A question can hold overall feedback

Each question in a practice session SHALL hold an optional overall feedback value. Overall feedback is formatted prose about the whole answer, kept separately from the structured feedback boxes (mistakes, vocabulary, phrases, grammar notes, natural version) and never stored as one of their entries.

#### Scenario: A saved question returns its overall feedback

- **WHEN** a session is opened in the editor or on the reading page
- **THEN** each question carries its overall feedback as formatted text, and a question that never had any carries an empty value

#### Scenario: Overall feedback is not a feedback entry

- **WHEN** a question stores overall feedback
- **THEN** its feedback boxes hold no additional entry for it, and no collection item or review item is created from it

### Requirement: Overall feedback is saved with the question

The system SHALL accept overall feedback when a session is created, fully saved, or autosaved, and MUST clean it with the same rich-text rules as the question and answer (bold, italic, lists, line breaks only). An empty or formatting-only value SHALL be stored as empty. The stored value MUST NOT exceed the session's content length limit.

#### Scenario: Formatted overall feedback is cleaned and kept

- **WHEN** the learner saves a question with overall feedback containing bold and italic text
- **THEN** the stored markup keeps only the supported formatting, and the question returns the same formatted value when reopened

#### Scenario: Unsafe markup is removed

- **WHEN** a save contains a script, an image, or other unsupported markup in the overall feedback
- **THEN** the unsupported markup is dropped and only its readable words and supported formatting are stored

#### Scenario: Blank overall feedback saves as empty

- **WHEN** the learner saves a question whose overall feedback holds only whitespace or empty formatting
- **THEN** the question is stored with no overall feedback and no validation error

#### Scenario: Overlong overall feedback is refused

- **WHEN** overall feedback exceeds the session's content length limit
- **THEN** the save is refused with a validation message naming the overall feedback field, and nothing is written

### Requirement: The session page shows overall feedback

The session editor SHALL offer an overall feedback field on every question card, and the reading page SHALL show it as a labelled block after the learner's answer and before the structured feedback boxes. Both MUST render only the supported formatting.

#### Scenario: Editing overall feedback

- **WHEN** the learner types overall feedback into a question card
- **THEN** the text is kept with the question's other edits and saved by the same create, save and autosave path

#### Scenario: Reading overall feedback

- **WHEN** a question with overall feedback is read
- **THEN** the reading page shows a labelled block with the formatted text between the answer and the feedback boxes

#### Scenario: No overall feedback

- **WHEN** a question has no overall feedback
- **THEN** neither the editor nor the reading page shows an empty overall feedback block

### Requirement: Overall feedback counts as question content

A question holding overall feedback SHALL be treated as a question with content even when its question, answer, and feedback boxes are empty. It MUST survive finishing a session and MUST appear on the reading page.

#### Scenario: Only overall feedback is filled in

- **WHEN** a session is finished with a question whose only content is overall feedback
- **THEN** that question is kept, and it appears on the reading page

#### Scenario: A card with nothing at all is still empty

- **WHEN** a session is finished with a question that has no question, answer, overall feedback, or feedback entry
- **THEN** that question is removed, as before

#### Scenario: Session totals stay the same

- **WHEN** a session holds overall feedback
- **THEN** the session's word, phrase, mistake, grammar note and question totals are unchanged by it

### Requirement: Overall feedback is searchable

The Library search SHALL match sessions whose question overall feedback holds every search word, and the per-session match list SHALL report such a match as its own kind. Opening that result SHALL take the learner to the overall feedback block. With no content filter active, overall feedback SHALL be searched beside entries, questions, answers and topics; with content types chosen, it SHALL be searched only when Overall feedback is one of them.

#### Scenario: Searching finds overall feedback

- **WHEN** the learner searches for words that appear only in a question's overall feedback
- **THEN** the session is listed, and the matching text is shown in the session's match list

#### Scenario: Opening the result

- **WHEN** the learner opens a search result that names overall feedback
- **THEN** the session page opens at that question and reveals the overall feedback block

#### Scenario: Only the chosen content types are searched

- **WHEN** the learner filters the Library by mistake and searches for a word that appears only in overall feedback
- **THEN** the session is not matched, because only the chosen feedback boxes are searched

#### Scenario: Overall feedback is a chosen content type

- **WHEN** the learner chooses Overall feedback as a content type and searches
- **THEN** overall feedback is searched, and feedback-box entries are not searched unless their own types are chosen too

### Requirement: The Library can filter by overall feedback

The Library's Content filter SHALL offer Overall feedback beside the five feedback boxes. A session SHALL match when at least one of its questions holds overall feedback, even if that question has no feedback entry. Choosing several content options SHALL match a session holding at least one of them.

#### Scenario: Filtering by overall feedback

- **WHEN** the learner chooses Overall feedback in the Content filter
- **THEN** the list shows only sessions holding at least one question with overall feedback

#### Scenario: A question with only overall feedback matches

- **WHEN** a session's only content is a question's overall feedback
- **THEN** choosing Overall feedback lists that session

#### Scenario: Overall feedback combines with a box type

- **WHEN** the learner chooses Overall feedback and Mistakes
- **THEN** the list shows sessions with overall feedback or with a mistake, and no blank-only session

#### Scenario: Removing the choice

- **WHEN** the learner removes the Overall feedback chip
- **THEN** the other content choices stay selected and the list refreshes without it

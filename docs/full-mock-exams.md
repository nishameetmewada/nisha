# NISHA full mock exams

NISHA has two learning paths: the original short practice studio at `practice.php`, and full Academic mock exams at `exams.php`. Full mocks use original content, longer stimuli, varied response controls, section clocks, and an end-of-exam review. They are independent study material rather than official exam papers or secure/proctored assessments.

## IELTS Academic mock 01

| Section | NISHA time budget | Content |
| --- | --- | --- |
| Listening | 30 minutes | Four contextual recordings/scripts, 10 questions each; 40 questions in total |
| Reading | 60 minutes | Three substantial original academic passages; 40 questions |
| Writing | 60 minutes | Task 1 visual/data description (150-word minimum); Task 2 essay (250-word minimum) |
| Speaking | 14 minutes | Part 1 interview; Part 2 cue card with preparation and long turn; Part 3 discussion |

The combined NISHA sequence lasts up to 164 minutes. IELTS speaking is normally scheduled separately from the other sections in real delivery; NISHA puts it in the same practice sequence for convenience. Listening uses a computer-style 30-minute practice budget, without the paper test's separate 10-minute answer-transfer period. These clocks are configured mock budgets, not a claim to reproduce every test centre's arrangements.

Reading/listening include typed form or note completion, matching, multiple choice, and true/false/not-given or yes/no/not-given judgments. You can flag questions and revisit them within the active IELTS section. Once a section is submitted or its clock expires, it is locked.

## PTE Academic mock 01

| Section | NISHA time budget | Items |
| --- | --- | --- |
| Speaking & Writing | 80 minutes | 35 |
| Reading | 25 minutes | 15 |
| Listening | 35 minutes | 18 |

This is a fixed 68-item, 140-minute NISHA mock. Actual PTE question counts and section durations vary. It includes the two formats introduced in 2025: Summarize Group Discussion and Respond to a Situation. PTE navigation moves forward; submitting an item locks it. Relevant speaking and writing tasks include preparation/response guidance or task limits in addition to the section clock.

The 22 section-qualified task formats are:

- Speaking & Writing: Read Aloud, Repeat Sentence, Describe Image, Retell Lecture, Answer Short Question, Summarize Group Discussion, Respond to a Situation, Summarize Written Text, Write Essay.
- Reading: dropdown fill in the blanks, multiple choice with multiple answers, reorder paragraphs, reading fill in the blanks, multiple choice with one answer.
- Listening: Summarize Spoken Text, multiple choice with multiple answers, fill in the blanks, Highlight Correct Summary, multiple choice with one answer, Select Missing Word, Highlight Incorrect Words, Write from Dictation.

The dropdown and text controls are accessible practice alternatives to the proprietary test interface. Audio-based tasks use original lectures, discussions, announcements, and situations. Describing images uses real chart data rendered in the page, with a table alternative.

## Audio, recordings, and assistance

Listening is generated from the supplied original scripts using the browser's speech voices. It is not a collection of professionally recorded official test audio. Check sound before starting; availability and quality depend on the student's browser/device. In exam mode a resource can be played once, and its script stays hidden. Assisted mode makes replay/transcripts available and labels the attempt as assisted, so a device without working speech audio can still use the material. Changing to assistance during a session remains reflected in the report.

Speaking recording is optional. It requires microphone permission in a supporting browser over HTTPS (or localhost in development). Audio stays on the device and is not uploaded to MySQL. Download recordings before leaving the page; they cannot be restored through a reload or account history. A mock can still be completed with self-guided spoken responses when recording is unavailable.

## Saving, resuming, and timing

The runner saves response state in the current browser when local storage is available. This includes written work and is not encrypted storage. Use your own device, explicitly clear/reset an attempt after using a shared device, and do not put sensitive personal details in practice answers. A guest's local attempt is separate from a signed-in student's local attempt. Old sessions expire after 48 hours.

The section clock uses a fixed deadline rather than a count of open-page seconds. Closing or refreshing the page does not pause an active mock. Resume uses the existing deadlines, catches up expired sections, and preserves already entered answers. A reload cannot restore local recording blobs. If storage is blocked or full, the runner warns the student; it should not claim the work is safely saved.

At final submission, the server marks objective responses against its original answer keys. If the connection fails, keep the page open or resume the local attempt and retry submission. A version mismatch after an update is rejected rather than silently grading against new questions; download your retained responses before starting the updated exam. Guests can complete and receive full reviews without MySQL. A signed-in student can save a completed report to MySQL when the new table has been imported. Saved written responses are private to that account. Students can open saved mock reports from their dashboard.

## What the results mean

- IELTS reading and listening show raw correct-answer totals out of 40.
- PTE shows practice points for supported objective tasks. Gap filling gives credit per correct blank; reordering uses correct adjacent pairs; multiple selection and incorrect-word highlighting deduct wrong selections down to zero. The review explains the practice marker.
- Write from Dictation uses a simplified normalized exact-sentence check. It does not implement Pearson's word-level or integrated scoring.
- Spoken responses, essays, and written/spoken summaries are ungraded and include reflection rubrics. A recording or a long response is not evidence of language proficiency.
- Skipped objective questions contribute zero points. A partially completed exam remains labelled incomplete. Assisted attempts are labelled assisted.
- NISHA does not calculate an official IELTS overall band or a PTE score out of 90. Official speaking/writing assessment, examiner delivery, proprietary scoring, and test security are outside this independent mock.

## Upgrade an existing Hostinger installation

1. Back up your existing website and database.
2. Upload the updated project files to `public_html`, preserving your existing private `config/config.php`. Do not upload the development `.tools` directory, test accounts, or a local database.
3. In phpMyAdmin, select your existing database and import **`database/migrations/002_full_exams.sql`**. It creates the new `exam_runs` table without replacing `users` or `attempts`. Importing `database/schema.sql` is also safe for the supplied schema because table creation uses `IF NOT EXISTS`; no existing records are removed.
4. Visit `exams.php`, complete a sample attempt, and check both guest marking and signed-in report saving. Verify that an account cannot open another account's saved result.
5. Keep HTTPS enabled for microphone support and secure account sessions.

The new migration is needed only for saving full mock history. Without it the server still marks an exam and clearly reports that the result was not saved.

## Maintaining question content

Content lives in `includes/exams/ielts-academic-01.php` and `includes/exams/pte-academic-01.php`. Add a new exam id/version when changing question structure so existing local answers are not attached to a different question set. The public data serializer removes answer keys, explanations, and corrected transcripts. Those become available through the marking endpoint after a submission. These are learning exercises; this is not a proctored-test security boundary.

Use original material, check every answer against its source passage/script, and keep word-limit instructions compatible with accepted answers. The content contract is in `docs/exam-contract.md`. Run the integrity suite described in `tests/README.md` and test the affected controls in a browser before publishing content updates.

Current arrangements should always be checked against the examination providers before booking a real test:

- IELTS Academic: https://ielts.org/take-a-test/test-types/ielts-academic-test
- PTE Academic: https://www.pearsonpte.com/pte-academic/test-format

Official pages could not be retrieved from the development environment during this update. Configured NISHA timings are documented above rather than represented as a live verification of provider specifications.

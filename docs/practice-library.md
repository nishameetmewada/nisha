# NISHA practice library

Choose a course at `practice.php`. Search by question type, filter by skill, and launch an untimed exercise, a timed sectional test or a complete mock. The library lists actual runnable content, with no empty or locked placeholder modules.

## Shipped coverage

| Course | Guided modules | Timed sections | Full mocks |
| --- | ---: | ---: | ---: |
| IELTS Academic | 42 | 4 | 1 |
| IELTS General Training | 38 | 4 | 1 |
| PTE Academic | 22 | 3 | 1 |
| PTE Core | 19 | 3 | 1 |

There are 121 course-specific guided modules, 14 section tests and four full mocks. These totals are launch routes, not 139 independent question banks. Guided and sectional sessions reuse full-mock questions; the IELTS library adds 32 supplemental modules. Related course variants share same-format material. Academic and General Training IELTS listening/speaking are the same bank. PTE Core deliberately reuses several Academic-format exercises alongside original Core summaries, emails and situations.

IELTS module totals include subtopics such as Academic Writing Task 1 visual types, Task 2 essay families and General Training letter registers. They should not be read as a claim that IELTS officially defines 42 or 38 separate question formats. PTE's 22 Academic and 19 Core modules are section-qualified types, so reading and listening versions of multiple choice are counted separately.

## How sessions work

- **Guided practice:** free navigation, no countdown, audio replay/transcripts, optional local recording, and a server answer check for each item. Revising a response clears its old feedback. Finish the session for a complete report and account saving.
- **Sectional mock:** an exact section from the corresponding full mock, with its time budget and navigation rules. Answer review appears after submission.
- **Full mock:** all sections of the selected course. IELTS has 40 listening and 40 reading questions, two writing tasks and three speaking parts. PTE Academic has 68 tasks; PTE Core has 60. Time budgets are configured NISHA learning choices, documented in `full-mock-exams.md`.

Objective marking uses original answer keys, accepted typed variants, per-blank points, adjacent-pair ordering and selection deductions where applicable. Writing/speaking offer self-review and optional explicit ChatGPT API coaching with practice estimates and improvement advice when configured. Writing-only or speaking-only results show “Self-review”. No official band, PTE 90-point score or certification is produced.

Listening uses browser speech from original scripts. Voice quality varies by device; these are not professional official recordings. Microphone recording is optional, stays only in the current tab, and must be downloaded before closing or reloading. Device text autosave lasts 48 hours and is separated between guests and each signed-in account. Timed clocks continue after leaving; guided sessions have no countdown. Account reports contain written responses, answer explanations and original resource context; audio recordings are sent to OpenAI only after an explicit AI review request and are not retained by NISHA. See `ai-coaching.md` for setup, limits and privacy.

An existing installation that already has `exam_runs` needs no new database migration. Otherwise import `database/migrations/002_full_exams.sql`. Preserve the site's existing private configuration when uploading updated files. Production requires PHP with PDO MySQL and the included Apache/LiteSpeed directory protections.

## Reference-site verification

Language Academy was requested as a reference for practice breadth. The development environment returned a proxy policy block for `www.languageacademy.com.au`, so its public pages, current complete catalogue, member-only functionality and official-provider specifications were not retrieved or verified. No claim of exact feature parity or copied test material is made. The implementation uses original NISHA content and the IELTS/PTE format coverage listed below. A saved environment configuration draft adds the reference domains; it must be reviewed, saved and published in environment settings before a direct comparison can be attempted.

## Module inventory

### IELTS Academic

- **Listening:** Flowchart completion (1 item); Form completion (10 items); Map / plan / diagram labelling (3 items); Matching facilities to locations (6 items); Multiple choice (9 items); Multiple-answer multiple choice (1 item); Note completion (15 items); Sentence completion (2 items); Short-answer questions (3 items); Summary completion (1 item); Table completion (1 item).
- **Reading:** Diagram label completion (3 items); Flowchart completion (1 item); Matching features (3 items); Matching headings (10 items); Matching information (4 items); Matching sentence endings (3 items); Multiple choice (4 items); Multiple-answer multiple choice (1 item); Note completion (1 item); Sentence completion (12 items); Short-answer questions (3 items); Summary completion (1 item); Table completion (1 item); True / False / Not Given (5 items); Yes / No / Not Given (5 items).
- **Speaking:** Speaking Part 1 · Introduction and interview (1 item); Speaking Part 2 · Individual long turn (1 item); Speaking Part 3 · Two-way discussion (1 item).
- **Writing:** Academic Writing Task 1 (1 item); Academic Writing Task 1 · Line graph (1 item); Academic Writing Task 1 · Map (1 item); Academic Writing Task 1 · Mixed charts (1 item); Academic Writing Task 1 · Pie chart (1 item); Academic Writing Task 1 · Process (1 item); Academic Writing Task 1 · Table (1 item); Academic Writing Task 2 (1 item); Writing Task 2 · Advantages and disadvantages (1 item); Writing Task 2 · Discussion (1 item); Writing Task 2 · Opinion (1 item); Writing Task 2 · Problem and solution (1 item); Writing Task 2 · Two-part question (1 item).

### IELTS General Training

- **Listening:** Flowchart completion (1 item); Form completion (10 items); Map / plan / diagram labelling (3 items); Matching facilities to locations (6 items); Multiple choice (9 items); Multiple-answer multiple choice (1 item); Note completion (15 items); Sentence completion (2 items); Short-answer questions (3 items); Summary completion (1 item); Table completion (1 item).
- **Reading:** Diagram label completion (3 items); Flowchart completion (1 item); Matching features (9 items); Matching headings (6 items); Matching information (3 items); Matching sentence endings (3 items); Multiple choice (8 items); Multiple-answer multiple choice (1 item); Note completion (1 item); Sentence completion (13 items); Short-answer questions (3 items); Summary completion (1 item); Table completion (1 item); True / False / Not Given (7 items); Yes / No / Not Given (3 items).
- **Speaking:** Speaking Part 1 · Introduction and interview (1 item); Speaking Part 2 · Individual long turn (1 item); Speaking Part 3 · Two-way discussion (1 item).
- **Writing:** General Training Writing Task 1 · Formal letter (1 item); General Training Writing Task 1 · Informal letter (1 item); General Training Writing Task 1 · Semi-formal letter (2 items); General Training Writing Task 2 (1 item); Writing Task 2 · Advantages and disadvantages (1 item); Writing Task 2 · Discussion (1 item); Writing Task 2 · Opinion (1 item); Writing Task 2 · Problem and solution (1 item); Writing Task 2 · Two-part question (1 item).

### PTE Academic

- **Listening:** Highlight Correct Summary (2 items); Highlight Incorrect Words (2 items); Listening: Fill in the Blanks (3 items); Listening: Multiple Choice, Multiple Answers (2 items); Listening: Multiple Choice, Single Answer (2 items); Select Missing Word (2 items); Summarize Spoken Text (1 item); Write from Dictation (4 items).
- **Reading:** Reading & Writing: Fill in the Blanks (5 items); Reading: Fill in the Blanks (4 items); Reading: Multiple Choice, Multiple Answers (2 items); Reading: Multiple Choice, Single Answer (2 items); Reorder Paragraphs (2 items).
- **Speaking:** Answer Short Question (5 items); Describe Image (5 items); Read Aloud (6 items); Repeat Sentence (10 items); Respond to a Situation (2 items); Retell Lecture (2 items); Summarize Group Discussion (2 items).
- **Writing:** Summarize Written Text (2 items); Write Essay (1 item).

### PTE Core

- **Listening:** Highlight Incorrect Words (2 items); Listening: Fill in the Blanks (3 items); Listening: Multiple Choice, Multiple Answers (2 items); Listening: Multiple Choice, Single Answer (2 items); Select Missing Word (1 item); Summarize Spoken Text (1 item); Write from Dictation (4 items).
- **Reading:** Reading & Writing: Fill in the Blanks (5 items); Reading: Fill in the Blanks (4 items); Reading: Multiple Choice, Multiple Answers (2 items); Reading: Multiple Choice, Single Answer (2 items); Reorder Paragraphs (2 items).
- **Speaking:** Answer Short Question (5 items); Describe Image (4 items); Read Aloud (6 items); Repeat Sentence (10 items); Respond to a Situation (2 items).
- **Writing:** Summarize Written Text (1 item); Write Email (2 items).

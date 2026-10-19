# Learning library contract

NISHA offers four course variants: `ielts-academic`, `ielts-general`,
`pte-academic`, and `pte-core`. All material is original, unofficial practice.
Writing and speaking offer self-review plus optional separately requested OpenAI API practice estimates and improvement guidance. No official IELTS/PTE score is generated. Recordings remain in the current tab unless explicitly sent for AI transcription/review. Download them before leaving; NISHA does not retain uploaded audio.

## Canonical content and catalogue

`includes/exam-data.php` exposes `nisha_exams()`: a keyed map containing only
the four full mock IDs `ielts-academic-01`, `ielts-general-01`,
`pte-academic-01`, and `pte-core-01`. Each full mock has `session_type: full`,
`timing_mode: timed`, and its `course_id`. Files that are not yet present are
omitted during development; malformed present files raise an explicit error.

Require `includes/learning-catalog.php` for:

- `nisha_learning_catalog(): array`, a list of courses, each with `id`, `name`,
  `exam`, `description`, `full_exam_id`, `modules`, and `sections`.
- Each module has `id`, `title`, `skill`, `item_count`, `practice_id`, and
  `section_id`. Skills are `listening`, `reading`, `writing`, or `speaking`.
- Each section has its canonical `id`, `title`, `item_count`,
  `duration_seconds`, and generated launch `exam_id`.
- `nisha_resolve_exam(string $id): ?array` returns canonical server content
  for a known launch ID and `null` for unknown IDs.

Module practice IDs are `practice.{course}.{module-id}`. Module IDs contain a
skill prefix and a normalized task title, truncated with a deterministic hash
when necessary. IDs remain at most 64 characters; clients use catalogue launch
IDs instead of constructing them. Timed section IDs are
`section.{course}.{skill}`, using `speaking-writing` for the combined PTE section.

Task-type practice groups reuse all matching questions from the course's full
bank. `includes/practice/ielts-modules.php` may return a list of supplementary
modules with `id`, `title`, `skill`, `course_ids`, `resources`, and `items`.
Matching titles or slugs merge within a skill; identical item IDs are included
once. Conflicting question/resource IDs raise errors. Empty modules are omitted.
Supplementary resources and items use globally unique identifiers.
An item may name `secondary_resource_id` for a diagram/table accompanying its
primary audio or passage; generated sessions retain both referenced resources.

Practice sessions have `session_type: practice`, `timing_mode: untimed`,
`course_id`, `module_id`, and one freely navigable section. Its finite duration
is a suggestion, never a countdown deadline. Sectional sessions use
`session_type: section`, `timing_mode: timed`, and the exact original full-mock
section. Generated content versions hash their complete canonical content.

Candidate JSON must pass through `nisha_public_exam()` or `exam_public_data()`.
These strip `answer`, `explanation`, and `correct_text` recursively. Server
content files remain protected under `includes/.htaccess`.

## Instant practice feedback

`POST api/practice-feedback.php` accepts JSON and the session's
`X-CSRF-Token` header:

```json
{"exam_id":"<catalogue practice_id>","exam_version":"<session version>","item_id":"<canonical item id>","response":"<typed response>"}
```

The response field uses the task's existing response type: string, selected-ID
list, ordered-ID list, or blank-ID object. Only these four top-level keys are
accepted. Requests are limited to 256 KiB; existing per-task text/array limits
and UTF-8 checks apply. The server resolves the item from its own bank, validates
through the normal submission validator, and marks only that one item.

Success is HTTP 200 with `{"success":true,"review":{...}}`. Feedback neither
requires MySQL nor persists a score. Full and sectional IDs return 403; unknown
IDs/types return 422, invalid CSRF returns 403, malformed JSON returns 400, and
stale versions return 409 with `code: exam_version_changed`. Students retain
their local answers when a version changes.

## Completion, results and history

`api/complete-exam.php` accepts any resolver-approved launch ID using the
existing seven-field submission payload: `exam_id`, `exam_version`,
`attempt_id`, `answers`, `elapsed_seconds`, `completed_sections`, and `assisted`.
It uses canonical keys, rejects stale versions, and grades without MySQL.
Authenticated results save idempotently per student/attempt to `exam_runs`;
guest or unavailable-database results remain downloadable and explicitly unsaved.
No schema change is required for these session types.

Results include `session_type`, `timing_mode`, `course_id`, `module_id`, and a
`resources` snapshot. Every review entry includes `options`, `text`, `title`,
`instructions`, `resource_id`, and an answer-free `item_snapshot`, along with
the existing response, correct answer, points, explanation and self-review
rubric. Saved history can therefore render its original wording and options
without depending on today's generated catalogue. Old history entries without
session metadata remain readable as full mocks.

Objective points always include skipped tasks in their denominator. Practice
feedback uses the same simplified marking rules as full mocks. Short answers
normalize case, punctuation and whitespace; word limits still apply. Writing
and speaking points remain null. Saved results are readable only by their owner
through `api/exam-result.php`.

## Optional AI coaching

`api/ai-review.php` is a distinct authenticated/CSRF endpoint for essay/spoken tasks. Canonical resolution and version checking remain server-owned. Timed sessions require a completed owned saved attempt; practice may request coaching before submission. AI estimates never change objective points. SQL migration003 adds owner-specific history and durable daily limits. See `docs/ai-coaching.md` and the `nisha_ai_submit` implementation for payload, scoring, audio validation, idempotency and privacy.

# Full mock exam implementation contract

This is an implementation contract for original NISHA practice, not an official exam specification. Existing six-question studio remains available; full mocks are additional.

## Data

`includes/exams/ielts-academic-01.php` and `includes/exams/pte-academic-01.php` each return an exam array. `includes/exam-data.php` returns keyed array of both exams. Each exam:

```php
[
'id'=>'ielts-academic-01', // or pte-academic-01
'exam'=>'IELTS', // or PTE
'name'=>'IELTS Academic · Full mock 01',
'version'=>'2026-10-original-01',
'description'=>'...',
'format_note'=>'Original, unofficial practice. Configured mock times; official test arrangements can vary.',
'resources'=>[
 'garden-passage'=>['kind'=>'passage','title'=>'Title','paragraphs'=>['...']],
 'booking-audio'=>['kind'=>'audio','title'=>'A booking conversation','audio_script'=>'Original full dialogue/lecture...'],
 'transport-chart'=>['kind'=>'chart','title'=>'Transport to campus','chart'=>['unit'=>'%','categories'=>['2010','2020'],'series'=>[['name'=>'Bus','values'=>[25,40]],['name'=>'Car','values'=>[60,35]]]]],
],
'sections'=>[
 [
  'id'=>'ielts-listening','title'=>'Listening','duration_seconds'=>1800,'navigation'=>'free',
  'instructions'=>'...','items'=>[
   ['id'=>'il01','type'=>'short','task_type'=>'Form completion','title'=>'Questions 1–10 · Part 1',
    'resource_id'=>'booking-audio','prompt'=>'Booking surname: ____','instructions'=>'Write ONE WORD ONLY.',
    'max_words'=>1,'answer'=>['carter'],'explanation'=>'The speaker spells Carter.'],
  ],
 ],
],
]
```

Task types:

- `choice`: `options` is array `[['id'=>'a','text'=>'...'], ...]`; `answer` correct option id string. User response string id. True/False/Not Given, matching headings, and matching features can use choices with an appropriate `task_type`.
- `multi`: same options; `answer` array of correct ids. User response array. `select_count` optional for IELTS exact two choices. Score one point per correct selected option with deductions for incorrect selections down to zero. For IELTS `all_or_nothing` true uses exact match (use this if each item represents a single numbered question; prefer choice to keep counts clear). PTE multiple selections use deductive rule.
- `short`: `answer` array of accepted answer strings; user response string. Case/punctuation/whitespace normalized, max_words enforced. Each one worth 1. Use word/number instructions that actually match accepted answers.
- `gaps`: `text` uses `{{g1}}` placeholders; `blanks` array `[['id'=>'g1','answer'=>['parks'],'options'=>[...optional strings]]]`. User response object keyed blank id. Each blank worth 1; empty incorrect. Options absent -> text input. Explain each with `explanation` at item level; optional blank `explanation`.
- `reorder`: `options` array as choice; `answer` array of correct option ids. User response ordered array. Score adjacent correctly ordered pairs, max n-1. UI offers keyboard-friendly move up/down controls; no drag-only UI.
- `highlight`: `text` contains incorrect transcript; user selects word indices (string indices of whitespace-delimited tokens); `answer` array of index strings. Optional `correct_text` for review. Score correct tokens minus wrong tokens, minimum zero.
- `essay`: writing response string; ungraded. `min_words`, `max_words` optional; `suggested_seconds` optional. Includes writing Task 1 chart resource or Task 2 prompt. `rubric` array of self-review suggestions. PTE SWT is essay type with task_type='Summarize Written Text', max_words=75 min_words=5; require one sentence via guidance.
- `spoken`: ungraded. `prompt`, `cues`, `rubric`, `prep_seconds`, `response_seconds` optional. User response optional note string. Audio-only stimulus in resource for repeat sentence/retell/group discussion; do not render audio scripts on candidate screen in exam mode. Recordings are optional local browser recordings, not uploaded or falsely auto-scored.

Each item MUST have unique id within exam and nonempty explanation for graded tasks. Do not duplicate question IDs. PTE all items have a `task_type` from 22 formats and `type` matching interaction. Resources may include topic-specific chart data for Describe Image. Do not use official/copyrighted exam questions, generated filler, or duplicate questions pretending to be multiple tests.

## Counts / mock timing

IELTS Academic: 4 sections in order listening1800sec/40 items over4audio parts; reading3600sec/40items over3original academic passages (~2150–2750words total); writing3600sec/2tasks with150/250word targets; speaking840sec/3parts (Part1interview, Part2one-min prep+two-min talk, Part3discussion). Reading/listening raw scores each /40. Speaking is separate in real delivery; combined order here is a practice sequence. Original listening scripts should be substantial realistic4parts with instructions/gaps generated spoken pauses if appropriate.

PTE Academic: configured mock budgets speaking-writing4800sec, reading1500sec, listening2100sec (140min in total). Variable real task counts/timings; do not claim one universal official item count. Target original68items: 35S&W (6ReadAloud,10RepeatSentence,5DescribeImage,2RetellLecture,5AnswerShortQuestion,2SummarizeGroupDiscussion,2RespondToSituation,2SummarizeWrittenText,1WriteEssay),15Reading (5Reading&WritingFIB dropdown,2MultipleChoiceMultiple,2ReorderParagraphs,4ReadingFIB,2MultipleChoiceSingle),18Listening(1SummarizeSpokenText,2MultipleChoiceMultiple,3FillInBlanks,2HighlightCorrectSummary,2MultipleChoiceSingle,2SelectMissingWord,2HighlightIncorrectWords,4WriteFromDictation). Use type gaps for multi-blank FIB. WFD type short accepts full sentence variants; objective exact sentence marking is simplified and must be explained. PTE integrated official scoring cannot be reproduced, results report practice points only.

## API contract

`POST api/complete-exam.php`, Content-Type application/json, X-CSRF-Token required. Public grading for guests; signed-in users save full response/result JSON to exam_runs. No audio uploads. Payload:

```
{exam_id:'ielts-academic-01', exam_version:'2026-10-original-01', attempt_id:'uuid-or-hex-unique-id',
 answers:{il01:'carter', pte_gap01:{g1:'parks'}, ptemulti01:['a','c'], ptereorder01:['b','a','d','c']},
 elapsed_seconds:integer, completed_sections:['ielts-listening',...], assisted:false}
```

Return HTTP200:
```
{success:true,saved:true|false,result_id:integer|null,save_message:'...',
 result:{exam_id,name,exam,attempt_id,elapsed_seconds,assisted,completed_sections,
 objective_score,objective_total,answered_items,total_items,
 sections:[{id,title,score,total,answered,total_items,ungraded_items}],
 review:[{id,section_id,type,task_type,prompt,response,correct_answer,score,max_score,explanation,graded:bool, rubric:[]}],
 responses:{...}, grading_note:'Raw practice points only; writing/speaking require self-review.'}}
```
`answered_items` counts nonempty responses (spoken optional note recorded does not invent judged speech). `completed_sections` must match knownsectionids; incomplete allowed but labeled incomplete. Server uses canonical answer keys. Required exam_version must match the canonical version; stale versions are rejected with HTTP409 without discarding client answers. `objective_total` includes all graded tasks even skipped. Never official PTE90 or overallIELTS bands. Save duplicate attempt_id idempotently per student; guest result savedfalse with signup hint. DB absent must still grade successfully and report unsaved. GET `api/exam-result.php?id=...` authenticated and owner-only returns same result envelope.

Data exposed on exams.php must strip `answer` keys, explanations, correct_text recursively from public data. Source answers protected by includes/.htaccess. Grading only at finish (client sends all examresponses to endpoint); savedresultreview exposes keys to owner after submit. Auth uses existing bootstrap. Existing accounts and attempts data remain unchanged; new exam_runs table only via append schema + idempotent migration.

## Runner UX / persistence

exams.php + assets/js/exams.js + assets/css/exams.css own full runner/catalog. Keep NISHA palette & shared header/footer. Clear cards for bothfullmocks with sections/questioncounts/time. Include equipment check, exam vs assisted audio/transcript options, resume/reset with confirmation, section countdown (wall clock), automatic deadline advancement, questionflags/jump forIELTS free navigation, forward-only PTE items, typedanswers/checkboxes/gaps/reorder/highlight/writing wordcounter/recording as above. Section deadline must survive reload; leaving page doesn't pause clock. Saved local state scoped to exam/version with expiry and privacy notice; clear/reset explicit. Recordings local blob only and cannot reliably restore reload; say so clearly. Timer catches up multiple expired sections on reload without resetting each deadline. Never expose answers before finalsubmit. Results include persection rawpoints and answerreviews, writingrubrics/ungraded notes, download JSON/print and saved state. Handle denied mic, missing speechvoices, storagequota and endpoint failures gracefully, retaining completed answers to retry.

## Ownership

Root integrates navigation/landing/quickstudio/docs/package and finaltests. Content agents own only respective includes/exams file. Runner agent owns exams.php/exams.js/exams.css. Backend agent owns exam-data.php loader, exam-grading.php, new APIs, schema+migration+dashboard changes. Coordinate before changing contract.

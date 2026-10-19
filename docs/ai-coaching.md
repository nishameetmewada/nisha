# ChatGPT API coaching on NISHA

NISHA offers optional OpenAI API feedback for every writing and speaking task in its four-course library. The new feature is separate from deterministic reading/listening points. Students choose **Review with AI**; merely typing, playing audio or submitting a mock does not trigger a paid API request.

## Activate it on Hostinger

1. Back up the website and database. Upload the updated files into `public_html`, preserving your existing private `config/config.php` and database values.
2. In phpMyAdmin, select the existing NISHA database and import **`database/migrations/003_ai_reviews.sql`**. If the older `exam_runs` table is missing, import migration 002 first. A new installation can import `database/schema.sql`, which includes all tables without dropping existing data.
3. Create an OpenAI API project/key with API billing enabled. A ChatGPT subscription does not provide API credits. Review the project's permitted models and billing controls/alerts.
4. Add the following entries to your **existing** `config/config.php` return array. Replace the placeholder only in that private server file. Never replace your database entries or publish this file:

   ```php
   'openai_api_key' => 'YOUR_OPENAI_API_KEY',
   'openai_model' => 'gpt-4.1-mini',
   'openai_transcription_model' => 'gpt-4o-mini-transcribe',
   'ai_reviews_per_student_day' => 10,
   'ai_reviews_per_day' => 100,
   ```

   Alternatively set server environment variables `NISHA_OPENAI_API_KEY`, `NISHA_OPENAI_MODEL`, `NISHA_OPENAI_TRANSCRIPTION_MODEL`, `NISHA_AI_STUDENT_DAILY_LIMIT` and `NISHA_AI_DAILY_LIMIT`. Explicit nonempty AI environment variables take precedence over local AI entries. `OPENAI_API_KEY` is also accepted as a fallback; database configuration precedence is unchanged. Clear the API key to disable new AI requests. The models must support their chosen OpenAI endpoint and project permissions.
5. Use PHP 8.2+ with PDO MySQL, fileinfo and cURL, or OpenSSL with HTTPS streams enabled. Allow outbound verified HTTPS to **`api.openai.com`**. Keep certificate verification enabled. The application uses fixed `/v1/responses` and `/v1/audio/transcriptions` URLs and never follows a client-supplied URL or provider redirect.
6. To allow the full five-minute speaking clip, set PHP `upload_max_filesize=12M`, `post_max_size=16M`, memory 128M or greater, and a sufficient web/PHP request timeout (at least 120 seconds). A lower hosting upload limit may reject a long clip; typed transcripts remain available.
7. Sign in as a test student. Open a guided writing task, enter a response and request AI feedback. Check the estimate, criterion feedback, quoted evidence and improvement plan. Try a speaking transcript or a short recording and confirm the reviewed transcript. Open **My AI coaching** on the dashboard, then reopen a saved exam report. Remove the test account when finished.

Keep `.htaccess` directory protection, HTTPS and restricted permissions on the private config. Confirm `/config/config.php` and `/includes/ai-review.php` are forbidden on the live host. No OpenAI key is embedded in HTML/JavaScript or returned in API errors.

## What gets scored

| Work | AI result | Practical limit |
| --- | --- | --- |
| IELTS writing | Estimated 0–9 writing practice band, with task, coherence, vocabulary and grammar criteria | An independent, uncalibrated estimate; no official IELTS overall band |
| PTE writing, summaries and emails | Content/form/vocabulary/grammar practice percentages | No proprietary integrated scoring and no PTE 10–90 conversion |
| Speaking transcript | Content/relevance, vocabulary and grammar practice percentages | Text cannot establish pronunciation, oral fluency, pacing, accent or delivery |
| Speaking recording | OpenAI transcription followed by the same content/language review | Recognition errors may affect feedback; check the transcript. No acoustic pronunciation/delivery score |

The server derives the displayed score from validated criterion values. Out-of-range, missing, duplicate or inconsistent criteria, incomplete provider output and refusals do not produce an invented score. An unassessable response returns no numeric estimate. Guidance includes strengths, specific issues with student evidence where available, suggestions, examples and next practice steps. AI can still make mistakes or misjudge a response; provider quality and score calibration require real student/expert evaluation.

Guided tasks can request coaching during practice. Timed full/sectional mocks expose it after the attempt has finished and saved to the signed-in account. Writing feedback is bound to the original submitted text. Speaking coaching reviews the recording or transcript supplied after the test; NISHA cannot verify that it was the original timed performance. Reflection notes are not treated as spoken answers.

## Audio handling

A student can select a local tab recording, upload a compatible saved clip, or type a transcript. The browser decodes/resamples the selected clip into mono 16 kHz 16-bit PCM WAV before uploading it. The server validates the actual RIFF chunks and sample count and rejects compressed/disguised, corrupt, oversized and longer-than-five-minute uploads **before** a provider request. A client-supplied duration is never trusted. This works on shared PHP hosting without ffmpeg. Audio decoding support depends on the browser; when unavailable use a compatible clip or transcript.

Audio is sent to OpenAI only after the student explicitly requests review. NISHA does not place it in a public uploads folder or retain it in MySQL; PHP's temporary upload is deleted after processing, with request-end cleanup as a fallback. The browser's original local recording is still lost after reload unless downloaded. Written responses, recognised/typed transcripts and completed feedback are saved privately in MySQL and shown in the owner's AI history. The OpenAI Responses request uses `store:false`; OpenAI's own service data-handling/retention policy still applies, so do not promise zero provider retention. Manage the site's privacy notice, account access, retention and backups accordingly.

## Request limits and reliability

Sign-in and CSRF are required. Default limits are 10 provider review attempts per student per UTC day and 100 across the site. SQL row locks reserve both limits together before a provider call, including concurrent requests. One UTC day is captured for each reservation. These are **request counts**, not a money budget. Failed/time-out attempts count conservatively because OpenAI may have processed them; a recording review includes transcription plus the text review. Audio is bounded to five minutes and review output to 4,000 tokens. Monitor actual OpenAI usage and use project billing controls/alerts; changing models changes cost.

A stable review request ID makes retries idempotent. Completed matching responses reuse owner-specific cached feedback without another provider request. Pending duplicate work does not start another call. Expired pending/failed attempts may be retried with another quota reservation. Changed responses/tasks/versions get new review references. Cache matching includes the model, prompt/schema version, task version and exact input; changing daily limits alone does not invalidate a review.

Network, quota, authentication, invalid model output and setup errors retain the student's response and explain the next step. No fallback score is generated. Old exam reports remain readable if the optional AI migration is absent. Saved AI feedback is separate from immutable objective reports and is matched by owner, exam/version, attempt/item and original response. It is never shared between students.

## Verification in this environment

There is no configured API key in the prepared workspace. Live model acceptance, audio recognition quality, API permissions/billing and score accuracy have therefore **not** been tested. Automated tests use explicit CLI-only injected transports or clearly mocked browser responses; production has no mock-provider flag or configurable provider URL. This distinguishes tested request/validation/persistence/UI behaviour from an actual paid OpenAI result.

A cloud configuration draft adds `api.openai.com` and `developers.openai.com` and declares an optional `NISHA_OPENAI_API_KEY` secret requirement. Review/save/publish the environment settings to apply those changes. `OPENAI_` names are reserved by cloud secret metadata, which is why the cloud requirement uses the `NISHA_` alias. Hostinger configuration is independent; cloud draft saving and GitHub publication do not activate or deploy the website.

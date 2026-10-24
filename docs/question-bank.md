# Generated practice and teacher-reviewed publishing

## Generated library

`generated-practice.php` and `generated-mocks.php` provide a separate public generated collection: **30,000 question variants** (7,500 per course) and **3,000 assembled mock variants** (750 per course). The content is produced by deterministic NISHA templates, not by a live model call. Browsing and starting generated practice does not spend OpenAI credit. Optional writing/speaking reviews use the existing AI API and quotas.

These are parameterised variations, not 30,000 independent editorial questions. Topics, question structures and individual questions repeat across variants and assembled papers. The templates currently contain 23,401 distinct normalised question structures across the draft slots. Counts describe available variant slots, not uniqueness, teacher endorsement or official exam equivalence. Listening uses browser speech; generated mock task mixes are learning exercises rather than exact official test replicas.

Generated sessions use separate `xp.` (question) and `xm.` (mock) identifiers and are labelled as **generated, not teacher-reviewed** in catalogues, setup, the exam room and saved results. They resolve without a review-ledger entry. Generated full mocks use the same account requirement and one-free-then-paid access checks as other full mocks; guided generated questions remain free. Existing account/result/payment tables are reused, so no new database migration is needed for this update.

The teacher-reviewed libraries at `question-bank.php` and `mock-library.php` remain separate. Making generated variants public does not create reviews, approve teachers, publish draft identifiers or change reviewed inventory totals.

## Teacher-reviewed collection

The expansion targets are **30,000 reviewed practice questions** and **3,000 reviewed assembled mock versions**. They are not claims about currently available content. Public totals come from the review ledger. There are zero newly reviewed publications until approved teachers actually review and publish them.

The existing four starter mocks and guided exercises remain a separate original NISHA starter library. They are not counted or advertised as teacher-reviewed.

## Drafts and editorial quality

`includes/generated-library.php` provides the drafting pool of 30,000 parameterised question slots across four courses, using 50 original topic cards and 15 evaluation perspectives. The same templates support the separately labelled public generated library. Some tasks reuse source material or question structures. These are **draft variations** for teacher review, not 30,000 independent editorial questions. Structural validation cannot establish correctness, ambiguity, exam suitability or editorial originality.

Teachers should edit or reject repeated or unsuitable drafts. Publishing rejects normalised exact duplicate questions across courses and reordered copies of the same paper. It cannot detect every paraphrased duplicate; that remains part of teacher review. Targets may require teachers to develop further substantive content rather than approve the entire generated pool.

An assembled paper uses a stable selection from the question bank. It has the starter paper's section budgets and task counts, but its generated draft task mix is learning practice rather than a replica of an official test. Each referenced question must be individually reviewed and published. An approved teacher must then review the whole paper for suitability, difficulty, timing and coherence before publishing it. Assembled versions reuse questions and are not independent unseen papers.

## Setup

1. Import `database/migrations/006_content_reviews.sql` after the account, commerce and teacher migrations. `database/schema.sql` includes the full installation schema.
2. Register a real teacher account. The owner must approve its teacher profile in the administration portal. An owner/admin role alone cannot certify teacher-reviewed content.
3. Open `content-review.php`. Ordinary students receive a forbidden page; guests are asked to sign in. Unreviewed `gp.`/`gm.` draft identifiers cannot resolve through the teacher-reviewed catalogue. Separately labelled `xp.`/`xm.` generated variants use the public generated library described above.
4. Choose a draft reference, such as `gp.ielts-academic.00001`. Edit its title, prompt, source, options, accepted answers, explanation and rubric as applicable. Check the actual question and correct answer, add a specific review note, and explicitly certify it.
5. For papers, open a reference such as `gm.ielts-academic.0001`. The queue links every required question. Complete those reviews first, then read the full assembled paper and certify it separately.

Question numbers range from `00001` to `07500` per course; paper numbers range from `0001` to `0750`. Other course identifiers are `ielts-general`, `pte-academic` and `pte-core`.

## Frozen versions and withdrawals

Every successful review records the exact question or paper, correct answers, resources, teacher identity, timestamp, note and content hash in an append-only version record. Public practice serves that frozen reviewed payload rather than regenerating it from the draft templates. Generator changes do not silently replace a reviewed question.

A paper depends on the exact published versions of its questions. Editing or withdrawing a referenced question hides the paper until its current question versions receive a fresh whole-paper review. Revoking a reviewer's approved teacher status also hides that reviewer's publications and dependent papers. Saved student exam reports retain their original snapshots; they do not become a certification of a newer version.

Teachers may withdraw their own publication. The owner may withdraw any publication. Neither a CLI validator nor a release manifest can create a human review or publish a question automatically.

## Verification

Run `php scripts/validate-content-drafts.php --all` to structurally validate all draft slots without publishing or contacting external services. The output distinguishes slot counts from distinct normalised structures.

Run `php tests/content-review-integrity.php --database` for isolated local fixtures covering role checks, draft secrecy, edited answer/source persistence, duplicate rejection, frozen versions, whole-paper source binding and withdrawal dependencies. Test accounts and records are explicitly artificial and cleaned after the run; they do not establish real teacher review or populate public production inventory.

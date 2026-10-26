/** Differential checks against the real PHP marker. No database or network. */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { execFileSync } from 'node:child_process';
import ts from 'typescript';

const mobileRoot = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const root = path.dirname(mobileRoot);
const php = process.env.NISHA_PHP_BIN || (fs.existsSync('/workspace/.tools/bin/php') ? '/workspace/.tools/bin/php' : 'php');
const scratch = fs.mkdtempSync(path.join(os.tmpdir(), 'nisha-offline-'));
let checks = 0;
const ok = (value, message) => { assert(value, message); checks++; };
try {
  execFileSync(php, ['scripts/build-mobile-content.php', '--check'], { cwd: root, stdio: 'pipe' });
  const pack = JSON.parse(fs.readFileSync(path.join(mobileRoot, 'src/data/offline-sessions.json'), 'utf8'));
  let source = fs.readFileSync(path.join(mobileRoot, 'src/offline.ts'), 'utf8');
  source = source.replace("import pack from './data/offline-sessions.json';", `const pack = ${JSON.stringify(pack)};`);
  source = source.replace("from './compat'", "from './compat.mjs'");
  const compatibility = fs.readFileSync(path.join(mobileRoot, 'src/compat.ts'), 'utf8');
  fs.writeFileSync(path.join(scratch, 'compat.mjs'), ts.transpileModule(compatibility, { compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.ESNext } }).outputText);
  const compiled = ts.transpileModule(source, { compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.ESNext } }).outputText;
  const target = path.join(scratch, 'offline.mjs'); fs.writeFileSync(target, compiled);
  const { offlineSessions, listOffline, getOffline, gradeOffline, offlineFeedback, offlineComplete, offlineWordCount } = await import(pathToFileURL(target).href);
  ok(offlineSessions.length === 80 && new Set(offlineSessions.map(exam => exam.id)).size === 80, 'Pack has 80 distinct references.');
  ok(offlineSessions.every(exam => exam.id.startsWith('xp.') && exam.session_type === 'practice' && exam.timing_mode === 'untimed' && exam.teacher_reviewed === false), 'No full mock or teacher-certified content is bundled.');
  for (const [course, count] of Object.entries(pack.courses)) {
    ok(count === 20 && listOffline({ course }).length === 20, 'Each course has 20 actual tasks.');
    for (const [skill, expected] of Object.entries({ reading: 6, listening: 6, writing: 4, speaking: 4 })) ok(listOffline({ course, skill }).length === expected, 'Every course has four-skill coverage.');
  }
  ok(listOffline({ query: 'Re-order paragraphs' }).every(exam => exam.sections[0].items[0].task_type === 'Re-order paragraphs'), 'Search selects genuine matching formats.');
  ok(getOffline('xm.ielts-academic.0001') === undefined && getOffline('not-a-question') === undefined, 'Unknown and full paper IDs are absent.');
  ok(offlineWordCount("A 3.5-year-old student's well-being") === 4, 'Word counting preserves decimal/hyphen/contraction rules.');

  const fixtures = [];
  for (const exam of pack.sessions) {
    const item = exam.sections[0].items[0];
    const right = ['essay', 'spoken'].includes(item.type) ? 'A local test response used to verify ungraded writing and speaking.'
      : item.type === 'gaps' ? Object.fromEntries(item.blanks.map(blank => [blank.id, blank.answer[0]]))
      : item.type === 'short' ? item.answer[0] : item.answer;
    const wrong = ['essay', 'spoken'].includes(item.type) ? ''
      : item.type === 'gaps' ? Object.fromEntries(item.blanks.map(blank => [blank.id, 'not the accepted source word']))
      : item.type === 'choice' ? item.options.find(option => option.id !== item.answer).id
      : item.type === 'short' ? 'not the accepted source word'
      : item.type === 'reorder' ? [...item.answer].reverse()
      : item.type === 'highlight' ? ['0']
      : [item.answer[0], item.options.find(option => !item.answer.includes(option.id)).id];
    for (const answers of [{ [item.id]: right }, { [item.id]: wrong }, {}]) fixtures.push({ id: exam.id, answers });
    if (item.type === 'short') fixtures.push({ id: exam.id, answers: { [item.id]: `  ${item.answer[0].toUpperCase()}!! ` } });
  }
  const expected = JSON.parse(execFileSync(php, ['-r', `
require 'includes/generated-public-library.php';
$fixtures=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);$out=[];
foreach($fixtures as $fixture){
 $exam=nisha_generated_public_resolve_exam($fixture['id']);
 $submission=nisha_validate_exam_submission($exam,['exam_id'=>$exam['id'],'exam_version'=>$exam['version'],'attempt_id'=>'mobile-offline-fixture-0001','answers'=>$fixture['answers'],'elapsed_seconds'=>0,'completed_sections'=>array_column($exam['sections'],'id'),'assisted'=>true]);
 $result=nisha_grade_exam($exam,$submission);
 $out[]=['score'=>$result['objective_score'],'total'=>$result['objective_total'],'answered'=>$result['answered_items'],'review'=>array_map(static fn($review)=>array_intersect_key($review,array_flip(['id','type','score','max_score','correct_answer','graded'])),$result['review'])];
}
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);`], { cwd: root, encoding: 'utf8', input: JSON.stringify(fixtures), maxBuffer: 20 * 1024 * 1024 }));
  for (const [index, fixture] of fixtures.entries()) {
    const exam = getOffline(fixture.id), result = gradeOffline(exam, fixture.answers), reference = expected[index];
    ok(result.objective_score === reference.score && result.objective_total === reference.total && result.answered_items === reference.answered, 'Objective totals match the real PHP marker.');
    const review = result.review.map(item => ({ id:item.id, type:item.type, score:item.score, max_score:item.max_score, correct_answer:item.correct_answer, graded:item.graded }));
    assert.deepEqual(review, reference.review); checks++;
    ok(result.offline === true && result.sync_eligible === false && result.teacher_reviewed === false && result.grading_source === 'offline-device', 'Local scores retain their unverified offline provenance.');
  }
  const first = getOffline(pack.sessions[0].id), question = first.sections[0].items[0];
  const knownAnswer = question.answer;
  offlineSessions[0].sections[0].items[0].answer = 'tampered-local-export';
  ok(offlineFeedback(first, { ...question, answer:'tampered-caller-answer' }, knownAnswer).score === 1, 'Feedback uses the installed canonical key rather than caller metadata.');
  assert.throws(() => gradeOffline({ ...first, id:'xm.ielts-academic.0001' }, {})); checks++;
  assert.throws(() => gradeOffline({ ...first, version:'stale-app-pack' }, {})); checks++;
  assert.throws(() => gradeOffline(first, { unknown:'not a task' })); checks++;
  const multi = pack.sessions.find(exam => exam.sections[0].items[0].type === 'multi');
  const multiItem = multi.sections[0].items[0];
  assert.throws(() => gradeOffline(multi, { [multiItem.id]:[multiItem.answer[0],multiItem.answer[0]] })); checks++;
  const ordered = pack.sessions.find(exam => exam.sections[0].items[0].type === 'reorder');
  const orderedItem = ordered.sections[0].items[0];
  assert.throws(() => gradeOffline(ordered, { [orderedItem.id]:[orderedItem.answer[0]] })); checks++;
  assert.throws(() => gradeOffline(multi, { [multiItem.id]:multiItem.options.map(option => option.id) })); checks++;
  const result = offlineComplete(first, { exam_id:first.id,exam_version:first.version,attempt_id:'local-complete-fixture',answers:{ [question.id]:knownAnswer },completed_sections:[first.sections[0].id],elapsed_seconds:27,assisted:true });
  ok(result.saved === false && result.result_id === null && result.result.attempt_id === 'local-complete-fixture' && result.result.elapsed_seconds === 27 && result.result.incomplete === false, 'Completing offline keeps local attempt metadata without an account save.');
  ok(/device only/.test(result.save_message) && !Object.hasOwn(result, 'mock_access'), 'Offline completion cannot claim a mock grant or consume the free allowance.');
  console.log(`PASS: ${checks} offline-pack differential checks across ${fixtures.length} PHP marker fixtures. No database, provider calls or account saves.`);
} finally {
  fs.rmSync(scratch, { recursive:true, force:true });
}

import {cloneJson,newReference} from './compat';
import pack from './data/offline-sessions.json';
import type { Answers, Answer, Envelope, Exam, Item, Result, Review } from './types';

/** These are free generated practice tasks only. Mock papers never enter this pack. */
const canonical = new Map<string, Exam>((pack.sessions as unknown as Exam[]).map(exam => [exam.id, exam]));
export const offlineSessions: Exam[] = cloneJson(pack.sessions) as unknown as Exam[];
export const offlineDisclosure = pack.disclosure;
export const offlinePackVersion = pack.content_sha256;

export function listOffline(filter: { course?: string; skill?: string; query?: string } = {}): Exam[] {
  const query = (filter.query || '').trim().toLocaleLowerCase('en');
  return offlineSessions.filter(exam => (!filter.course || filter.course === 'all' || exam.course_id === filter.course)
    && (!filter.skill || filter.skill === 'all' || exam.offline_skill === filter.skill)
    && (!query || [exam.name, exam.description, ...exam.sections.flatMap(section => section.items.map(item => `${item.task_type || ''} ${item.title || ''} ${item.prompt || ''}`))].join(' ').toLocaleLowerCase('en').includes(query)));
}

export function getOffline(id: string): Exam | undefined {
  const exam = canonical.get(id);
  return exam ? cloneJson(exam) : undefined;
}

function bundledExam(exam: Exam): Exam {
  const bundled = canonical.get(exam.id);
  if (!bundled || bundled.version !== exam.version || bundled.session_type !== 'practice') {
    throw new Error('This session is not in the installed offline practice pack. Connect to open online practice or mocks.');
  }
  return bundled;
}

export function offlineWordCount(text: string): number {
  return (text.match(/[\p{L}\p{N}]+(?:[’'.\-][\p{L}\p{N}]+)*/gu) || []).length;
}

function normalise(text: string): string {
  // PHP installations can have different optional Unicode extensions. Keep
  // this pack's marker aligned with the PHP runtime that built its fixtures.
  const folded = pack.normalisation.unicode_nfkc ? text.normalize('NFKC') : text;
  const lowered = pack.normalisation.unicode_lowercase ? folded.toLocaleLowerCase('en') : folded.replace(/[A-Z]/g, letter => letter.toLowerCase());
  return lowered.replace(/[^\p{L}\p{N}\s]/gu, ' ').replace(/\s+/gu, ' ').trim();
}

function textCorrect(text: string, expected: unknown, maxWords?: number): boolean {
  if (maxWords !== undefined && offlineWordCount(text) > maxWords) return false;
  const actual = normalise(text);
  return actual !== '' && (Array.isArray(expected) ? expected : [expected]).some(answer => typeof answer === 'string' && normalise(answer) === actual);
}

function responsePresent(response: Answer): boolean {
  if (typeof response === 'string') return response.trim() !== '';
  return Object.values(response).some(value => String(value).trim() !== '');
}

function validatedResponse(item: Item, value: unknown): Answer {
  if (['multi', 'reorder', 'highlight'].includes(item.type)) {
    if (value === undefined) return [];
    if (!Array.isArray(value) || !value.every(entry => typeof entry === 'string') || new Set(value).size !== value.length) throw new Error('Choose each available response at most once.');
    const allowed = item.type === 'highlight'
      ? (item.text || '').trim().split(/\s+/u).map((_, index) => String(index))
      : (item.options || []).map(option => option.id);
    if (value.some(entry => !allowed.includes(entry))) throw new Error('Choose an available response for this task.');
    if (item.type === 'reorder' && value.length !== 0 && value.length !== allowed.length) throw new Error('An ordered answer must include every paragraph exactly once.');
    if (item.type === 'multi' && item.select_count && value.length > item.select_count) throw new Error('Too many answer options were selected.');
    return value;
  }
  if (item.type === 'gaps') {
    if (value === undefined) return {};
    if (!value || Array.isArray(value) || typeof value !== 'object') throw new Error('Enter gap answers by their blank reference.');
    const response = value as Record<string, unknown>;
    for (const [id, answer] of Object.entries(response)) {
      const blank = item.blanks?.find(entry => entry.id === id);
      if (!blank || typeof answer !== 'string' || answer.length > 1000 || (answer !== '' && blank.options?.length && !blank.options.includes(answer))) throw new Error('Enter a valid response for each gap.');
    }
    return response as Record<string, string>;
  }
  if (value === undefined) return '';
  if (typeof value !== 'string' || value.includes('\0')) throw new Error('Enter a text response for this task.');
  if (item.type === 'choice' && value !== '' && !(item.options || []).some(option => option.id === value)) throw new Error('Choose one of the available options.');
  const limit = item.type === 'essay' ? 50000 : item.type === 'spoken' ? 10000 : 2000;
  if (new TextEncoder().encode(value).length > limit) throw new Error('This response is too long for the task.');
  return value;
}

function answerFree(value: unknown): unknown {
  if (Array.isArray(value)) return value.map(answerFree);
  if (value && typeof value === 'object') return Object.fromEntries(Object.entries(value).filter(([key]) => !['answer', 'explanation', 'correct_text'].includes(key)).map(([key, part]) => [key, answerFree(part)]));
  return value;
}

function mark(item: Item, sectionId: string, value: unknown): Review {
  const response = validatedResponse(item, value);
  const graded = !['essay', 'spoken'].includes(item.type);
  let score: number | null = graded ? 0 : null;
  let maximum: number | null = graded ? 1 : null;
  let correct: unknown = graded ? item.answer ?? null : null;
  if (item.type === 'choice') score = response !== '' && response === item.answer ? 1 : 0;
  else if (item.type === 'short') score = textCorrect(response as string, item.answer, item.max_words) ? 1 : 0;
  else if (item.type === 'multi' || item.type === 'highlight') {
    const expected = ((item.answer || []) as unknown[]).map(String);
    const actual = response as string[];
    correct = expected;
    if (item.type === 'multi' && item.all_or_nothing) {
      maximum = 1;
      score = [...actual].sort().join('\0') === [...expected].sort().join('\0') ? 1 : 0;
    } else {
      maximum = expected.length;
      score = Math.max(0, actual.filter(id => expected.includes(id)).length - actual.filter(id => !expected.includes(id)).length);
    }
  } else if (item.type === 'reorder') {
    const expected = ((item.answer || []) as unknown[]).map(String);
    const actual = response as string[];
    correct = expected; maximum = Math.max(0, expected.length - 1); score = 0;
    for (let index = 0; index < actual.length - 1; index++) {
      if (expected.some((id, position) => position < expected.length - 1 && id === actual[index] && expected[position + 1] === actual[index + 1])) score++;
    }
  } else if (item.type === 'gaps') {
    maximum = item.blanks?.length || 0; score = 0;
    const gapAnswers: Record<string, unknown> = {};
    for (const blank of item.blanks || []) {
      gapAnswers[blank.id] = blank.answer || [];
      const blankLimit = (blank as {max_words?: number}).max_words;
      const maxWords = typeof blankLimit === 'number' ? blankLimit : item.max_words;
      if (textCorrect((response as Record<string, string>)[blank.id] || '', blank.answer, maxWords)) score++;
    }
    correct = gapAnswers;
  } else if (graded) throw new Error('This question type needs an online practice session.');
  return {
    id: item.id, section_id: sectionId, type: item.type, task_type: item.task_type || item.type,
    prompt: item.prompt || item.text || '', title: item.title || '', instructions: item.instructions || '',
    response, correct_answer: correct, correct, score, max_score: maximum, maximum,
    explanation: graded ? item.explanation || 'Compare the response with the source and accepted answer.' : 'Ungraded offline. Connect and sign in to request optional AI coaching for your writing or spoken transcript.',
    graded, rubric: item.rubric || [], options: item.options || [], text: item.text || '',
    resource_id: item.resource_id || undefined, secondary_resource_id: item.secondary_resource_id || null,
    item_snapshot: answerFree(item), ...(item.correct_text ? { correct_text: item.correct_text } : {}),
  };
}

export function offlineFeedback(exam: Exam, item: Item, response: Answer): Review {
  const bundled = bundledExam(exam);
  for (const section of bundled.sections) {
    const canonicalItem = section.items.find(entry => entry.id === item.id);
    if (canonicalItem) return mark(canonicalItem, section.id, response);
  }
  throw new Error('This question is not available in the offline session.');
}

export function gradeOffline(exam: Exam, answers: Answers): Result {
  const bundled = bundledExam(exam);
  const known = new Set(bundled.sections.flatMap(section => section.items.map(item => item.id)));
  if (Object.keys(answers).some(id => !known.has(id))) throw new Error('A response does not belong to this offline session.');
  const reviews = bundled.sections.flatMap(section => section.items.map(item => mark(item, section.id, answers[item.id])));
  const sections = bundled.sections.map(section => {
    const entries = reviews.filter(review => review.section_id === section.id);
    return { id: section.id, title: section.title, score: entries.reduce((total, review) => total + (review.score || 0), 0), total: entries.reduce((total, review) => total + (review.max_score || 0), 0), answered: entries.filter(review => responsePresent(review.response)).length, total_items: entries.length, ungraded_items: entries.filter(review => review.score === null).length };
  });
  return {
    exam_id: bundled.id, name: bundled.name, exam: bundled.exam, exam_version: bundled.version,
    course_id: bundled.course_id, module_id: bundled.module_id, session_type: 'practice', timing_mode: 'untimed',
    objective_score: sections.reduce((total, section) => total + section.score, 0),
    objective_total: sections.reduce((total, section) => total + section.total, 0),
    answered_items: sections.reduce((total, section) => total + section.answered, 0), total_items: reviews.length,
    sections, review: reviews, responses: cloneJson(answers), resources: cloneJson(bundled.resources),
    content_source: 'generated', generated: true, teacher_reviewed: false, offline: true, grading_source: 'offline-device', sync_eligible: false,
    format_note: String(bundled.offline_disclosure),
    grading_note: 'Device-marked offline practice points only. Writing and speaking remain ungraded offline. No official IELTS/PTE score or AI review has been generated.',
    completed_sections: bundled.sections.map(section => section.id), incomplete: false,
  };
}

export function offlineComplete(exam: Exam, payload: { answers?: Answers; attempt_id?: string; elapsed_seconds?: number; completed_sections?: string[]; assisted?: boolean; exam_id?: string; exam_version?: string }): Envelope {
  const bundled = bundledExam(exam);
  if (payload.exam_id && payload.exam_id !== bundled.id || payload.exam_version && payload.exam_version !== bundled.version) throw new Error('The offline practice reference changed. Start a new local session.');
  const result = gradeOffline(bundled, payload.answers || {});
  const completed = bundled.sections.map(section => section.id).filter(id => payload.completed_sections?.includes(id));
  result.attempt_id = payload.attempt_id || `offline-${newReference()}`;
  result.elapsed_seconds = Math.max(0, Math.min(604800, Math.floor(payload.elapsed_seconds || 0)));
  result.assisted = payload.assisted ?? true;
  result.completed_sections = completed;
  result.incomplete = completed.length < bundled.sections.length;
  return { success: true, saved: false, result_id: null, save_message: 'Saved on this device only. Offline practice is separate from your verified online account history and free mock allowance.', result };
}

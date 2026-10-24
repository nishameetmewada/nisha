<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/exam-data.php';
require_once __DIR__ . '/exam-grading.php';

/* Original parameterised learning material. These are generated variations,
 * not 30,000 independently edited questions or 3,000 unseen official papers. */
function nisha_generated_courses(): array
{
    return [
        'ielts-academic' => ['name' => 'IELTS Academic', 'exam' => 'IELTS', 'base' => 'ielts-academic-01'],
        'ielts-general' => ['name' => 'IELTS General Training', 'exam' => 'IELTS', 'base' => 'ielts-general-01'],
        'pte-academic' => ['name' => 'PTE Academic', 'exam' => 'PTE', 'base' => 'pte-academic-01'],
        'pte-core' => ['name' => 'PTE Core', 'exam' => 'PTE', 'base' => 'pte-core-01'],
    ];
}

function nisha_generated_profiles(): array
{
    return [
        ['id'=>'access','title'=>'Reaching people who do not take part','aim'=>'understand barriers faced by people who do not participate','method'=>'The team will interview both users and non-users before changing the service.','evidence'=>'Comments from people who have not joined are considered alongside attendance records.','limitation'=>'A busy registration list cannot show whether the least confident people have been excluded.','decision'=>'Keep an assisted route available while testing the proposed change.','gap'=>'The interviews must include users and [focus].','word'=>'non-users'],
        ['id'=>'pilot','title'=>'Testing before expanding','aim'=>'test a small version before committing to a larger programme','method'=>'The team will run a limited pilot and record difficulties before expanding the programme.','evidence'=>'A pilot diary will record problems as well as successful visits.','limitation'=>'Success with a small supported group does not guarantee success at a much larger scale.','decision'=>'Review the pilot before signing a long-term agreement.','gap'=>'Before expansion, the team will run a limited [focus].','word'=>'pilot'],
        ['id'=>'maintenance','title'=>'Keeping the proposal useful','aim'=>'plan the maintenance needed after the initial launch','method'=>'The team will create a maintenance schedule and assign responsibility for routine checks.','evidence'=>'Repair records will show which problems recur and how long they remain unresolved.','limitation'=>'An attractive launch can hide a lack of money or staff for everyday upkeep.','decision'=>'Reserve resources for upkeep rather than spend the entire budget at launch.','gap'=>'Routine checks will follow a maintenance [focus].','word'=>'schedule'],
        ['id'=>'timing','title'=>'Matching support to daily routines','aim'=>'understand how demand changes at different times','method'=>'The team will compare observations made in the morning, afternoon and evening.','evidence'=>'A time diary will distinguish convenient periods from periods when people need help but cannot attend.','limitation'=>'A single observation period may miss people whose daily routines are different.','decision'=>'Trial alternative opening periods before extending every session.','gap'=>'Observations will cover morning, afternoon and [focus].','word'=>'evening'],
        ['id'=>'budget','title'=>'Choosing between useful improvements','aim'=>'compare the practical benefits of competing spending options','method'=>'The team will compare essential improvements with optional additions before allocating the budget.','evidence'=>'Written estimates will include running costs as well as the initial purchase.','limitation'=>'The cheapest initial option can create higher operating costs later.','decision'=>'Explain the trade-offs instead of choosing by purchase price alone.','gap'=>'Estimates must include running costs and the initial [focus].','word'=>'purchase'],
        ['id'=>'interviews','title'=>'Understanding what the totals cannot explain','aim'=>'explain the reasons behind observed patterns','method'=>'The team will combine a simple survey with follow-up interviews about unexpected responses.','evidence'=>'The interviews will help explain why people made different choices.','limitation'=>'A percentage can describe a pattern without explaining the experience behind it.','decision'=>'Use numerical records and personal accounts together.','gap'=>'Unexpected survey responses will lead to follow-up [focus].','word'=>'interviews'],
        ['id'=>'environment','title'=>'Checking effects beyond the immediate benefit','aim'=>'consider environmental effects across the life of the proposal','method'=>'The team will examine materials, transport, energy use and eventual disposal.','evidence'=>'A life-cycle record will include activities outside the project building.','limitation'=>'A visible reduction in one place may simply move a cost elsewhere.','decision'=>'Compare the whole process rather than one attractive environmental claim.','gap'=>'The record must consider materials, energy and eventual [focus].','word'=>'disposal'],
        ['id'=>'fairness','title'=>'Distributing opportunities fairly','aim'=>'check whether different groups can benefit fairly','method'=>'The team will compare access arrangements for newcomers and experienced participants.','evidence'=>'Booking records will be reviewed alongside requests that could not be accommodated.','limitation'=>'High overall demand can conceal repeated advantages for the same confident group.','decision'=>'Protect some opportunities for newcomers and review the effect.','gap'=>'Some opportunities will be protected for [focus].','word'=>'newcomers'],
        ['id'=>'privacy','title'=>'Collecting only useful information','aim'=>'collect useful evidence without unnecessary personal information','method'=>'The team will remove names from the evaluation and collect only information needed for the stated purpose.','evidence'=>'Anonymous feedback will be grouped before the findings are discussed.','limitation'=>'Detailed personal records can create risks even when the evaluation has a helpful aim.','decision'=>'Explain why information is needed and delete identifying details when they are no longer required.','gap'=>'Before discussion, feedback will be made [focus].','word'=>'anonymous'],
        ['id'=>'durability','title'=>'Learning from ordinary wear','aim'=>'understand how the proposal performs during ordinary repeated use','method'=>'The team will monitor repeated use instead of relying on a demonstration at the opening event.','evidence'=>'A condition log will record gradual changes and interruptions to normal use.','limitation'=>'A successful demonstration does not show whether the arrangement remains reliable.','decision'=>'Review ordinary use before advertising long-term reliability.','gap'=>'Gradual changes will be recorded in a condition [focus].','word'=>'log'],
        ['id'=>'seasons','title'=>'Allowing for changing conditions','aim'=>'distinguish a lasting pattern from a seasonal effect','method'=>'The team will repeat observations in contrasting seasons rather than compare only consecutive days.','evidence'=>'The evaluation will record weather and calendar conditions with each observation.','limitation'=>'A short trial during unusually convenient conditions may overstate the lasting benefit.','decision'=>'Report the conditions of the trial and repeat it before drawing a broad conclusion.','gap'=>'Observations will be repeated in contrasting [focus].','word'=>'seasons'],
        ['id'=>'communication','title'=>'Checking whether instructions make sense','aim'=>'find out whether newcomers understand the instructions','method'=>'The team will ask first-time visitors to explain the instructions in their own words.','evidence'=>'Misunderstandings will be recorded before the wording or layout is revised.','limitation'=>'Instructions that seem obvious to experienced staff may confuse a newcomer.','decision'=>'Test revised instructions with people who have not seen the earlier version.','gap'=>'The revised instructions will be tested with first-time [focus].','word'=>'visitors'],
        ['id'=>'training','title'=>'Providing support before independent use','aim'=>'identify the training needed for confident independent participation','method'=>'The team will offer a short induction and observe which steps still require assistance.','evidence'=>'Questions asked after the induction will guide the next support session.','limitation'=>'A signed attendance sheet does not prove that someone can complete a task independently.','decision'=>'Provide a route to ask for help after the introductory session.','gap'=>'New participants will first attend a short [focus].','word'=>'induction'],
        ['id'=>'measurement','title'=>'Making comparisons responsibly','aim'=>'make measurements comparable before interpreting differences','method'=>'The team will agree common definitions and check that observers apply them consistently.','evidence'=>'A shared recording guide will describe both examples and borderline cases.','limitation'=>'A precise number can be misleading when different observers count different things.','decision'=>'Resolve differences in recording methods before comparing results.','gap'=>'Observers will use a shared recording [focus].','word'=>'guide'],
        ['id'=>'feedback','title'=>'Turning comments into a visible change','aim'=>'connect feedback with a clear decision and follow-up review','method'=>'The team will publish a short response explaining which suggestions can be tried and why.','evidence'=>'A follow-up review will ask whether the agreed change addressed the original difficulty.','limitation'=>'Collecting comments repeatedly can frustrate participants if no response is visible.','decision'=>'Explain the action taken and invite a focused follow-up response.','gap'=>'After the change, the team will conduct a follow-up [focus].','word'=>'review'],
    ];
}

function nisha_generated_topics(): array
{
    static $topics;
    if ($topics === null) {
        $topics = require __DIR__ . '/content/generated-topics.php';
        if (!is_array($topics) || count($topics) !== 50) throw new UnexpectedValueException('The generated topic bank is incomplete.');
    }
    return $topics;
}

function nisha_generated_content_hash(): string
{
    static $hash;
    return $hash ??= substr(hash('sha256', hash_file('sha256', __FILE__) . hash_file('sha256', __DIR__ . '/content/generated-topics.php') . implode('', array_map(static fn($course) => hash_file('sha256', __DIR__ . '/exams/' . $course['base'] . '.php'), nisha_generated_courses()))), 0, 24);
}

function nisha_generated_scenario(int $number): array
{
    $topic = nisha_generated_topics()[intdiv($number, 15)];
    $profile = nisha_generated_profiles()[$number % 15];
    $paragraphs = [$topic['context'] . ' ' . $topic['benefit'], $topic['mechanism'] . ' ' . $topic['example'],
        'The evaluation has a specific aim: to ' . $profile['aim'] . '. ' . $profile['method'] . ' ' . $profile['evidence'],
        $topic['risk'] . ' ' . $profile['limitation'] . ' ' . $topic['recommendation'] . ' ' . $profile['decision']];
    return ['topic'=>$topic,'profile'=>$profile,'title'=>$topic['title'] . ' · ' . $profile['title'],'paragraphs'=>$paragraphs,
        'audio_script'=>implode(' ', $paragraphs)];
}

function nisha_generated_choices(string $answer, array $wrong, int $rotation): array
{
    $texts = array_values(array_unique(array_merge([$answer], $wrong)));
    if (count($texts) < 4) throw new UnexpectedValueException('Generated choice distractors are not distinct.');
    $texts = array_slice($texts, 0, 4); $shift = $rotation % 4;
    $texts = array_merge(array_slice($texts, $shift), array_slice($texts, 0, $shift));
    $options = [];
    foreach ($texts as $i=>$text) $options[] = ['id'=>chr(97+$i),'text'=>$text];
    return ['options'=>$options,'answer'=>chr(97+(int)array_search($answer,$texts,true))];
}

/** One of 30,000 resolvable variations. No entire-bank materialisation occurs. */
function nisha_generated_question(string $courseId, int $number): array
{
    $course = nisha_generated_courses()[$courseId] ?? null;
    if (!$course || $number < 1 || $number > 7500) throw new InvalidArgumentException('Choose an available generated question.');
    $scenario = nisha_generated_scenario(intdiv($number-1,10)); $slot = ($number-1)%10;
    $topic = $scenario['topic']; $profile = $scenario['profile'];
    $id = 'g-' . $courseId . '-' . str_pad((string)$number,5,'0',STR_PAD_LEFT);
    $resource = 'r-' . $id; $skill = $slot < 3 ? 'reading' : ($slot < 6 ? 'listening' : ($slot < 8 ? 'writing' : 'speaking'));
    $resources = [$resource=>['kind'=>$skill==='listening'?'audio':'passage','title'=>$scenario['title'],'paragraphs'=>$scenario['paragraphs'],'audio_script'=>$scenario['audio_script']]];
    $item = ['id'=>$id,'type'=>'choice','task_type'=>'Generated comprehension practice','title'=>$scenario['title'],'resource_id'=>$resource,
        'prompt'=>'','instructions'=>'Use only the supplied scenario. This is a generated learning variation, not an official test question.','generated'=>true,'generation_family'=>$topic['id'].':'.$profile['id'],
        'explanation'=>'Use the stated evidence and distinguish the proposal from a guaranteed outcome.'];
    $courseIndex = array_search($courseId,array_keys(nisha_generated_courses()),true);
    $wrong = ['Guarantee that every participant will receive the same result.','Replace all evaluation with a single attendance total.','Expand immediately without checking the practical difficulties.'];
    if ($slot === 0) {
        $focus = ['aim','decision','limitation','evidence'][$courseIndex];
        $answer = $profile[$focus];
        $labels = ['What is the specific purpose of the evaluation?','Which action does the evaluation recommend next?','Which limitation is explicitly acknowledged?','What evidence will the team examine?'];
        $item['prompt']=$labels[$courseIndex]; $item += nisha_generated_choices($answer,$wrong,$number);
        $item['task_type']='Reading · Multiple choice'; $item['explanation']='The scenario explicitly states: '.$answer;
    } elseif ($slot === 1 && $courseId === 'pte-academic') {
        $item['type']='reorder'; $item['task_type']='Re-order paragraphs'; $item['prompt']='Put the stages of this evaluation into a coherent order.';
        $texts=['First, the team identifies the practical issue in the scenario.','Next, it agrees what evidence would answer the evaluation question.','Then, it collects observations and checks possible limitations.','Finally, it reviews the evidence before choosing the next action.'];
        $item['options']=[]; foreach([2,0,3,1] as $i) $item['options'][]=['id'=>chr(97+$i),'text'=>$texts[$i]];
        $item['answer']=['a','b','c','d']; $item['explanation']='Identify the issue, plan evidence, collect it, and then decide. The transition words support this order.';
    } elseif ($slot === 1 && $courseId === 'pte-core') {
        $item['type']='gaps'; $item['task_type']='Reading · Fill in the blanks'; $item['prompt']='Complete the evaluation note with a word from the passage.';
        $item['text']=$profile['gap']; $item['blanks']=[['id'=>'focus','answer'=>[$profile['word']]]]; $item['explanation']='The relevant expression is “'.$profile['word'].'”.';
    } elseif ($slot === 1 && $courseId === 'ielts-general') {
        $item['type']='multi'; $item['task_type']='Reading · Multiple choice, two answers'; $item['prompt']='Which TWO statements are supported by the scenario?'; $item['select_count']=2;
        $item['options']=[['id'=>'a','text'=>$topic['benefit']],['id'=>'b','text'=>$wrong[0]],['id'=>'c','text'=>$profile['method']],['id'=>'d','text'=>$wrong[2]]]; $item['answer']=['a','c'];
        $item['explanation']='The proposed benefit and stated method are supported. Guaranteed results and immediate unchecked expansion are not.';
    } elseif ($slot === 1) {
        $item['task_type']='Reading · Evidence and explanation'; $item['prompt']='Which explanation best describes how the proposed benefit could arise?';
        $item += nisha_generated_choices($topic['mechanism'],$wrong,$number); $item['explanation']=$topic['mechanism'];
    } elseif ($slot === 2) {
        $item['type']='short'; $item['task_type']='Reading · Short answer';
        $item['prompt']=match($courseId) {'ielts-academic'=>'Which term from the evaluation method completes this note: '.str_replace('[focus]','____',$profile['gap']), 'ielts-general'=>'Write the missing word from this practical instruction: '.str_replace('[focus]','____',$profile['gap']), 'pte-academic'=>'Identify the missing key word in the research note: '.str_replace('[focus]','____',$profile['gap']),default=>'Use one word from the text to finish the workplace note: '.str_replace('[focus]','____',$profile['gap'])};
        $item['answer']=[$profile['word']]; $item['max_words']=1; $item['explanation']='The source uses the word “'.$profile['word'].'”.';
    } elseif ($slot === 3) {
        $answer=[$profile['method'],$topic['benefit'],$topic['mechanism'],$profile['decision']][$courseIndex];
        $item['task_type']='Listening · Multiple choice'; $item['prompt']=['Which method will the team use?','Which potential benefit does the speaker describe?','How does the speaker explain the proposed benefit?','What action does the speaker recommend?'][$courseIndex];
        $item += nisha_generated_choices($answer,$wrong,$number); $item['explanation']='The recording states: '.$answer;
    } elseif ($slot === 4 && $courseId === 'pte-academic') {
        $item['type']='highlight'; $item['task_type']='Highlight Incorrect Words'; $item['prompt']='Listen to the recording and select the ONE word in the displayed sentence that differs.';
        $correct='The evaluation should examine evidence before the programme expands.'; $item['text']='The evaluation should ignore evidence before the programme expands.';
        $resources[$resource]['audio_script']=$correct; $item['answer']=['3']; $item['correct_text']=$correct; $item['explanation']='The recording says “examine”; the display says “ignore”.';
        $item['title']=$scenario['title'].' · Check the evaluation principle';
        // Keep the original scenario as meaningful spoken context before the
        // comparison sentence; only the displayed sentence is compared.
        $resources[$resource]['audio_script']=$profile['method'].' '.$correct;
    } elseif ($slot === 4 && $courseId === 'pte-core') {
        $item['task_type']='Listening · Main message'; $item['prompt']='Why is the team carrying out this evaluation?';
        $item += nisha_generated_choices($profile['aim'],$wrong,$number); $item['explanation']='The stated aim is to '.$profile['aim'].'.';
    } elseif ($slot === 4) {
        $supported=$courseId==='ielts-academic'?[$topic['benefit'],$profile['method']]:[$topic['recommendation'],$profile['evidence']];
        $item['type']='multi'; $item['task_type']='Listening · Multiple choice, two answers'; $item['prompt']='Select the TWO points supported by the speaker.'; $item['select_count']=2;
        $item['options']=[['id'=>'a','text'=>$supported[0]],['id'=>'b','text'=>$wrong[1]],['id'=>'c','text'=>$supported[1]],['id'=>'d','text'=>$wrong[0]]];$item['answer']=['a','c'];
        $item['explanation']='Both selected points appear in the recording; the alternative claims exaggerate or contradict its conclusions.';
    } elseif ($slot === 5 && str_starts_with($courseId,'pte-')) {
        $sentence=$courseId==='pte-academic'?$topic['mechanism']:$profile['method'];
        $resources[$resource]['audio_script']=$sentence; $item['type']='short';$item['task_type']='Write from Dictation';$item['prompt']='Listen and write the complete sentence you hear.';
        $item['answer']=[$sentence];$item['explanation']='The sentence is: '.$sentence;$item['instructions']='Use accurate words and spelling. This practice uses whole-sentence marking.';
    } elseif ($slot === 5) {
        $item['type']='gaps';$item['task_type']='Listening · Note completion';$item['prompt']='Complete the note using ONE WORD from the recording.';$item['text']=$profile['gap'];
        $item['blanks']=[['id'=>'focus','answer'=>[$profile['word']]]];$item['explanation']='The recording uses “'.$profile['word'].'” in the stated method.';
    } elseif ($slot === 6 || $slot === 7) {
        $item['type']='essay';$item['rubric']=['Address each part of the task using relevant detail.','Organise ideas into connected, coherent language.','Check grammar, vocabulary, spelling and the stated word limit.'];
        if ($slot===6 && $courseId==='ielts-academic') {
            $chartId=$resource.'-chart';$resources[$chartId]=nisha_generated_chart($scenario,$number);
            $item['resource_id']=$chartId;$item['task_type']='Academic Writing Task 1';$item['min_words']=150;$item['suggested_seconds']=1200;
            $item['prompt']='The chart shows a fictional pilot connected with '.$topic['title'].'. Summarise the main features and make relevant comparisons. Do not invent reasons that the chart cannot establish.';
        } elseif ($slot===6 && $courseId==='ielts-general') {
            $item['task_type']='General Training Writing Task 1';$item['min_words']=150;$item['suggested_seconds']=1200;
            $item['prompt']='You would like to join the project described in the scenario. Write a letter to its coordinator. Explain your interest, describe one practical difficulty and ask how the team could help you participate.';
        } elseif ($slot===6) {
            $item['task_type']='Summarize Written Text';$item['min_words']=$courseId==='pte-core'?25:5;$item['max_words']=$courseId==='pte-core'?50:75;$item['suggested_seconds']=600;
            $item['prompt']=$courseId==='pte-core'?'Summarise the scenario in 25–50 words. Include its purpose, approach and important caution.':'Summarise the scenario in ONE sentence of 5–75 words. Include its central proposal and the important evaluation caution.';
        } elseif ($courseId==='pte-core') {
            $item['task_type']='Write Email';$item['min_words']=50;$item['max_words']=120;$item['suggested_seconds']=540;
            $item['prompt']='Write an email to the project coordinator. Explain why '.$profile['title'].' matters, request clarification about the proposed method, and suggest one practical next step. Write 50–120 words.';
        } else {
            $item['task_type']=$course['exam']==='IELTS'?'Writing Task 2':'Write Essay';$item['min_words']=$course['exam']==='IELTS'?250:200;$item['suggested_seconds']=$course['exam']==='IELTS'?2400:1200;
            if($course['exam']==='PTE')$item['max_words']=300;
            $item['prompt']='Organisations sometimes prioritise the immediate benefit of a proposal, while others require evidence about its limitations before expanding it. Discuss both approaches using the issue of '.$topic['title'].' and the focus on '.$profile['title'].'. Give your view with relevant reasons and examples.';
        }
        $item['instructions']='Write your own response. Request optional AI coaching when enabled for a practice estimate and improvement advice. These are not official scores.';
    } else {
        $item['type']='spoken';$item['prep_seconds']=30;$item['response_seconds']=90;
        $item['rubric']=['Address the task and develop relevant detail.','Use clear, connected language and specific examples.','AI coaching can assess transcribed language when enabled; assess pronunciation and delivery separately.'];
        if($slot===8 && $course['exam']==='PTE') {
            $resources[$resource]['paragraphs']=[$topic['context'].' '.$topic['mechanism']];$item['task_type']='Read Aloud';$item['prompt']='Read the displayed passage aloud naturally and accurately.';$item['prep_seconds']=35;$item['response_seconds']=40;
        } elseif($slot===9 && $courseId==='pte-academic') {
            $chartId=$resource.'-chart';$resources[$chartId]=nisha_generated_chart($scenario,$number);$item['resource_id']=$chartId;$item['task_type']='Describe Image';$item['prompt']='Describe the main pattern in this fictional project chart. Include relevant comparisons without claiming that the figures prove a cause.';$item['prep_seconds']=25;$item['response_seconds']=40;
        } elseif($slot===9 && $courseId==='pte-core') {
            $item['task_type']='Respond to a Situation';$item['prompt']='You are helping the team in the scenario. A colleague wants to expand immediately, but you are concerned about '.$profile['title'].'. Explain the concern politely, suggest the evaluation step and ask the colleague to agree on a practical plan.';$item['prep_seconds']=20;$item['response_seconds']=40;
        } elseif($slot===8) {
            $item['task_type']='Speaking Part 1 · Familiar questions';$item['prompt']=$courseId==='ielts-academic'?'What do you already know about '.$topic['title'].'? Explain one way this issue might affect people in your community.':'Would you be interested in helping with the project described here? Explain what would make participation convenient or difficult for you.';$item['prep_seconds']=0;$item['response_seconds']=60;
        } else {
            $item['task_type']='Speaking Part 2 · Individual long turn';$item['prompt']='Describe a project or activity related to '.$topic['title'].' that you have experienced or would like to try. Explain its purpose, how people take part, and why '.$profile['title'].' could matter.';
            $item['cues']=['What the project or activity is','Who could benefit','What practical difficulty could arise','Explain one useful lesson'];$item['prep_seconds']=60;$item['response_seconds']=120;
        }
        $item['instructions']='Speak aloud; recording is optional. AI reviews require a transcript or selected recording and assess language rather than pronunciation or delivery.';
    }
    return ['item'=>$item,'resources'=>$resources,'skill'=>$skill,'scenario_number'=>intdiv($number-1,10),'slot'=>$slot];
}

function nisha_generated_chart(array $scenario,int $seed):array
{
    $start=15+($seed%21);$change=4+($seed%7);
    return ['kind'=>'chart','title'=>'Fictional pilot · '.$scenario['topic']['title'],'chart'=>['unit'=>'participants completing the activity','categories'=>['Week 1','Week 2','Week 3','Week 4'],
        'series'=>[['name'=>'Supported route','values'=>[$start,$start+$change,$start+2*$change,$start+3*$change]],['name'=>'Independent route','values'=>[$start-5,$start-2,$start+1,$start+4]]]],
        'description'=>'Invented learning data for a project focused on '.$scenario['profile']['title'].'. These values do not represent an actual research study.'];
}

function nisha_generated_question_session(string $courseId,int $number):array
{
    $course=nisha_generated_courses()[$courseId];$q=nisha_generated_question($courseId,$number);
    $q['resources'] = array_intersect_key($q['resources'], array_flip([$q['item']['resource_id']]));
    foreach ($q['resources'] as &$source) { if ($source['kind'] === 'audio') unset($source['paragraphs']); elseif ($source['kind'] === 'passage') unset($source['audio_script']); } unset($source);
    $id='gp.'.$courseId.'.'.str_pad((string)$number,5,'0',STR_PAD_LEFT);
    return ['id'=>$id,'name'=>$course['name'].' · Generated practice '.$number,'exam'=>$course['exam'],'version'=>'gen-'.nisha_generated_content_hash().'-'.$number,
        'description'=>'An original parameterised learning variation with a stated scenario and focused task.','format_note'=>nisha_generated_disclosure(),
        'course_id'=>$courseId,'module_id'=>'generated-'.$q['skill'],'session_type'=>'practice','timing_mode'=>'untimed','generated'=>true,'resources'=>$q['resources'],
        'sections'=>[['id'=>'generated-'.$q['skill'],'title'=>ucfirst($q['skill']).' practice','duration_seconds'=>$q['skill']==='writing'?1200:180,'navigation'=>'free','instructions'=>'Generated practice: work at your own pace. Scenarios recur in related questions and assembled mocks.','items'=>[$q['item']]]]];
}

function nisha_generated_disclosure():string
{
    return 'Teacher-reviewed practice developed from original NISHA draft topics. Assembled mocks reuse questions from the reviewed bank; they are not independent unseen papers or official examinations. Listening uses browser speech. Scores are practice guidance only.';
}

function nisha_generated_parse_id(string $id): ?array
{
    if (!preg_match('/\A(gp|gm)\.(ielts-academic|ielts-general|pte-academic|pte-core)\.([0-9]{4,5})\z/D', $id, $m)) return null;
    $number = (int) $m[3];
    if (($m[1] === 'gp' && strlen($m[3]) === 5 && $number >= 1 && $number <= 7500)
        || ($m[1] === 'gm' && strlen($m[3]) === 4 && $number >= 1 && $number <= 750)) {
        return ['id' => $id, 'kind' => $m[1] === 'gp' ? 'question' : 'mock', 'course' => $m[2], 'number' => $number];
    }
    return null;
}

/** Shared public predicate: no generated draft or stale paper can become public. */
function nisha_content_visible_sql(string $alias = 'p'): string
{
    if (!in_array($alias, ['p', 'qp'], true)) throw new LogicException('Invalid internal query alias.');
    return "$alias.status='published' AND EXISTS (SELECT 1 FROM content_review_versions rv JOIN teacher_profiles tp ON tp.user_id=rv.reviewer_id AND tp.status='approved' WHERE rv.id=$alias.version_id)"
        . " AND ($alias.kind='question' OR (EXISTS (SELECT 1 FROM content_mock_dependencies dep WHERE dep.mock_id=$alias.content_id) AND NOT EXISTS (SELECT 1 FROM content_mock_dependencies dep LEFT JOIN content_publications qp ON qp.content_id=dep.question_id AND qp.version_id=dep.question_version_id AND qp.status='published' LEFT JOIN content_review_versions qv ON qv.id=qp.version_id LEFT JOIN teacher_profiles qt ON qt.user_id=qv.reviewer_id AND qt.status='approved' WHERE dep.mock_id=$alias.content_id AND (qp.content_id IS NULL OR qt.user_id IS NULL))))";
}

function nisha_generated_stats(): array
{
    $counts = [];
    try {
        $rows = db()->query('SELECT p.kind,p.course_id,COUNT(*) AS total FROM content_publications p WHERE ' . nisha_content_visible_sql() . ' GROUP BY p.kind,p.course_id')->fetchAll();
        foreach ($rows as $row) $counts[$row['course_id']][$row['kind']] = (int) $row['total'];
    } catch (Throwable $exception) { /* Public starter pages still work before migration. */ }
    $questions = 0; $mocks = 0; $courses = [];
    foreach (nisha_generated_courses() as $id => $course) {
        $q = $counts[$id]['question'] ?? 0; $m = $counts[$id]['mock'] ?? 0;
        $questions += $q; $mocks += $m;
        $courses[] = ['id' => $id, 'name' => $course['name'], 'question_count' => $q, 'mock_count' => $m];
    }
    return ['published' => $questions + $mocks > 0, 'question_count' => $questions, 'mock_count' => $mocks,
        'target_question_count' => 30000, 'target_mock_count' => 3000, 'draft_question_count' => 30000, 'draft_mock_count' => 3000,
        'courses' => $courses, 'disclosure' => nisha_generated_disclosure(), 'review_required' => true];
}

/** Return a frozen, reviewed version only. Never regenerate public content. */
function nisha_content_public_row(string $id, ?PDO $connection = null): ?array
{
    if (!nisha_generated_parse_id($id)) return null;
    try {
        $q = ($connection ?? db())->prepare('SELECT p.*,rv.payload_json,rv.content_hash,rv.reviewer_id,rv.reviewed_at FROM content_publications p JOIN content_review_versions rv ON rv.id=p.version_id WHERE p.content_id=? AND ' . nisha_content_visible_sql() . ' LIMIT 1');
        $q->execute([$id]); $row = $q->fetch();
        return $row ?: null;
    } catch (Throwable $exception) { return null; }
}

function nisha_generated_resolve_exam(string $id): ?array
{
    $row = nisha_content_public_row($id);
    if (!$row) return null;
    try {
        $session = json_decode($row['payload_json'], true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($session) || ($session['id'] ?? '') !== $id || !hash_equals($row['content_hash'], nisha_content_payload_hash($session))) return null;
        return $session;
    } catch (Throwable $exception) { return null; }
}

function nisha_generated_page(string $kind, string $courseId, int $page = 1, int $perPage = 24): array
{
    if (!isset(nisha_generated_courses()[$courseId]) || !in_array($kind, ['questions', 'mocks'], true)) return ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 0];
    $perPage = max(1, min(48, $perPage)); $type = $kind === 'questions' ? 'question' : 'mock';
    try {
        $where = 'p.kind=? AND p.course_id=? AND ' . nisha_content_visible_sql();
        $q = db()->prepare('SELECT COUNT(*) FROM content_publications p WHERE ' . $where); $q->execute([$type, $courseId]);
        $total = (int) $q->fetchColumn(); $pages = (int) ceil($total / $perPage); $page = max(1, min(max(1, $pages), $page));
        $q = db()->prepare('SELECT p.content_id,p.draft_number,rv.payload_json,rv.reviewed_at FROM content_publications p JOIN content_review_versions rv ON rv.id=p.version_id WHERE ' . $where . ' ORDER BY p.draft_number LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage));
        $q->execute([$type, $courseId]); $items = [];
        foreach ($q->fetchAll() as $row) {
            $s = json_decode($row['payload_json'], true, 64, JSON_THROW_ON_ERROR);
            $items[] = ['id' => $s['id'], 'name' => $s['name'], 'course_id' => $courseId, 'session_type' => $s['session_type'],
                'item_count' => count(nisha_exam_items($s)), 'duration_seconds' => array_sum(array_column($s['sections'], 'duration_seconds')),
                'skill' => $type === 'question' ? ($s['review_skill'] ?? 'practice') : 'all', 'reviewed_at' => $row['reviewed_at'],
                'launch_url' => 'exams.php?' . http_build_query(['exam' => $s['id'], 'start' => '1'])];
        }
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
    } catch (Throwable $exception) { return ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 0, 'per_page' => $perPage]; }
}

/** Draft-only source mapping. Each mock has a distinct set, not reordered copies. */
function nisha_generated_mock_sources(string $courseId, int $number): array
{
    $course = nisha_generated_courses()[$courseId] ?? null;
    if (!$course || $number < 1 || $number > 750) throw new InvalidArgumentException('Choose an available draft paper.');
    $base = nisha_exams()[$course['base']]; $sections = []; $position = 0;
    foreach ($base['sections'] as $section) {
        $key = strtolower($section['id'] . ' ' . $section['title']); $ids = [];
        foreach ($section['items'] as $i => $item) {
            $skill = str_contains($key, 'speaking') && str_contains($key, 'writing') ? ($item['type'] === 'essay' ? 'writing' : 'speaking')
                : (str_contains($key, 'listening') ? 'listening' : (str_contains($key, 'reading') ? 'reading' : (str_contains($key, 'writing') ? 'writing' : 'speaking')));
            $slots = match ($skill) { 'reading' => [0,1,2], 'listening' => [3,4,5], 'writing' => [6,7], default => [8,9] };
            $scenario = (($number - 1) * 47 + $position * 19) % 750;
            $qNumber = $scenario * 10 + $slots[$i % count($slots)] + 1;
            $ids[] = 'gp.' . $courseId . '.' . str_pad((string) $qNumber, 5, '0', STR_PAD_LEFT); ++$position;
        }
        $sections[] = ['base' => $section, 'question_ids' => $ids];
    }
    return $sections;
}

function nisha_generated_mock(string $courseId, int $number, ?bool $reviewedOnly = false): array
{
    $course = nisha_generated_courses()[$courseId]; $sections = []; $resources = []; $dependencies = []; $missing = [];
    foreach (nisha_generated_mock_sources($courseId, $number) as $definition) {
        $section = $definition['base']; $section['items'] = [];
        $section['instructions'] = 'Complete this assembled practice section. Questions reuse the reviewed learning bank; the task mix is not a replica of an official examination.';
        foreach ($definition['question_ids'] as $id) {
            $row = $reviewedOnly !== false ? nisha_content_public_row($id) : null;
            if ($reviewedOnly && !$row) { $missing[] = $id; continue; }
            $parsed = nisha_generated_parse_id($id);
            $q = $row ? json_decode($row['payload_json'], true, 64, JSON_THROW_ON_ERROR) : nisha_generated_question_session($courseId, $parsed['number']);
            $section['items'][] = $q['sections'][0]['items'][0];
            $resources += $q['resources'];
            if ($row) $dependencies[$id] = ['version_id' => (int) $row['version_id'], 'content_hash' => $row['content_hash']];
        }
        $sections[] = $section;
    }
    if ($missing) throw new InvalidArgumentException('This paper still needs ' . count($missing) . ' reviewed questions. First missing question: ' . $missing[0] . '.');
    $id = 'gm.' . $courseId . '.' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    return ['id' => $id, 'name' => $course['name'] . ' · Reviewed assembled mock ' . $number, 'exam' => $course['exam'],
        'version' => 'draft-' . nisha_generated_content_hash() . '-' . $number, 'course_id' => $courseId, 'module_id' => null,
        'session_type' => 'full', 'timing_mode' => 'timed', 'description' => 'A timed paper assembled from teacher-reviewed learning questions.',
        'format_note' => nisha_generated_disclosure(), 'resources' => $resources, 'sections' => $sections, 'review_dependencies' => $dependencies];
}

function nisha_content_payload_hash(array $session): string
{
    unset($session['version']);
    return hash('sha256', json_encode($session, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

/** Ignore labels/identifiers so duplicates cannot inflate the published count. */
function nisha_content_semantic_hash(array $session): string
{
    $items = [];
    $normalise = static function(mixed $value) use (&$normalise): mixed {
        if (is_string($value)) return strtolower(preg_replace('/\s+/u',' ',trim($value)) ?? $value);
        if (is_array($value)) { foreach($value as $key=>$part) $value[$key]=$normalise($part); }
        return $value;
    };
    foreach (nisha_exam_items($session) as $item) {
        $resource = $session['resources'][$item['resource_id'] ?? ''] ?? [];
        $source = $resource['kind'] === 'audio' ? ['audio' => $resource['audio_script'] ?? ''] : ['paragraphs' => $resource['paragraphs'] ?? [], 'chart' => $resource['chart'] ?? null];
        foreach (['id','title','resource_id','secondary_resource_id','generated','generation_family','task_type','explanation'] as $key) unset($item[$key]);
        if (isset($item['options'])) {
            $byId = array_column($item['options'],'text','id');
            $correct = is_array($item['answer']) ? array_map(static fn($id)=>$byId[$id],$item['answer']) : $byId[$item['answer']];
            $texts = array_values($byId); sort($texts);
            if ($item['type']==='multi') sort($correct);
            $item['options']=$texts; $item['answer']=$correct;
        }
        $items[] = hash('sha256', json_encode($normalise([$item, $source]), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
    if ($session['session_type'] === 'full') sort($items);
    return hash('sha256', json_encode($items, JSON_THROW_ON_ERROR));
}

function nisha_content_require_teacher(array $user): void
{
    require_once __DIR__ . '/teachers.php';
    if (!nisha_teacher_approved($user)) throw new InvalidArgumentException('An approved teacher must review and certify this content.');
}

function nisha_content_text(mixed $value, string $label, int $limit = 10000, bool $empty = false): string
{
    if (!is_string($value) || strlen($value) > $limit || !preg_match('//u', $value) || str_contains($value, "\0") || (!$empty && trim($value) === '')) throw new InvalidArgumentException('Enter valid ' . $label . '.');
    return trim($value);
}

/** Structural validation complements, but never substitutes for, teacher review. */
function nisha_content_validate_session(array $session): void
{
    if (!isset($session['resources'],$session['sections']) || !is_array($session['resources']) || !is_array($session['sections']) || count($session['resources']) > 250 || count($session['sections']) > 8) throw new InvalidArgumentException('The paper structure is invalid.');
    nisha_content_text($session['name'] ?? '', 'a title', 180);
    $items = nisha_exam_items($session);
    if (!$items || count($items) > 150) throw new InvalidArgumentException('A paper needs between 1 and 150 questions.');
    if (($session['session_type'] ?? '') === 'practice' && count($items) !== 1) throw new InvalidArgumentException('A practice publication must contain one reviewed question.');
    foreach ($session['resources'] as $resource) {
        if (!is_array($resource) || !in_array($resource['kind'] ?? '', ['passage','audio','chart'], true)) throw new InvalidArgumentException('Use text, audio-script or chart resources.');
        nisha_content_text($resource['title'] ?? '', 'a resource title', 250);
        if ($resource['kind'] === 'audio') nisha_content_text($resource['audio_script'] ?? '', 'an audio script', 50000);
        if ($resource['kind'] === 'passage') {
            if (!is_array($resource['paragraphs'] ?? null) || !$resource['paragraphs'] || count($resource['paragraphs']) > 50) throw new InvalidArgumentException('A passage needs readable paragraphs.');
            foreach ($resource['paragraphs'] as $p) nisha_content_text($p, 'a passage paragraph', 10000);
        }
        if ($resource['kind'] === 'chart') {
            $chart = $resource['chart'] ?? [];
            if (!is_array($chart['categories'] ?? null) || !$chart['categories'] || count($chart['categories']) > 20 || !is_array($chart['series'] ?? null) || !$chart['series'] || count($chart['series']) > 5) throw new InvalidArgumentException('The chart data is invalid.');
            foreach ($chart['categories'] as $label) nisha_content_text($label, 'a chart label', 120);
            foreach ($chart['series'] as $series) {
                nisha_content_text($series['name'] ?? '', 'a chart series label', 120);
                if (count($series['values'] ?? []) !== count($chart['categories'])) throw new InvalidArgumentException('Every chart series must match the categories.');
                foreach ($series['values'] as $value) if ((!is_int($value) && !is_float($value)) || !is_finite((float)$value) || abs($value) > 1000000000) throw new InvalidArgumentException('Use bounded numeric chart values.');
            }
        }
    }
    foreach ($items as $item) {
        $type = $item['type'] ?? '';
        if (!in_array($type, ['choice','multi','short','gaps','reorder','highlight','essay','spoken'], true)) throw new InvalidArgumentException('This question format is unsupported.');
        nisha_content_text($item['prompt'] ?? '', 'a question prompt', 10000);
        if (!isset($session['resources'][$item['resource_id'] ?? ''])) throw new InvalidArgumentException('A question is missing its source.');
        if (in_array($type, ['choice','multi','reorder'], true)) {
            $options = $item['options'] ?? []; $ids = array_column($options, 'id');
            if (count($options) < 2 || count($options) > 12 || count($ids) !== count(array_unique($ids))) throw new InvalidArgumentException('Provide distinct answer options.');
            foreach ($options as $option) nisha_content_text($option['text'] ?? '', 'an option', 3000);
            $answers = $type === 'choice' ? [$item['answer'] ?? ''] : ($item['answer'] ?? []);
            if (!is_array($answers) || !$answers || count($answers) !== count(array_unique($answers)) || array_diff($answers,$ids)) throw new InvalidArgumentException('Select valid correct answer options.');
            if ($type === 'reorder' && count($answers) !== count($ids)) throw new InvalidArgumentException('The correct order must include every option once.');
            if ($type === 'multi' && (int)($item['select_count'] ?? count($answers)) !== count($answers)) throw new InvalidArgumentException('The selection count must equal the correct answer count.');
        } elseif ($type === 'short') {
            if (!is_array($item['answer'] ?? null) || !$item['answer'] || count($item['answer']) > 20) throw new InvalidArgumentException('Enter at least one accepted answer.');
            foreach ($item['answer'] as $answer) nisha_content_text($answer, 'an accepted answer', 2000);
        } elseif ($type === 'gaps') {
            nisha_content_text($item['text'] ?? '', 'a gap text', 10000);
            if (!is_array($item['blanks'] ?? null) || !$item['blanks'] || count($item['blanks']) > 20) throw new InvalidArgumentException('Provide at least one gap.');
            foreach ($item['blanks'] as $blank) {
                if (!str_contains($item['text'],'[' . $blank['id'] . ']') || !is_array($blank['answer'] ?? null) || !$blank['answer']) throw new InvalidArgumentException('Each gap needs a matching marker and answer.');
                foreach ($blank['answer'] as $answer) nisha_content_text($answer, 'a gap answer', 1000);
            }
        } elseif ($type === 'highlight') {
            $words = preg_split('/\s+/u', trim($item['text'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
            if (!$words || !is_array($item['answer'] ?? null) || !$item['answer']) throw new InvalidArgumentException('Enter the highlighted-word text and correct positions.');
            foreach ($item['answer'] as $position) if (!is_string($position) || !ctype_digit($position) || !isset($words[(int)$position])) throw new InvalidArgumentException('Use valid highlighted-word positions.');
            nisha_content_text($item['correct_text'] ?? '', 'the corrected text', 10000);
        } else {
            if (!is_array($item['rubric'] ?? null) || !$item['rubric']) throw new InvalidArgumentException('Writing and speaking questions need a review rubric.');
            foreach ($item['rubric'] as $criterion) nisha_content_text($criterion, 'a review criterion', 2000);
        }
    }
}

/** Edit teaching fields only; identifiers, type and publication status are server-owned. */
function nisha_content_edit_question(array $session, array $input): array
{
    $item =& $session['sections'][0]['items'][0]; $primary = $item['resource_id'];
    $item['prompt'] = nisha_content_text($input['prompt'] ?? '', 'a question prompt');
    $item['instructions'] = nisha_content_text($input['instructions'] ?? '', 'instructions', 10000, true);
    $item['explanation'] = nisha_content_text($input['explanation'] ?? '', 'an explanation', 10000, in_array($item['type'],['essay','spoken'],true));
    $session['name'] = nisha_content_text($input['name'] ?? '', 'a title', 180);
    foreach ($session['resources'] as $id => &$resource) {
        $resource['title'] = nisha_content_text($input['resource_title'][$id] ?? '', 'a source title', 250);
        if ($resource['kind'] === 'audio') $resource['audio_script'] = nisha_content_text($input['resource_text'][$id] ?? '', 'an audio script', 50000);
        elseif ($resource['kind'] === 'passage') $resource['paragraphs'] = preg_split('/\R\s*\R/u', nisha_content_text($input['resource_text'][$id] ?? '', 'source paragraphs', 50000));
        else {
            $chart = &$resource['chart'];
            foreach ($chart['series'] as $si => &$series) foreach ($series['values'] as $vi => &$value) {
                $raw = $input['chart_values'][$id][$si][$vi] ?? null;
                if (!is_string($raw) || !preg_match('/\A-?[0-9]+(?:\.[0-9]{1,3})?\z/D',$raw)) throw new InvalidArgumentException('Enter numeric chart values.');
                $value = str_contains($raw,'.') ? (float)$raw : (int)$raw;
            }
            unset($series,$value);
        }
    }
    unset($resource);
    if (isset($item['options'])) foreach ($item['options'] as &$option) $option['text'] = nisha_content_text($input['options'][$option['id']] ?? '', 'an answer option', 3000);
    unset($option);
    $answers = static function(mixed $value): array { $text = nisha_content_text($value,'accepted answers',10000); return array_values(array_filter(array_map('trim',preg_split('/\R/u',$text)),static fn($s)=>$s!=='')); };
    if ($item['type'] === 'choice') $item['answer'] = nisha_content_text($input['answers'] ?? '', 'the correct option ID', 20);
    elseif (in_array($item['type'], ['multi','reorder','highlight'], true)) $item['answer'] = array_values(array_filter(array_map('trim',explode(',',nisha_content_text($input['answers'] ?? '', 'correct option IDs', 500)))));
    elseif ($item['type'] === 'short') $item['answer'] = $answers($input['answers'] ?? '');
    elseif ($item['type'] === 'gaps') {
        $item['text'] = nisha_content_text($input['question_text'] ?? '', 'a gap text', 10000);
        foreach ($item['blanks'] as &$blank) $blank['answer'] = $answers($input['gap_answers'][$blank['id']] ?? ''); unset($blank);
    }
    if ($item['type'] === 'highlight') { $item['text'] = nisha_content_text($input['question_text'] ?? '', 'highlight text'); $item['correct_text'] = nisha_content_text($input['correct_text'] ?? '', 'corrected text'); }
    if (in_array($item['type'],['essay','spoken'],true)) $item['rubric'] = $answers($input['rubric'] ?? '');
    $session['review_skill'] = str_replace('generated-','',$session['sections'][0]['id']);
    nisha_content_validate_session($session); return $session;
}

function nisha_content_publish(array $user, string $id, array $session, string $note, bool $attested): int
{
    nisha_content_require_teacher($user); $parsed = nisha_generated_parse_id($id);
    if (!$parsed || !$attested || strlen(trim($note)) < 20 || strlen($note) > 2000 || !preg_match('//u', $note)) throw new InvalidArgumentException('Confirm the teacher review and write a review note of at least 20 characters.');
    if (($session['id'] ?? '') !== $id || ($session['course_id'] ?? '') !== $parsed['course'] || ($session['session_type'] ?? '') !== ($parsed['kind'] === 'question' ? 'practice' : 'full')) throw new InvalidArgumentException('The reviewed task does not match this content reference.');
    nisha_content_validate_session($session);
    $session['teacher_reviewed'] = true; $session['format_note'] = nisha_generated_disclosure();
    $session['version'] = 'reviewed-' . substr(nisha_content_payload_hash($session), 0, 32);
    $hash = nisha_content_payload_hash($session); $semantic = nisha_content_semantic_hash($session); $pdo = db();
    $pdo->beginTransaction();
    try {
        $q = $pdo->prepare("SELECT status FROM teacher_profiles WHERE user_id=? FOR UPDATE"); $q->execute([(int) $user['id']]);
        if ($q->fetchColumn() !== 'approved') throw new InvalidArgumentException('Your teacher approval is no longer active.');
        $dependencies = [];
        if ($parsed['kind'] === 'mock') {
            $reviewedItems = []; $reviewedResources = [];
            foreach (($session['review_dependencies'] ?? []) as $questionId => $dependency) {
                $q = $pdo->prepare("SELECT p.version_id,rv.content_hash,rv.payload_json FROM content_publications p JOIN content_review_versions rv ON rv.id=p.version_id JOIN teacher_profiles tp ON tp.user_id=rv.reviewer_id AND tp.status='approved' WHERE p.content_id=? AND p.kind='question' AND p.status='published' FOR UPDATE");
                $q->execute([$questionId]); $current = $q->fetch();
                if (!$current || (int) $current['version_id'] !== (int) ($dependency['version_id'] ?? 0) || $current['content_hash'] !== ($dependency['content_hash'] ?? '')) throw new InvalidArgumentException('A reviewed question changed. Reload and review the assembled paper again.');
                $dependencies[$questionId] = (int) $current['version_id'];
                $source = json_decode($current['payload_json'], true, 64, JSON_THROW_ON_ERROR);
                $reviewedItems += nisha_exam_items($source); $reviewedResources += $source['resources'];
            }
            if (count($dependencies) !== count(nisha_exam_items($session))) throw new InvalidArgumentException('Every paper question must be bound to its reviewed source version.');
            $actual = nisha_exam_items($session); ksort($actual); ksort($reviewedItems); ksort($reviewedResources); $actualResources=$session['resources']; ksort($actualResources);
            if ($actual !== $reviewedItems || $actualResources !== $reviewedResources) throw new InvalidArgumentException('A paper must contain the exact reviewed questions and sources. Edit and republish the individual questions first.');
        }
        $q = $pdo->prepare('INSERT INTO content_review_versions(content_id,kind,course_id,draft_number,content_hash,semantic_hash,payload_json,reviewer_id,review_note) VALUES (?,?,?,?,?,?,?,?,?)');
        $q->execute([$id,$parsed['kind'],$parsed['course'],$parsed['number'],$hash,$semantic,json_encode($session, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),(int)$user['id'],trim($note)]);
        $version = (int) $pdo->lastInsertId();
        // A duplicate semantic hash for another ID must never update that ID.
        $duplicate = $pdo->prepare('SELECT content_id FROM content_publications WHERE kind=? AND semantic_hash=? LIMIT 1 FOR UPDATE'); $duplicate->execute([$parsed['kind'],$semantic]);
        $other = $duplicate->fetchColumn(); if ($other !== false && $other !== $id) throw new InvalidArgumentException('This content duplicates an already reviewed question or paper. Make a substantive edit before publishing.');
        $q=$pdo->prepare('SELECT content_id FROM content_publications WHERE content_id=? FOR UPDATE'); $q->execute([$id]);
        if($q->fetchColumn()!==false) {
            $q=$pdo->prepare("UPDATE content_publications SET version_id=?,semantic_hash=?,status='published' WHERE content_id=?"); $q->execute([$version,$semantic,$id]);
        } else {
            $q=$pdo->prepare("INSERT INTO content_publications(content_id,kind,course_id,draft_number,version_id,semantic_hash,status) VALUES (?,?,?,?,?,?,'published')"); $q->execute([$id,$parsed['kind'],$parsed['course'],$parsed['number'],$version,$semantic]);
        }
        $q = $pdo->prepare('DELETE FROM content_mock_dependencies WHERE mock_id=?'); $q->execute([$id]);
        foreach ($dependencies as $questionId => $versionId) { $q = $pdo->prepare('INSERT INTO content_mock_dependencies(mock_id,question_id,question_version_id) VALUES (?,?,?)'); $q->execute([$id,$questionId,$versionId]); }
        $pdo->commit(); return $version;
    } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $exception; }
}

function nisha_content_withdraw(array $user, string $id): void
{
    require_once __DIR__ . '/teachers.php';
    if (!nisha_is_admin($user)) nisha_content_require_teacher($user);
    $q = db()->prepare("UPDATE content_publications p JOIN content_review_versions rv ON rv.id=p.version_id SET p.status='withdrawn' WHERE p.content_id=? AND (?=1 OR rv.reviewer_id=?)");
    $q->execute([$id,nisha_is_admin($user) ? 1 : 0,(int)$user['id']]);
    if (!$q->rowCount()) throw new InvalidArgumentException('Choose your own published content or ask the owner to withdraw it.');
}

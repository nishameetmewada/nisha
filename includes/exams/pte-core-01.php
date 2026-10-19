<?php
declare(strict_types=1);

/*
 * Original NISHA PTE Core practice. Shared-format items deliberately come from
 * the same original bank as the Academic mock; this is disclosed to learners.
 * Isolate the required file so its local builders cannot overwrite this file's
 * variables when the catalogue loads both exams in the same PHP scope.
 */
$coreAcademicBank = (static function (): array {
    return require __DIR__ . '/pte-academic-01.php';
})();
$coreSharedItems = [];
foreach ($coreAcademicBank['sections'] as $coreBankSection) {
    foreach ($coreBankSection['items'] as $coreBankItem) {
        $coreSharedItems[$coreBankItem['id']] = $coreBankItem;
    }
}
$coreResources = [];
$coreUsedSources = [];
$coreCopyShared = static function (string $sourceId, string $newId) use (
    $coreSharedItems, $coreAcademicBank, &$coreResources, &$coreUsedSources
): array {
    if (!isset($coreSharedItems[$sourceId]) || isset($coreUsedSources[$sourceId])) {
        throw new RuntimeException('Invalid or repeated shared PTE Core practice item.');
    }
    $coreUsedSources[$sourceId] = true;
    $item = $coreSharedItems[$sourceId];
    $item['id'] = $newId;
    if (isset($item['resource_id'])) {
        $resourceId = $item['resource_id'];
        $coreResources[$resourceId] = $coreAcademicBank['resources'][$resourceId];
    }
    return $item;
};
$coreId = static fn (string $section, int $number): string => 'ptec-' . $section . str_pad((string) $number, 2, '0', STR_PAD_LEFT);

$coreSpeakingWriting = [];
// Six read-aloud passages, ten distinct sentences, four charts, two situations
// and five short spoken questions. No Academic-only task enters this section.
$coreSpeakingSources = array_merge(
    range(1, 16), range(17, 20), [31, 32], range(24, 28)
);
foreach ($coreSpeakingSources as $position => $sourceNumber) {
    $coreSpeakingWriting[] = $coreCopyShared(
        'pte-sw' . str_pad((string) $sourceNumber, 2, '0', STR_PAD_LEFT),
        $coreId('sw', $position + 1)
    );
}

// Core situations use original everyday/workplace stimuli and a configured
// 20-second preparation period rather than the Academic mock's ten seconds.
$coreResources['core-situation-room'] = [
    'kind' => 'audio', 'title' => 'A room booking needs to change',
    'audio_script' => 'You organise a weekly community language club at a local centre. The centre manager tells you that your usual room will be unavailable next Wednesday because of urgent maintenance. A smaller room is available, but it will not hold the entire group. Speak to the manager. Explain what your group needs, suggest a practical alternative and ask how you can confirm the revised arrangement before notifying the members.',
];
$coreResources['core-situation-delivery'] = [
    'kind' => 'audio', 'title' => 'A damaged delivery at work',
    'audio_script' => 'You work in a small shop. A delivery of display stands has arrived with two damaged parts, and a promotion starts tomorrow morning. The supplier can send replacements, but cannot guarantee that they will arrive in time. Speak to the supplier’s service agent. Describe the problem, explain the deadline and propose a temporary solution while asking for written confirmation of the replacement plan.',
];
$coreSituationResources = ['core-situation-room', 'core-situation-delivery'];
$coreSituationRubrics = [
    [
        'Explain the group’s space needs and the consequence of losing the usual room.',
        'Offer a workable alternative, such as another suitable time or two smaller sessions, and ask how to confirm it.',
        'Use polite, clear language to the centre manager. Review fluency and pronunciation yourself; this task is ungraded.',
    ],
    [
        'Describe the damaged parts and make the promotion deadline clear.',
        'Propose a reasonable temporary arrangement and request written confirmation of replacements.',
        'Use an appropriate professional register, with a clear request and closing. No automated speaking score is awarded.',
    ],
];
foreach ([20, 21] as $offset => $itemIndex) {
    $coreSpeakingWriting[$itemIndex]['resource_id'] = $coreSituationResources[$offset];
    $coreSpeakingWriting[$itemIndex]['title'] = 'Respond to a Situation ' . ($offset + 1);
    $coreSpeakingWriting[$itemIndex]['prompt'] = 'Listen to the situation, then respond directly to the person described. Explain the problem and propose a useful next step.';
    $coreSpeakingWriting[$itemIndex]['prep_seconds'] = 20;
    $coreSpeakingWriting[$itemIndex]['response_seconds'] = 40;
    $coreSpeakingWriting[$itemIndex]['cues'] = ['Address all three purposes in the situation.', 'Speak as the person in the scenario, using a suitable polite register.'];
    $coreSpeakingWriting[$itemIndex]['rubric'] = $coreSituationRubrics[$offset];
}
// The replaced Academic stimuli are not part of the Core candidate data.
unset($coreResources['situation-lab'], $coreResources['situation-work']);

$coreResources['core-swt-training'] = [
    'kind' => 'passage', 'title' => 'A more useful approach to staff training',
    'paragraphs' => [
        'Harbour Services used to give every new employee the same half-day introduction to its booking software. Experienced users found much of the session repetitive, while beginners sometimes left without enough practice. The training coordinator replaced most of the presentation with a short skills check followed by activities matched to each person’s needs. All staff still attend a common briefing on privacy, security and where to seek help.',
        'The skills check is used to plan support, not to judge an employee’s overall ability. People can request additional practice even if they complete it successfully. Short drop-in sessions are available during the first month, when staff encounter real booking problems. Supervisors also record recurring questions so the guidance can be improved. Early feedback suggests that employees value the flexibility, but the company will review error rates and access to support before deciding whether the new approach is more effective. In particular, it wants to ensure that workers on evening shifts have the same opportunities for help as daytime staff.',
    ],
];
$coreSpeakingWriting[] = [
    'id' => $coreId('sw', 28), 'type' => 'essay', 'task_type' => 'Summarize Written Text',
    'title' => 'Summarize Written Text · Staff training', 'resource_id' => 'core-swt-training',
    'prompt' => 'Summarise the passage in 25–50 words. Include the main change to staff training and the important conditions for making it useful.',
    'instructions' => 'Configured Core practice: 10 minutes. Write a concise summary in complete sentences; preserve the meaning and avoid adding your own recommendations.',
    'min_words' => 25, 'max_words' => 50, 'suggested_seconds' => 600,
    'rubric' => [
        'Explain the move from identical introductory training to support matched to staff needs.',
        'Include important support, such as the shared security briefing, ongoing help or fair access, without listing every detail.',
        'Distinguish encouraging feedback from a proven improvement; the company is still evaluating the change.',
        'Use 25–50 words with accurate grammar, vocabulary and spelling. This writing is ungraded.',
    ],
    'explanation' => 'One possible summary: Harbour Services now matches software training to employees’ needs while retaining a shared security briefing and ongoing support. It is assessing errors and access to help, including for evening workers, before judging the new approach’s effectiveness.',
];
$coreSpeakingWriting[] = [
    'id' => $coreId('sw', 29), 'type' => 'essay', 'task_type' => 'Write Email',
    'title' => 'Write Email · Arrange a shift swap',
    'prompt' => 'You work at a community sports centre. You are scheduled to work on Saturday morning, but a family appointment now prevents you from attending. Write an email to your supervisor, Elena, in which you explain the scheduling problem, propose a specific shift swap or alternative and say how you will make sure your duties are covered.',
    'instructions' => 'Configured Core practice: 9 minutes. Write 50–120 words in a suitable email format. Include a greeting and closing. Use invented ordinary details where needed; do not provide personal contact information.',
    'min_words' => 50, 'max_words' => 120, 'suggested_seconds' => 540,
    'rubric' => [
        'Explain the conflict clearly and early without unnecessary personal detail.',
        'Propose a specific, workable alternative and request the supervisor’s agreement rather than assuming approval.',
        'Explain how the opening duties, handover or customer bookings will be covered.',
        'Use a polite professional tone, a greeting and closing, and 50–120 words. Review grammar and spelling yourself.',
    ],
    'explanation' => 'A successful email covers the reason for the request, a practical alternative shift and a clear coverage plan. Several arrangements could be reasonable; no single model email is treated as a canonical answer.',
];
$coreSpeakingWriting[] = [
    'id' => $coreId('sw', 30), 'type' => 'essay', 'task_type' => 'Write Email',
    'title' => 'Write Email · Report a household repair',
    'prompt' => 'You rent an apartment. The kitchen tap has been leaking for several days, and the cupboard beneath it is now damp. Write an email to your property manager, Morgan, in which you describe the problem and its effect, request a suitable repair arrangement and explain when someone can provide access to the apartment.',
    'instructions' => 'Configured Core practice: 9 minutes. Write 50–120 words in a suitable email format. Include a greeting and closing. Use invented ordinary details where needed; do not include a real home address or phone number.',
    'min_words' => 50, 'max_words' => 120, 'suggested_seconds' => 540,
    'rubric' => [
        'Describe both the leaking tap and the damp cupboard so the manager understands the concern.',
        'Request a repair and give a clear access window or a practical way to arrange it.',
        'Use a polite, appropriately firm tone without inventing legal claims or unrelated complaints.',
        'Include a greeting and closing, stay within 50–120 words and check language accuracy. This response is ungraded.',
    ],
    'explanation' => 'The task requires a clear problem, a repair request and access arrangements. Details such as an available afternoon and a request to confirm the appointment can make the email actionable; alternative sensible details are acceptable.',
];

$coreReading = [];
for ($coreNumber = 1; $coreNumber <= 15; ++$coreNumber) {
    $coreReading[] = $coreCopyShared('pte-r' . str_pad((string) $coreNumber, 2, '0', STR_PAD_LEFT), $coreId('r', $coreNumber));
}

$coreResources['core-sst-parcels'] = [
    'kind' => 'audio', 'title' => 'A new parcel collection arrangement',
    'audio_script' => 'From next Monday, parcels for residents in our apartment building will be stored in the secure room beside reception instead of being left in the entrance hall. Delivery drivers will leave an arrival notice in your mailbox. Please bring that notice and some identification when collecting a parcel. Reception staff can provide access between seven in the morning and eight in the evening. If your working hours make collection difficult, you may nominate another adult in advance by completing a short form. We are introducing this arrangement because unattended parcels have occasionally blocked the doorway, and several residents have reported missing deliveries. Large items that cannot fit safely in the room must still be delivered directly to your apartment by prior arrangement. The residents’ committee will review the system after a month and welcomes comments about access or collection times.',
];
$coreListening = [[
    'id' => $coreId('l', 1), 'type' => 'essay', 'task_type' => 'Summarize Spoken Text',
    'title' => 'Summarize Spoken Text · Parcel collection', 'resource_id' => 'core-sst-parcels',
    'prompt' => 'Listen to the announcement and summarise its main message in 20–30 words. Include the new arrangement and the important information residents need.',
    'instructions' => 'Configured Core practice: 8 minutes, including listening and writing. Listen once in exam mode and write a concise summary in complete sentences.',
    'min_words' => 20, 'max_words' => 30, 'suggested_seconds' => 480,
    'rubric' => [
        'Identify the move to secure parcel storage and the collection procedure or access arrangement.',
        'Choose the most important details; the word limit does not allow every exception and review date.',
        'Preserve the meaning without adding unsupported requirements or opinions.',
        'Write 20–30 words with clear grammar and spelling. This task is ungraded and needs self-review.',
    ],
    'explanation' => 'One possible summary: Parcels will move to secure storage beside reception. Residents need an arrival notice and identification, with advance arrangements available for collection by another adult.',
]];
// Seven Core listening formats: omit Academic Highlight Correct Summary and
// replace its longer Summarize Spoken Text with the original Core announcement.
$coreListeningSources = [2, 3, 4, 5, 6, 9, 10, 12, 13, 14, 15, 16, 17, 18];
foreach ($coreListeningSources as $position => $sourceNumber) {
    $coreListening[] = $coreCopyShared('pte-l' . str_pad((string) $sourceNumber, 2, '0', STR_PAD_LEFT), $coreId('l', $position + 2));
}

return [
    'id' => 'pte-core-01', 'exam' => 'PTE', 'name' => 'PTE Core · Full mock 01',
    'version' => '2026-10-library-01',
    'description' => 'A 60-item Core practice route covering all 19 task formats, with everyday and workplace writing, spoken situations and a parcel-service announcement. Includes shared original PTE skills practice.',
    'format_note' => 'Original, unofficial PTE Core practice. Configured mock budgets are 60 minutes Speaking & Writing, 30 minutes Reading and 30 minutes Listening (120 minutes total); these are practice settings, not a claim about a universal official test length or item count. The current official format and timing have not been independently verified for this release. Shared-format questions deliberately reuse the original NISHA PTE Academic practice bank, so taking both routes exposes some of the same questions; these are not independent unseen papers. Core-specific written and spoken summaries, emails and situation stimuli are original additions. Writing and speaking require self-review. Objective results are raw practice points, not official PTE 10–90 or integrated skill scores. Dictation uses simplified whole-sentence marking.',
    'resources' => $coreResources,
    'sections' => [
        [
            'id' => 'pte-core-speaking-writing', 'title' => 'Speaking & Writing',
            'duration_seconds' => 3600, 'navigation' => 'forward',
            'instructions' => 'Complete 30 tasks in order within the configured 60-minute budget. The seven task formats are Read Aloud, Repeat Sentence, Describe Image, Respond to a Situation, Answer Short Question, Summarize Written Text and Write Email. Use the displayed preparation and response limits. For situations, this mock allows 20 seconds of preparation and 40 seconds of speaking. Write 25–50 words for the 10-minute written summary and 50–120 words for each 9-minute email. Spoken answers and writing are ungraded; use the self-review guidance after finishing. Shared-format items overlap with the Academic practice bank.',
            'items' => $coreSpeakingWriting,
        ],
        [
            'id' => 'pte-core-reading', 'title' => 'Reading',
            'duration_seconds' => 1800, 'navigation' => 'forward',
            'instructions' => 'Complete 15 shared-bank tasks in order within the configured 30-minute budget. The five formats are Reading & Writing: Fill in the Blanks, Multiple Choice with Multiple Answers, Reorder Paragraphs, Reading: Fill in the Blanks and Multiple Choice with a Single Answer. Wrong multiple selections deduct practice points down to zero. Reorder tasks award points for correct adjacent paragraph pairs. Each correct gap or single-choice response earns one raw practice point. These skills passages are shared with the Academic practice route.',
            'items' => $coreReading,
        ],
        [
            'id' => 'pte-core-listening', 'title' => 'Listening',
            'duration_seconds' => 1800, 'navigation' => 'forward',
            'instructions' => 'Complete 15 tasks in order within the configured 30-minute budget. Allow up to 8 minutes, including audio, for the 20–30-word spoken summary. The other formats are Multiple Choice with Multiple Answers, Fill in the Blanks, Multiple Choice with a Single Answer, Select Missing Word, Highlight Incorrect Words and Write from Dictation. Listen once in exam mode. Multiple selections and incorrect-word selections deduct for errors down to zero. Dictation uses one practice point per complete correct sentence after normalisation, not official word-level scoring. Other than the Core announcement, listening stimuli are shared with the Academic bank.',
            'items' => $coreListening,
        ],
    ],
];

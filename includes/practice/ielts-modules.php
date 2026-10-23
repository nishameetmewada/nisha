<?php
declare(strict_types=1);

// Original short practice sets supplement the longer canonical mock banks.
// These are focused exercises, not additional full unseen examinations.
$modules = [];
$both = ['ielts-academic', 'ielts-general'];
$add = static function (string $id, string $title, string $skill, array $courses, array $resources, array $items) use (&$modules): void {
    $modules[] = ['id' => $id, 'title' => $title, 'skill' => $skill, 'course_ids' => $courses, 'resources' => $resources, 'items' => $items];
};
$opts = static function (array $texts): array {
    $out = [];
    foreach ($texts as $key => $text) $out[] = ['id' => (string) $key, 'text' => $text];
    return $out;
};
$short = static function (string $id, string $task, string $resource, string $prompt, array $answers, string $explanation, int $max = 1): array {
    return ['id' => $id, 'type' => 'short', 'task_type' => $task, 'resource_id' => $resource, 'title' => $task, 'prompt' => $prompt, 'instructions' => $max === 1 ? 'Write ONE WORD AND/OR A NUMBER.' : 'Write NO MORE THAN TWO WORDS AND/OR A NUMBER.', 'max_words' => $max, 'answer' => $answers, 'explanation' => $explanation];
};
$choice = static function (string $id, string $task, string $resource, string $prompt, array $options, string $answer, string $explanation) use ($opts): array {
    return ['id' => $id, 'type' => 'choice', 'task_type' => $task, 'resource_id' => $resource, 'title' => $task, 'prompt' => $prompt, 'instructions' => 'Choose ONE answer. Options may be used more than once unless stated otherwise.', 'options' => $opts($options), 'answer' => $answer, 'explanation' => $explanation];
};
$gaps = static function (string $id, string $task, string $resource, string $text, array $answers, string $explanation): array {
    $blanks = [];
    foreach ($answers as $key => $answer) $blanks[] = ['id' => (string) $key, 'answer' => (array) $answer];
    return ['id' => $id, 'type' => 'gaps', 'task_type' => $task, 'title' => $task, 'resource_id' => $resource, 'prompt' => 'Complete the information using ONE WORD AND/OR A NUMBER for each blank.', 'instructions' => 'Use words or numbers from the supplied text or recording. Each blank earns one raw practice point.', 'text' => $text, 'blanks' => $blanks, 'explanation' => $explanation];
};
$essay = static function (string $id, string $task, string $prompt, int $minWords, array $extra = []): array {
    return array_merge(['id' => $id, 'type' => 'essay', 'task_type' => $task, 'title' => $task, 'prompt' => $prompt, 'instructions' => 'Write at least ' . $minWords . ' words in connected prose. Request AI coaching when enabled for a practice estimate and advice, or use the review checklist.', 'min_words' => $minWords, 'suggested_seconds' => $minWords === 150 ? 1200 : 2400, 'rubric' => ['Address every part of the task and organise the response into coherent paragraphs.', 'Select relevant detail or develop reasons; do not add unsupported information.', 'Review vocabulary, grammar, spelling and the minimum word count.']], $extra);
};

// LISTENING: multiple answers, visual labelling and completion formats.
$task = 'Multiple-answer multiple choice';
$add('listening-multiple', $task, 'listening', $both, [
    'im-l-multiple' => ['kind' => 'audio', 'title' => 'Joining a community workshop', 'audio_script' => 'Coordinator: Before you join the community workshop, let me explain the membership arrangements. The annual fee includes use of our hand tools, so you do not need to buy your own basic kit. Materials such as timber and replacement blades are charged separately. We used to open only on weekdays, but members can now book a Saturday morning session as well. Every new member must complete the safety induction, even if they have used similar equipment elsewhere. The induction is free, but it is not a qualification that allows you to teach other people. Storage is limited, so projects cannot normally be left here for more than a week. Finally, we can offer advice, but members are responsible for deciding whether a design will meet their needs. We do not guarantee a successful result.'],
], [[
    'id' => 'im-l-multiple01', 'type' => 'multi', 'task_type' => $task, 'title' => $task, 'resource_id' => 'im-l-multiple', 'prompt' => 'Which TWO benefits are included in workshop membership?', 'instructions' => 'Choose TWO answers. This focused item earns one point only when both correct options, and no others, are selected.', 'select_count' => 2, 'all_or_nothing' => true,
    'options' => $opts(['a' => 'Use of basic hand tools', 'b' => 'Unlimited free construction materials', 'c' => 'Access to a Saturday morning session', 'd' => 'Exemption from the safety induction', 'e' => 'A guarantee that every project will succeed']), 'answer' => ['a', 'c'], 'explanation' => 'The fee includes hand tools and members can book Saturday mornings. Materials cost extra, induction is compulsory and results are not guaranteed.',
]]);

$task = 'Map / plan / diagram labelling';
$mapItems = [];
foreach ([
    ['im-l-map01', 'Which letter marks the computer room?', ['a'], 'The speaker places the computer room to the left of reception, which is room A on the plan.'],
    ['im-l-map02', 'Which letter marks the local history room?', ['c'], 'The local history room is straight ahead beyond reception, in the room at the top of the plan, C.'],
    ['im-l-map03', 'Which letter marks the children’s room?', ['b'], 'The children’s room is immediately to the right of reception, labelled B.'],
] as $q) {
    $item = $short($q[0], $task, 'im-l-map-audio', $q[1], $q[2], $q[3]);
    $item['instructions'] = 'Write the correct letter, A, B or C. Each letter is used once.';
    $item['secondary_resource_id'] = 'im-l-map-plan';
    $mapItems[] = $item;
}
$add('listening-plan', $task, 'listening', $both, [
    'im-l-map-audio' => ['kind' => 'audio', 'title' => 'Finding rooms in a small library', 'audio_script' => 'Welcome to the neighbourhood library. Look at the plan with the entrance at the bottom. The plan shows only the main public rooms, not staff offices or toilets. Walk in from the entrance and go straight along the corridor to reception in the centre. You can collect a library card there. Immediately to your left is the computer room. It contains six machines and a printer; please ask before connecting your own device. The room directly opposite it, on your right as you face reception, is the children’s room. Story sessions happen there on Tuesday mornings, so it may be lively then. To reach the local history room, continue straight ahead past reception. It is the room at the top of your plan. Historical photographs and street directories are kept there, and food is not permitted. The lines on the plan show the public routes between rooms. If you need help finding a particular collection, return to reception rather than entering a staff-only doorway.'],
    'im-l-map-plan' => ['kind' => 'diagram', 'title' => 'Library floor plan', 'diagram' => ['description' => 'Entrance at the bottom; reception in the centre. Room A is left of reception, room B is right of reception, and room C is straight ahead at the top. Routes connect reception to each room. Directions are relative to entering from the bottom. Room functions must be identified from the recording.', 'nodes' => [
        ['id' => 'entrance', 'label' => 'Entrance', 'x' => 36, 'y' => 82, 'width' => 28, 'height' => 10],
        ['id' => 'reception', 'label' => 'Reception', 'x' => 36, 'y' => 44, 'width' => 28, 'height' => 12],
        ['id' => 'room-a', 'label' => 'A', 'x' => 4, 'y' => 44, 'width' => 22, 'height' => 12],
        ['id' => 'room-b', 'label' => 'B', 'x' => 74, 'y' => 44, 'width' => 22, 'height' => 12],
        ['id' => 'room-c', 'label' => 'C', 'x' => 36, 'y' => 8, 'width' => 28, 'height' => 12],
    ], 'edges' => [['from' => 'entrance', 'to' => 'reception'], ['from' => 'reception', 'to' => 'room-a'], ['from' => 'reception', 'to' => 'room-b'], ['from' => 'reception', 'to' => 'room-c']]]],
], $mapItems);

$task = 'Table completion';
$item = $gaps('im-l-table01', $task, 'im-l-table-audio', "Course | Day | Room\nPhotography | {{g1}} | Cedar\nGardening | Thursday | {{g2}}\nBookbinding | Saturday | {{g3}}", ['g1' => ['tuesday'], 'g2' => ['willow'], 'g3' => ['8', 'eight']], 'Photography is on Tuesday, gardening uses Willow and bookbinding meets in room eight. The alternatives mentioned in the recording are explicitly rejected.');
$item['secondary_resource_id'] = 'im-l-table-grid';
$add('listening-table', $task, 'listening', $both, [
    'im-l-table-audio' => ['kind' => 'audio', 'title' => 'Three short courses', 'audio_script' => 'Here are the confirmed arrangements for this month’s short courses. Photography will be on Tuesday, not Monday as the first leaflet suggested. It remains in the Cedar room, because the larger windows are useful. Gardening meets on Thursday. That class has moved from the outdoor shelter into the Willow room while repairs take place in the garden. Please bring a clean pair of shoes so soil is not carried across the carpet. Bookbinding takes place on Saturday in room eight. The old timetable lists room six, but that room is now used for storage. All three courses start at ten, and the reception desk opens half an hour earlier. If you are unsure which door to use, ask at reception rather than following a different group.'],
    'im-l-table-grid' => ['kind' => 'table', 'title' => 'Complete the course schedule', 'columns' => ['Course', 'Day', 'Room'], 'rows' => [['Photography', 'Blank 1', 'Cedar'], ['Gardening', 'Thursday', 'Blank 2'], ['Bookbinding', 'Saturday', 'Blank 3']]],
], [$item]);

$task = 'Flowchart completion';
$add('listening-flowchart', $task, 'listening', $both, [
    'im-l-flow-audio' => ['kind' => 'audio', 'title' => 'Submitting a design project', 'audio_script' => 'To submit your design project, first upload a short outline to the course website. Do not send your finished model at this stage. Your tutor will read the outline and arrange a meeting if changes are needed. Once the outline is approved, book a workshop induction, even if you have used the room before. The machines have changed since last term. After the induction, you may construct a prototype. Keep photographs of important stages because these will support your final explanation. Test the prototype with a small group of users and record what they find difficult. Finally, submit a written evaluation together with the photographs. The prototype itself should stay in your allocated storage space until the exhibition.'],
], [$gaps('im-l-flow01', $task, 'im-l-flow-audio', 'Upload an {{g1}} → receive tutor approval → attend the workshop {{g2}} → construct and test a prototype → submit a written {{g3}} with photographs.', ['g1' => ['outline'], 'g2' => ['induction'], 'g3' => ['evaluation']], 'The recording sets out the sequence: outline, approval, induction, prototype testing and a final written evaluation.')]);

$task = 'Summary completion';
$add('listening-summary', $task, 'listening', $both, [
    'im-l-summary-audio' => ['kind' => 'audio', 'title' => 'A trial bicycle library', 'audio_script' => 'Our bicycle library lends several types of cycle to residents for a week. The aim is to let people test a real journey before deciding whether to buy a bicycle. Someone may be confident on a quiet path but uncertain about travelling to work with a laptop and groceries. Staff therefore ask borrowers to keep a diary of the journeys they actually make. The diary is not a fitness competition; a short trip to a shop can tell us as much as a long weekend ride. We also provide basic maintenance advice and a telephone number for mechanical problems. At the end of the loan, borrowers discuss barriers such as storage, traffic or cost. The evaluation will use these conversations alongside booking records. A high number of loans alone would not show whether cycling has become a practical option.'],
], [$gaps('im-l-summary01', $task, 'im-l-summary-audio', 'Residents borrow a bicycle for a week to test an everyday {{g1}}. They keep a {{g2}} describing actual trips, and staff provide advice on basic {{g3}}. Evaluation includes conversations about barriers as well as booking records.', ['g1' => ['journey'], 'g2' => ['diary'], 'g3' => ['maintenance']], 'The aim is to test a real journey, borrowers keep a diary and staff offer maintenance advice. The scheme is not described as a fitness competition.')]);

$task = 'Sentence completion';
$add('listening-sentences', $task, 'listening', $both, [
    'im-l-sentence-audio' => ['kind' => 'audio', 'title' => 'Preparing for a field visit', 'audio_script' => 'For tomorrow’s field visit, meet outside the main entrance at eight thirty. The coach cannot wait for late arrivals. We will walk through a wet meadow, so boots are essential; ordinary trainers will not be suitable after this week’s rain. Bring a packed lunch and enough drinking water for the whole day. We will lend everyone a clipboard, but please bring your own pencil, since ink may run if the paper gets damp. At the visitor centre we will divide into groups. Each group will measure one section of the stream before comparing its findings with the others.'],
], [
    $short('im-l-sentence01', $task, 'im-l-sentence-audio', 'Participants must wear ____ because the meadow is wet.', ['boots'], 'The tutor calls boots essential and rejects ordinary trainers.'),
    $short('im-l-sentence02', $task, 'im-l-sentence-audio', 'Each participant should bring a ____ because ink may run on damp paper.', ['pencil'], 'A pencil is requested; clipboards are supplied by the organisers.'),
]);

$task = 'Short-answer questions';
$add('listening-short-answers', $task, 'listening', $both, [
    'im-l-short-audio' => ['kind' => 'audio', 'title' => 'An evening at the observatory', 'audio_script' => 'Our public observing evening begins with a short talk in the classroom. If the sky is clear, we then move to the roof terrace to use the telescopes. The building is accessible by lift, but the terrace can be cold even in spring, so bring a jacket. Please leave large bags in the lockers beside reception. You do not need a coin for these lockers; collect a key from the receptionist and return it before leaving. The event finishes at nine thirty. When you go outside, avoid using a bright torch near the telescopes because other visitors’ eyes need time to adjust to the darkness.'],
], [
    $short('im-l-short01', $task, 'im-l-short-audio', 'Where do visitors leave large bags?', ['lockers'], 'Large bags go in the lockers beside reception.'),
    $short('im-l-short02', $task, 'im-l-short-audio', 'What item should visitors bring to stay warm?', ['jacket'], 'Visitors are told to bring a jacket because the roof terrace can be cold.'),
    $short('im-l-short03', $task, 'im-l-short-audio', 'What must visitors return before leaving?', ['key'], 'The receptionist lends a locker key, which must be returned before departure.'),
]);

// READING: explicit matching and completion formats.
$task = 'Matching features';
$features = ['a' => 'Dr Lane', 'b' => 'Professor Ortiz', 'c' => 'Dr Chen'];
$add('reading-features', $task, 'reading', $both, [
    'im-r-features' => ['kind' => 'passage', 'title' => 'Three approaches to reducing travel emissions', 'paragraphs' => [
        'At a transport forum, Dr Lane argued that a reliable basic service should come before promotional campaigns. People cannot choose a bus that does not run when their shift ends. Her research combines route timetables with interviews about journeys that residents currently make by car.',
        'Professor Ortiz focused on street design. He noted that a painted cycle lane can disappear at precisely the junction where an inexperienced rider needs protection. He recommended testing complete routes, including crossings and places to park a bicycle securely, instead of measuring success only by kilometres of lane installed.',
        'Dr Chen examined travel information. She found that new residents often received several separate maps that did not explain how different services connected. Her team tested a single guide showing typical journeys and accessible transfer points. She stressed that better information cannot replace an absent service, but it can make existing options easier to use.',
    ]],
], [
    $choice('im-r-features01', $task, 'im-r-features', 'Who emphasises whether transport operates at the times people need it?', $features, 'a', 'Dr Lane highlights shifts ending after services stop and calls for a reliable basic service.'),
    $choice('im-r-features02', $task, 'im-r-features', 'Who argues for evaluating a whole cycling journey rather than a length of lane?', $features, 'b', 'Professor Ortiz examines junctions, crossings and secure parking along complete routes.'),
    $choice('im-r-features03', $task, 'im-r-features', 'Who developed a combined guide to connections between services?', $features, 'c', 'Dr Chen’s team tested one guide containing typical journeys and transfer points.'),
]);

$task = 'Matching sentence endings';
$endings = ['a' => 'can hide important differences between groups.', 'b' => 'allows misunderstandings to be identified before a large study.', 'c' => 'should be removed whenever they conflict with a prediction.', 'd' => 'helps readers judge whether a conclusion is justified.', 'e' => 'guarantees that every participant will give the same answer.'];
$add('reading-sentence-endings', $task, 'reading', $both, [
    'im-r-endings' => ['kind' => 'passage', 'title' => 'Explaining survey evidence', 'paragraphs' => [
        'A survey report should describe how questions were tested, how participants were recruited and which answers are missing. A pilot with a small group can reveal that a phrase understood by researchers carries a different meaning for respondents. Correcting that problem early is usually easier than interpreting thousands of uncertain responses later.',
        'Reporting only an overall average may also be misleading. An apparently moderate waiting time could combine very short waits for one group with lengthy delays for another. Separating relevant groups helps reveal these patterns, provided the categories have a clear purpose and do not identify individuals. Finally, an account of the method lets readers assess the strength and limits of a claim. Unusual findings should be investigated, not quietly removed to make the result agree with an expectation.',
    ]],
], [
    $choice('im-r-endings01', $task, 'im-r-endings', 'Complete: Testing questions with a small pilot group …', $endings, 'b', 'The pilot reveals differences in understanding before thousands of responses are collected.'),
    $choice('im-r-endings02', $task, 'im-r-endings', 'Complete: Publishing only an overall average …', $endings, 'a', 'The waiting-time example shows how an average can conceal unequal experiences.'),
    $choice('im-r-endings03', $task, 'im-r-endings', 'Complete: Describing the research method …', $endings, 'd', 'A method account helps readers assess the strength and limits of the conclusion.'),
]);

$task = 'Summary completion';
$add('reading-summary', $task, 'reading', $both, [
    'im-r-summary' => ['kind' => 'passage', 'title' => 'What a small urban pond can do', 'paragraphs' => [
        'A small pond can support wildlife even in a densely built neighbourhood, but its design affects which species can use it. Sloping edges allow animals to leave the water and provide shallow areas where different plants can grow. A pond with steep smooth walls may trap small animals. Location matters too: rainwater running directly from a busy road can carry pollutants into the habitat.',
        'Maintenance should preserve some variation rather than make every part equally tidy. Removing all vegetation at once can eliminate shelter, while leaving every fallen leaf in a very small pond may reduce water quality as the material decays. Managers therefore plan work in stages. They also monitor the pond over several seasons, because a photograph taken in summer cannot show whether the habitat remains useful through the rest of the year.',
    ]],
], [$gaps('im-r-summary01', $task, 'im-r-summary', 'Ponds benefit from {{g1}} edges that help animals leave the water. Runoff from roads may bring {{g2}}. Maintenance is best carried out in {{g3}} to retain habitat variation.', ['g1' => ['sloping'], 'g2' => ['pollutants'], 'g3' => ['stages']], 'The text recommends sloping edges, warns about pollutants and describes maintenance planned in stages.')]);

$task = 'Note completion';
$add('reading-notes', $task, 'reading', $both, [
    'im-r-notes' => ['kind' => 'passage', 'title' => 'Using oral history responsibly', 'paragraphs' => [
        'An oral history interview records how a person remembers and interprets past events. Before recording, explain the project’s purpose and obtain consent. Agree whether the participant’s name may appear in public material and what will happen to the recording after the project ends. The interview should allow time for unexpected details rather than force every answer into a rigid questionnaire.',
        'Afterwards, prepare a transcript that can be searched and checked. A transcript does not capture every feature of a voice, so the original recording remains valuable. Memories may conflict with documents or with another person’s account. Such differences should be recorded as part of the evidence, not silently corrected. Notes about the interview’s date and setting help later users understand the circumstances in which the account was produced.',
    ]],
], [$gaps('im-r-notes01', $task, 'im-r-notes', "Before recording: obtain {{g1}}.\nAfter recording: produce a searchable {{g2}}.\nPreserve conflicting {{g3}} rather than silently changing them.", ['g1' => ['consent'], 'g2' => ['transcript'], 'g3' => ['memories']], 'Consent is needed before recording; the searchable record is a transcript; conflicting memories remain part of the evidence.')]);

$task = 'Table completion';
$item = $gaps('im-r-table01', $task, 'im-r-table-text', "Service | Main purpose | Access\nTool library | Borrow equipment | Annual {{g1}}\nRepair café | Learn through repair | Stay with your {{g2}}\nMaterials exchange | Reuse leftover supplies | Check the online {{g3}}", ['g1' => ['membership'], 'g2' => ['object'], 'g3' => ['inventory']], 'The tool library requires annual membership; repair café visitors stay with their object; the exchange publishes an online inventory.');
$item['secondary_resource_id'] = 'im-r-table-grid';
$add('reading-table', $task, 'reading', $both, [
    'im-r-table-text' => ['kind' => 'passage', 'title' => 'Three ways to reuse local resources', 'paragraphs' => [
        'The tool library lends equipment for short periods to people with an annual membership. Tools must be returned clean, and staff demonstrate basic use before a first loan. It is a borrowing service rather than a place to leave broken possessions.',
        'At the repair café, a volunteer and visitor investigate a damaged object together. Visitors must stay with their object and should be prepared to take it home even if no repair is possible. The aim includes learning, not simply obtaining free labour.',
        'The materials exchange accepts reusable leftovers such as lengths of clean timber and unopened craft supplies. Its online inventory shows what is currently available. Check that list before travelling, because staff cannot guarantee that a photographed item will remain on the shelf. Electrical waste and opened chemical containers are not accepted.',
    ]],
    'im-r-table-grid' => ['kind' => 'table', 'title' => 'Local reuse services', 'columns' => ['Service', 'Main purpose', 'Access requirement'], 'rows' => [['Tool library', 'Borrow equipment', 'Annual blank 1'], ['Repair café', 'Learn through repair', 'Stay with your blank 2'], ['Materials exchange', 'Reuse supplies', 'Check online blank 3']]],
], [$item]);

$task = 'Flowchart completion';
$add('reading-flowchart', $task, 'reading', $both, [
    'im-r-flow-text' => ['kind' => 'passage', 'title' => 'From donated photograph to archive record', 'paragraphs' => [
        'When a photograph arrives at the community archive, a volunteer first assigns a reference number. That number stays with the image throughout processing, even if the date or subject is later corrected. The volunteer then obtains permission from the owner for the intended uses. An image cannot be published merely because somebody has lent it for scanning.',
        'Once permission is recorded, the photograph is scanned at the archive’s standard resolution. A second volunteer checks the scan against the original before a catalogue entry is prepared. The entry includes the reference number, known details and any uncertainties. Finally, the digital files are copied to separate storage for backup. The original photograph is returned unless the owner has formally donated it. These steps preserve both the image and the information needed to use it responsibly.',
    ]],
], [$gaps('im-r-flow01', $task, 'im-r-flow-text', 'Assign a reference {{g1}} → obtain the owner’s {{g2}} → scan → check scan → prepare a catalogue entry → create a {{g3}} → return or retain the original according to the agreement.', ['g1' => ['number'], 'g2' => ['permission'], 'g3' => ['backup']], 'The article explicitly orders reference numbering, permission, scanning/checking, cataloguing and backup before the original is dealt with.')]);

$task = 'Diagram label completion';
$diagramItems = [];
foreach ([
    ['im-r-diagram01', 'Label A: What material forms the upper layer?', ['gravel'], 'The upper layer of the filter is gravel, which catches larger particles.'],
    ['im-r-diagram02', 'Label B: What material forms the middle layer?', ['sand'], 'The middle layer is sand, positioned below the gravel.'],
    ['im-r-diagram03', 'Label C: Through what does treated water leave?', ['outlet'], 'The final paragraph states that treated water leaves through an outlet at the base.'],
] as $q) {
    $item = $short($q[0], $task, 'im-r-diagram-text', $q[1], $q[2], $q[3]);
    $item['instructions'] = 'Choose ONE WORD ONLY from the passage for each labelled part.';
    $item['secondary_resource_id'] = 'im-r-filter-plan';
    $diagramItems[] = $item;
}
$add('reading-diagram', $task, 'reading', $both, [
    'im-r-diagram-text' => ['kind' => 'passage', 'title' => 'A demonstration filter', 'paragraphs' => [
        'This classroom model demonstrates how different materials can remove suspended particles from water. It must not be used to produce drinking water: removing visible dirt does not guarantee removal of harmful organisms or dissolved chemicals. The container is transparent so students can compare layers as water passes through.',
        'At the top is a layer of gravel, which catches larger particles. Directly below it is a layer of sand that traps finer material. A mesh support beneath these two layers prevents them from falling into the lower collection space. Water enters at the top and moves downwards under gravity. Treated water leaves through an outlet at the base. Students can compare the appearance of incoming and outgoing water, but any claim about safety would require additional testing.',
    ]],
    'im-r-filter-plan' => ['kind' => 'diagram', 'title' => 'Label the demonstration filter', 'diagram' => ['description' => 'Water moves down from the inlet through layers A and B, then past a mesh support to a collection space. C is the component through which water leaves at the base. The diagram does not indicate that the water is safe to drink.', 'nodes' => [
        ['id' => 'inlet', 'label' => 'Water in', 'x' => 30, 'y' => 2, 'width' => 40, 'height' => 10],
        ['id' => 'layer-a', 'label' => 'A', 'x' => 20, 'y' => 20, 'width' => 60, 'height' => 12],
        ['id' => 'layer-b', 'label' => 'B', 'x' => 20, 'y' => 40, 'width' => 60, 'height' => 12],
        ['id' => 'mesh', 'label' => 'Mesh', 'x' => 20, 'y' => 60, 'width' => 60, 'height' => 10],
        ['id' => 'outlet', 'label' => 'C', 'x' => 30, 'y' => 82, 'width' => 40, 'height' => 10],
    ], 'edges' => [['from' => 'inlet', 'to' => 'layer-a'], ['from' => 'layer-a', 'to' => 'layer-b'], ['from' => 'layer-b', 'to' => 'mesh'], ['from' => 'mesh', 'to' => 'outlet']]]],
], $diagramItems);

$task = 'Multiple-answer multiple choice';
$add('reading-multiple', $task, 'reading', $both, [
    'im-r-multiple' => ['kind' => 'passage', 'title' => 'Testing a new appointment system', 'paragraphs' => [
        'A clinic introduced online booking to reduce telephone queues. Some patients found it convenient, but staff noticed that people without reliable internet access still needed help. The clinic retained telephone appointments and trained reception staff to assist patients who wanted to try the website. It also measured missed appointments and interviewed both users and non-users of the new system. The evaluation did not treat the number of website visits as proof of better access. A visit might represent a successful booking, repeated confusion or an attempt that was abandoned. The clinic therefore plans to revise the site using evidence about complete booking journeys.',
    ]],
], [[
    'id' => 'im-r-multiple01', 'type' => 'multi', 'task_type' => $task, 'title' => $task, 'resource_id' => 'im-r-multiple', 'prompt' => 'Which TWO statements are supported by the passage?', 'instructions' => 'Choose TWO answers. Both correct options, with no others, are required for this one-point item.', 'select_count' => 2, 'all_or_nothing' => true,
    'options' => $opts(['a' => 'Telephone booking was retained.', 'b' => 'Every website visit represented a completed booking.', 'c' => 'The evaluation included people who did not use online booking.', 'd' => 'Staff were prohibited from helping patients use the website.', 'e' => 'The clinic abandoned any plan to improve the website.']), 'answer' => ['a', 'c'], 'explanation' => 'Telephone appointments remain and interviews include non-users. Website visits are explicitly not assumed to equal successful bookings.',
]]);

// General Training students also practise evaluative and information-location tasks.
$task = 'Yes / No / Not Given';
$views = ['yes' => 'YES', 'no' => 'NO', 'not_given' => 'NOT GIVEN'];
$viewItems = [];
foreach ([
    ['im-r-views01', 'Counting book loans alone is insufficient to judge a small library’s value.', 'yes', 'The writer says a loan count misses quiet study, guidance and internet access.'],
    ['im-r-views02', 'The writer believes that a larger central library always gives every resident better access.', 'no', 'The writer says travel can make a larger library less accessible to some residents, so a larger building is not automatically a better service for everyone.'],
    ['im-r-views03', 'The writer thinks every library should open for exactly sixty hours each week.', 'not_given', 'The writer calls for attention to useful opening hours but specifies no universal weekly total.'],
] as $q) {
    $item = $choice($q[0], $task, 'im-r-views', $q[1], $views, $q[2], $q[3]);
    $item['instructions'] = 'Choose YES if the statement agrees with the writer’s view, NO if it contradicts that view, or NOT GIVEN if the writer’s view is not stated.';
    $viewItems[] = $item;
}
$add('general-reading-views', $task, 'reading', ['ielts-general'], [
    'im-r-views' => ['kind' => 'passage', 'title' => 'Judging a local library’s value', 'paragraphs' => [
        'Debates about small libraries often begin with the number of books borrowed. That measure is useful, but it cannot describe the whole service. A person may use a library for quiet study, guidance with an online form or access to a computer without borrowing anything. These activities should not disappear from an evaluation simply because counting them requires more effort.',
        'A larger central library may offer a wider collection and specialist staff. However, reaching it can require a long bus journey, particularly for people who cannot walk far or drive. A larger building is therefore not automatically a better service for every resident. Decisions should consider complete journeys, useful opening hours and the support available at each location.',
        'This is not an argument that no small library should ever change. Buildings require maintenance and budgets have limits. The public deserves a clear comparison of realistic options, including the effects on people who rely on local access. A decision becomes more defensible when those effects are made visible rather than treated as somebody else’s problem.',
    ]],
], $viewItems);

$task = 'Matching information';
$paragraphChoices = ['a' => 'Paragraph A', 'b' => 'Paragraph B', 'c' => 'Paragraph C', 'd' => 'Paragraph D'];
$add('general-reading-information', $task, 'reading', ['ielts-general'], [
    'im-r-information' => ['kind' => 'passage', 'title' => 'A neighbourhood seed exchange', 'paragraphs' => [
        'A. The seed exchange began when several gardeners realised that they each bought a whole packet but used only a few seeds. Sharing the remainder reduced waste and gave newcomers a chance to try unfamiliar vegetables without purchasing many varieties.',
        'B. Every donated packet should state the plant’s name, the year the seed was collected and the donor’s description of where it grew. If the variety is uncertain, say so rather than inventing a precise label. These details help another gardener decide whether to try it.',
        'C. Seeds are kept in a dry cupboard away from direct sunlight. Volunteers check for damaged packets and signs of moisture. Material that has become damp is separated from the rest, because poor storage can affect more than one donation.',
        'D. The organisers do not promise that every seed will germinate. They invite participants to report what happened, including unsuccessful attempts. The reports help improve advice for the following season and make clear that a living collection involves uncertainty rather than guaranteed results.',
    ]],
], [
    $choice('im-r-information01', $task, 'im-r-information', 'Which paragraph explains why the exchange was started?', $paragraphChoices, 'a', 'Paragraph A gives the original problem: buying more seeds than an individual gardener needs.'),
    $choice('im-r-information02', $task, 'im-r-information', 'Which paragraph describes information that should accompany a donation?', $paragraphChoices, 'b', 'Paragraph B lists the plant name, collection year and growing-location details for packet labels.'),
    $choice('im-r-information03', $task, 'im-r-information', 'Which paragraph asks users to report outcomes, including failures?', $paragraphChoices, 'd', 'Paragraph D asks for reports of what happened, including unsuccessful attempts, to improve future advice.'),
]);

$task = 'Short-answer questions';
$add('reading-short-answers', $task, 'reading', $both, [
    'im-r-short' => ['kind' => 'passage', 'title' => 'Volunteering with a local object collection', 'paragraphs' => [
        'New archive volunteers attend a ninety-minute introduction before working with the collection. The session explains how objects are numbered, where to find their records and when to stop and ask for help. Volunteers begin with a small supervised task rather than handling unfamiliar material alone.',
        'Gloves are provided for tasks where they are appropriate. Staff explain when to use them, because not every material should be handled in the same way. Leave each identification label attached to its object; removing it may separate the object from information that cannot easily be recovered. If an item appears damaged, consult the conservator before moving or cleaning it. Do not attempt a repair simply because suitable glue seems to be available.',
        'At the end of a session, record what you have completed and return tools to their marked places. These ordinary routines allow the next volunteer to understand the work without guessing. Accuracy and a willingness to ask questions matter more than completing a large number of objects quickly.',
    ]],
], [
    $short('im-r-short01', $task, 'im-r-short', 'How many minutes does the introduction last?', ['90', 'ninety'], 'The opening sentence specifies a ninety-minute introduction.'),
    $short('im-r-short02', $task, 'im-r-short', 'What protective items are provided when appropriate?', ['gloves'], 'The second paragraph says gloves are provided for appropriate tasks.'),
    $short('im-r-short03', $task, 'im-r-short', 'Who should a volunteer consult about a damaged item?', ['conservator'], 'The instructions require consulting the conservator before moving or cleaning a damaged item.'),
]);

// WRITING: six visual families, five essay families, and three letter registers.
$academicOnly = ['ielts-academic'];
$generalOnly = ['ielts-general'];
$visualRubric = ['Give a clear overview of the main trends, differences or stages.', 'Select accurate supporting detail and make useful comparisons rather than describing every element separately.', 'Do not invent reasons or information that the stimulus does not provide.', 'Use connected paragraphs, suitable vocabulary and accurate grammar; write at least 150 words.'];
$task = 'Academic Writing Task 1 · Line graph';
$add('academic-line', $task, 'writing', $academicOnly, [
    'im-w-line' => ['kind' => 'chart', 'title' => 'Weekly library visits at two university sites', 'chart' => ['type' => 'line', 'unit' => 'visits per week', 'categories' => ['2015', '2017', '2019', '2021', '2023'], 'series' => [['name' => 'Central library', 'values' => [4200, 4500, 5100, 4700, 5600]], ['name' => 'Riverside library', 'values' => [1800, 2400, 3200, 3900, 4300]]]]],
], [$essay('im-w-line01', $task, 'The line graph shows weekly visits to two university libraries in selected years from 2015 to 2023. Summarise the information by selecting and reporting the main features, and make comparisons where relevant. All figures are fictional practice data.', 150, ['resource_id' => 'im-w-line', 'rubric' => $visualRubric])]);

$task = 'Academic Writing Task 1 · Pie chart';
$add('academic-pie', $task, 'writing', $academicOnly, [
    'im-w-pie' => ['kind' => 'chart', 'title' => 'Household water use in a town, 2025', 'chart' => ['type' => 'pie', 'unit' => '% of household water use', 'categories' => ['Bathing', 'Toilets', 'Laundry', 'Kitchen', 'Garden'], 'series' => [['name' => 'Share', 'values' => [32, 24, 20, 14, 10]]]]],
], [$essay('im-w-pie01', $task, 'The pie chart shows how households in one town used water in 2025. Summarise the information by selecting and reporting the main features, and make comparisons where relevant. The figures are fictional practice data.', 150, ['resource_id' => 'im-w-pie', 'rubric' => $visualRubric])]);

$task = 'Academic Writing Task 1 · Table';
$add('academic-table', $task, 'writing', $academicOnly, [
    'im-w-table' => ['kind' => 'table', 'title' => 'Participation in adult education courses', 'columns' => ['Course', '2015 participants', '2025 participants'], 'rows' => [['Languages', 240, 310], ['Computing', 180, 420], ['Arts', 220, 260], ['Practical skills', 160, 350]]],
], [$essay('im-w-table01', $task, 'The table compares the numbers of adults attending four types of course at a community college in 2015 and 2025. Summarise the information by selecting and reporting the main features, and make comparisons where relevant. The numbers are fictional practice data.', 150, ['resource_id' => 'im-w-table', 'rubric' => $visualRubric])]);

$task = 'Academic Writing Task 1 · Map';
$add('academic-map', $task, 'writing', $academicOnly, [
    'im-w-map' => ['kind' => 'diagram', 'title' => 'Town centre changes: 2010 on left, 2025 on right', 'diagram' => ['description' => 'The left panel represents 2010; the right panel represents 2025. In each panel north is at the top. In 2010 a car park occupied the northwest, a library the northeast, shops the southwest and a bus stop the southeast. In 2025 the northwest became a park, the library remained in the northeast, the southwest became a café and the southeast became a bus station. A pedestrian link now connects the café to the park. Panel positions are comparable; no distances or building sizes are specified.', 'nodes' => [
        ['id' => 'old-year', 'label' => '2010', 'x' => 3, 'y' => 3, 'width' => 42, 'height' => 10],
        ['id' => 'new-year', 'label' => '2025', 'x' => 55, 'y' => 3, 'width' => 42, 'height' => 10],
        ['id' => 'old-car', 'label' => 'Car park', 'x' => 3, 'y' => 25, 'width' => 19, 'height' => 18],
        ['id' => 'old-library', 'label' => 'Library', 'x' => 26, 'y' => 25, 'width' => 19, 'height' => 18],
        ['id' => 'old-shops', 'label' => 'Shops', 'x' => 3, 'y' => 65, 'width' => 19, 'height' => 18],
        ['id' => 'old-bus', 'label' => 'Bus stop', 'x' => 26, 'y' => 65, 'width' => 19, 'height' => 18],
        ['id' => 'new-park', 'label' => 'Park', 'x' => 55, 'y' => 25, 'width' => 19, 'height' => 18],
        ['id' => 'new-library', 'label' => 'Library', 'x' => 78, 'y' => 25, 'width' => 19, 'height' => 18],
        ['id' => 'new-cafe', 'label' => 'Café', 'x' => 55, 'y' => 65, 'width' => 19, 'height' => 18],
        ['id' => 'new-bus', 'label' => 'Bus station', 'x' => 78, 'y' => 65, 'width' => 19, 'height' => 18],
    ], 'edges' => [['from' => 'new-cafe', 'to' => 'new-park']]]],
], [$essay('im-w-map01', $task, 'The two plans show changes to a small town centre between 2010 and 2025. Summarise the information by selecting and reporting the main features, and make comparisons where relevant. Refer to both panels and use the description for the pedestrian link; do not infer distances or building sizes.', 150, ['resource_id' => 'im-w-map', 'rubric' => $visualRubric])]);

$task = 'Academic Writing Task 1 · Process';
$add('academic-process', $task, 'writing', $academicOnly, [
    'im-w-process' => ['kind' => 'diagram', 'title' => 'Turning used glass into new containers', 'diagram' => ['description' => 'Follow the arrows in this six-stage linear process: used glass containers are collected; the glass is sorted by colour; it is washed; it is crushed; the pieces are melted in a furnace; the liquid glass is moulded into new containers. Sorting occurs before washing. The diagram gives no temperature, duration or quantity data.', 'nodes' => [
        ['id' => 'collect', 'label' => 'Collect', 'x' => 5, 'y' => 8, 'width' => 30, 'height' => 12],
        ['id' => 'sort', 'label' => 'Sort', 'x' => 65, 'y' => 8, 'width' => 30, 'height' => 12],
        ['id' => 'wash', 'label' => 'Wash', 'x' => 65, 'y' => 40, 'width' => 30, 'height' => 12],
        ['id' => 'crush', 'label' => 'Crush', 'x' => 5, 'y' => 40, 'width' => 30, 'height' => 12],
        ['id' => 'melt', 'label' => 'Melt', 'x' => 5, 'y' => 72, 'width' => 30, 'height' => 12],
        ['id' => 'mould', 'label' => 'Mould', 'x' => 65, 'y' => 72, 'width' => 30, 'height' => 12],
    ], 'edges' => [['from' => 'collect', 'to' => 'sort'], ['from' => 'sort', 'to' => 'wash'], ['from' => 'wash', 'to' => 'crush'], ['from' => 'crush', 'to' => 'melt'], ['from' => 'melt', 'to' => 'mould']]]],
], [$essay('im-w-process01', $task, 'The diagram illustrates how used glass containers are processed to make new containers. Summarise the information by selecting and reporting the main features. Describe the sequence and use only details supplied by the diagram and its description.', 150, ['resource_id' => 'im-w-process', 'rubric' => $visualRubric])]);

$task = 'Academic Writing Task 1 · Mixed charts';
$add('academic-mixed', $task, 'writing', $academicOnly, [
    'im-w-mixed-chart' => ['kind' => 'chart', 'title' => 'Museum attendance, 2015 and 2025', 'chart' => ['type' => 'bar', 'unit' => 'thousands of visits', 'categories' => ['2015', '2025'], 'series' => [['name' => 'Visits', 'values' => [80, 140]]]]],
    'im-w-mixed-table' => ['kind' => 'table', 'title' => 'Museum income by source, same years', 'columns' => ['Income source', '2015 (£ thousands)', '2025 (£ thousands)'], 'rows' => [['Tickets', 320, 490], ['Shop and café', 120, 260], ['Grants', 260, 250]]],
], [$essay('im-w-mixed01', $task, 'The bar chart shows annual attendance at a museum in 2015 and 2025, while the table gives its income from three sources in the same years. Summarise the information by selecting and reporting the main features, and make comparisons where relevant. Include information from both displays. All figures are fictional practice data.', 150, ['resource_id' => 'im-w-mixed-chart', 'secondary_resource_id' => 'im-w-mixed-table', 'rubric' => $visualRubric])]);

$essayFamilies = [
    ['opinion', 'Writing Task 2 · Opinion', 'Some people believe that public libraries should lend equipment, such as tools and musical instruments, as well as books. To what extent do you agree or disagree? Give reasons and relevant examples.', ['State and maintain a clear position on expanding library lending.', 'Develop reasons with relevant examples and consider practical implications.']],
    ['discussion', 'Writing Task 2 · Discussion', 'Some people think employers should allow staff to work from home whenever possible. Others believe that working together in a shared workplace is more beneficial. Discuss both views and give your own opinion.', ['Explain both views fairly and make your own position clear.', 'Compare benefits using developed reasons rather than treating one view as a slogan.']],
    ['advantages', 'Writing Task 2 · Advantages and disadvantages', 'More university courses now include projects completed with local businesses. Do the advantages of this development outweigh the disadvantages? Give reasons and relevant examples.', ['Discuss advantages and disadvantages and make a clear judgement about which outweighs the other.', 'Develop the comparison, including consequences for learning and practical difficulties.']],
    ['problems', 'Writing Task 2 · Problem and solution', 'Many people buy new household products instead of repairing damaged ones. What problems can this cause, and what measures could encourage more repair? Give reasons and relevant examples.', ['Explain problems and propose measures that address them directly.', 'Develop realistic mechanisms rather than listing unrelated environmental actions.']],
    ['two-part', 'Writing Task 2 · Two-part question', 'In many towns, fewer young adults take part in local clubs and community groups. Why might this be happening? What could these organisations do to attract younger members?', ['Answer both questions with developed ideas.', 'Connect suggested actions to the reasons identified; avoid unsupported generalisations about all young adults.']],
];
foreach ($essayFamilies as $row) {
    $add('essay-' . $row[0], $row[1], 'writing', $both, [], [$essay('im-w-essay-' . $row[0], $row[1], $row[2], 250, ['rubric' => array_merge($row[3], ['Organise connected paragraphs with a supported conclusion.', 'Review vocabulary and grammatical accuracy; write at least 250 words.'])])]);
}
$letters = [
    ['formal', 'General Training Writing Task 1 · Formal letter', 'You bought a desk online, but it arrived damaged and the delivery company told you to contact the seller. Write a letter to the company’s customer service manager. Describe the desk and the damage; explain how the problem affects you; say what action you would like the company to take. Begin “Dear Sir or Madam,”. You do not need to include addresses.', ['Make the complaint clear, include useful details and request a reasonable remedy.', 'Use formal, polite language and an appropriate closing.']],
    ['semi-formal', 'General Training Writing Task 1 · Semi-formal letter', 'You have agreed to look after your neighbour Mr Ali’s home while he is away, but a change in your work schedule means you cannot visit every day. Write a letter to Mr Ali. Explain the change; say which tasks you can still do; suggest how the remaining tasks could be managed. Begin “Dear Mr Ali,”. You do not need to include addresses.', ['Explain the change and cover all practical arrangements without an unclear promise.', 'Use a friendly but respectful semi-formal tone suited to a neighbour you know.']],
    ['informal', 'General Training Writing Task 1 · Informal letter', 'A friend is moving to your town and has asked for advice. Write a letter to your friend. Describe a suitable area to live in; suggest ways to meet people; explain how you can help during the move. Begin “Dear Alex,”. You do not need to include addresses.', ['Cover accommodation, meeting people and your own offer of help with useful detail.', 'Use a natural informal tone, an appropriate greeting and a friendly closing.']],
];
foreach ($letters as $row) {
    $add('letter-' . $row[0], $row[1], 'writing', $generalOnly, [], [$essay('im-w-letter-' . $row[0], $row[1], $row[2], 150, ['rubric' => array_merge($row[3], ['Organise the letter coherently and develop every requested point.', 'Review grammar, vocabulary and spelling; write at least 150 words.'])])]);
}

return $modules;

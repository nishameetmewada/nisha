<?php
declare(strict_types=1);

/* Original NISHA practice material. Raw practice points are not official PTE scores. */
$options = static function (array $texts): array {
    $out = [];
    foreach ($texts as $i => $text) {
        $out[] = ['id' => chr(97 + $i), 'text' => $text];
    }
    return $out;
};
$spoken = static function (string $id, string $task, string $resource, string $prompt, int $prep, int $response, array $extra = []): array {
    return array_merge([
        'id' => $id, 'type' => 'spoken', 'task_type' => $task,
        'resource_id' => $resource, 'prompt' => $prompt,
        'prep_seconds' => $prep, 'response_seconds' => $response,
        'rubric' => [
            'Review whether your response completed this particular task and conveyed the important meaning.',
            'Listen for clear pronunciation, a steady pace and connected phrases; avoid prolonged hesitation.',
            'This response requires self-review. The browser does not award an official speaking score.',
        ],
    ], $extra);
};
$resources = [
    'ra-library' => ['kind' => 'passage', 'title' => 'A library beyond its shelves', 'paragraphs' => [
        'A university library is increasingly a place for making as well as reading. Students may borrow recording equipment, consult a data specialist or repair a small electronic device. These services do not replace books. Instead, they recognise that learning often involves producing something new and that access to the necessary tools should not depend on a student’s personal income.',
    ]],
    'ra-wetlands' => ['kind' => 'passage', 'title' => 'Wetlands and changing rainfall', 'paragraphs' => [
        'Wetlands can slow the movement of floodwater by spreading it across a broad, shallow area. Their effectiveness depends on the shape of the surrounding land and on how wet the soil already is. For this reason, restoring a wetland requires more than planting vegetation: planners must understand the entire catchment and allow water to follow suitable routes during heavy rain.',
    ]],
    'ra-peer-learning' => ['kind' => 'passage', 'title' => 'Explaining a difficult idea', 'paragraphs' => [
        'When students explain a difficult idea to one another, they often discover gaps in their own understanding. The listener’s questions make assumptions visible, while examples help turn an abstract principle into something practical. Peer learning is therefore most useful when students have time to question an explanation, rather than simply copy an answer that appears to be correct.',
    ]],
    'ra-materials' => ['kind' => 'passage', 'title' => 'Designing for repair', 'paragraphs' => [
        'The environmental impact of a product is influenced by how easily it can be repaired. A device assembled with standard screws may remain useful long after one component fails. Designers must balance this benefit against cost, weight and resistance to water. The challenge is to make repair a practical option without sacrificing the qualities that people need every day.',
    ]],
    'ra-night-sky' => ['kind' => 'passage', 'title' => 'Observing the night sky', 'paragraphs' => [
        'Artificial light allows cities to function after sunset, but poorly directed lighting can make the night sky difficult to observe. Shielding a lamp directs more light towards the ground, where it is useful, and less into the atmosphere. Careful lighting design can therefore improve visibility for pedestrians while also reducing energy consumption and protecting opportunities for astronomical research.',
    ]],
    'ra-public-data' => ['kind' => 'passage', 'title' => 'Public information and context', 'paragraphs' => [
        'Publishing a dataset does not automatically make it useful. People also need to know how its measurements were collected, which observations are missing and whether definitions changed over time. Clear documentation allows a community group or a researcher to interpret the figures responsibly. Without that context, a precise number can create more confidence than the underlying evidence deserves.',
    ]],
    'di-travel' => ['kind' => 'chart', 'title' => 'Student journeys to campus, survey share', 'chart' => [
        'unit' => '% of journeys', 'categories' => ['2022', '2023', '2024', '2025'],
        'series' => [
            ['name' => 'Bus', 'values' => [28, 31, 36, 40]],
            ['name' => 'Bicycle', 'values' => [12, 15, 19, 23]],
            ['name' => 'Car', 'values' => [48, 42, 32, 24]],
            ['name' => 'Walk', 'values' => [12, 12, 13, 13]],
        ],
    ]],
    'di-water' => ['kind' => 'chart', 'title' => 'Monthly rainfall at a field station', 'chart' => [
        'unit' => 'millimetres', 'categories' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
        'series' => [
            ['name' => 'Coastal station', 'values' => [42, 38, 51, 76, 104, 118]],
            ['name' => 'Inland station', 'values' => [28, 24, 30, 48, 57, 63]],
        ],
    ]],
    'di-budget' => ['kind' => 'chart', 'title' => 'Allocation of a community research grant', 'chart' => [
        'unit' => '% of total budget', 'categories' => ['Research staff', 'Equipment', 'Field travel', 'Public workshops', 'Administration'],
        'series' => [['name' => 'Budget allocation', 'values' => [42, 25, 16, 10, 7]]],
    ]],
    'di-library' => ['kind' => 'chart', 'title' => 'Library visits during an ordinary teaching day', 'chart' => [
        'unit' => 'visitors per hour', 'categories' => ['08:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00'],
        'series' => [['name' => 'Visitors', 'values' => [65, 148, 232, 278, 205, 124, 57]]],
    ]],
    'di-seedlings' => ['kind' => 'chart', 'title' => 'Seedling survival after twelve weeks', 'chart' => [
        'unit' => '% surviving', 'categories' => ['No shade', 'Light shade', 'Moderate shade', 'Deep shade'],
        'series' => [
            ['name' => 'Regular watering', 'values' => [62, 83, 91, 74]],
            ['name' => 'Reduced watering', 'values' => [31, 58, 72, 60]],
        ],
    ]],
    'lecture-heat' => ['kind' => 'audio', 'title' => 'Urban heat and the shape of shade', 'audio_script' =>
        'Today I want to examine why two streets in the same neighbourhood can feel very different on a hot afternoon. Air temperature matters, but it is only part of the explanation. A person standing beside a dark wall receives heat radiated from that wall as well as energy from direct sunlight. A nearby thermometer may not capture this experience very well. Trees change several parts of the situation. Their leaves intercept sunlight, and water evaporating from the leaves can cool the surrounding air. Yet a line of young trees with small crowns may offer little protection at the time pedestrians need it most. In one local study, researchers mapped shade at hourly intervals instead of simply counting trees. A bus stop with three trees was still exposed at four o’clock because all three shadows fell behind the shelter. Moving the waiting area a few metres made better use of the existing shade. The lesson is not that planting is unimportant. It is that cooling strategies should begin with people’s actual routes, waiting places and daily schedules. Success depends on useful shade, not just a larger number of trees.'
    ],
    'lecture-memory' => ['kind' => 'audio', 'title' => 'Why retrieval changes learning', 'audio_script' =>
        'Imagine that you are revising a chapter and every paragraph looks familiar. It is tempting to conclude that you have learned the material. Familiarity, however, is not the same as being able to retrieve an idea without the page in front of you. Researchers distinguish between recognising information and reconstructing it from memory. When learners close a book and try to explain the main argument, they discover which links are missing. This effort can also strengthen later recall, especially if they check their explanation and correct mistakes. Timing is important. Repeating a question immediately may feel easy because the answer is still active in memory. Returning to it after a day requires more work and can produce more durable learning. This does not mean that every study session should become an examination. A short written outline, a conversation with a classmate or three questions answered without notes can provide useful retrieval practice. The aim is to create manageable difficulty and then receive accurate feedback. Studying should help knowledge remain available when the original textbook, and its reassuring sense of familiarity, is no longer present.'
    ],
    'discussion-studio' => ['kind' => 'audio', 'title' => 'Three students discuss a shared project studio', 'audio_script' =>
        'Maya: Our department has offered us an unused classroom for a shared project studio, but we have to recommend how it should operate. I would prioritise long opening hours. At the moment, students who commute cannot always stay for an afternoon workshop, and people doing paid work often need somewhere to meet in the evening. A space that closes at five would reproduce the access problems we already have. We could use student cards for entry and require a short safety induction before anyone books equipment.

        Ben: I agree about access, but I worry that we are treating opening hours as the whole problem. Our design project last term stalled because we could not store a half-built model anywhere. We packed it away after every meeting, and fragile parts broke in transit. I would spend some of the budget on labelled storage and a few sturdy worktables before buying sophisticated machines. Otherwise, the studio could look impressive while being frustrating for ordinary group work. We should also decide who removes abandoned materials at the end of a semester.

        Leila: Both of those points matter. My concern is that a booking system might favour the same confident students every week. First-year students may assume the room is only for advanced projects, and students from other subjects might not realise they are welcome. What about a regular introductory session hosted by a student mentor? We could reserve a small number of slots for newcomers without restricting the whole timetable. A simple online page showing examples of possible projects would also make the purpose clearer.

        Maya: That sounds sensible, although mentors would need training and some payment. We cannot assume that students can provide a reliable service for free. Perhaps we should propose a limited evening schedule initially, with a paid mentor present twice a week. That would let us collect information about demand before the department commits to longer hours. Card access could be introduced later if the pilot shows that people can use the room responsibly.

        Ben: A pilot would also help us estimate storage needs. We could provide movable shelves and assign each group a clearly dated space. Then a short monthly review could identify projects that have finished. I would still avoid spending the full budget on equipment in the first month. We need to observe what people actually make and repair before choosing specialist tools.

        Leila: So our proposal could combine a modest equipment list, practical storage and two supported evenings each week. I can draft an invitation that explicitly welcomes beginners, while Maya checks the staffing cost and Ben prepares a floor plan. After six weeks, we should ask users and non-users what prevented them from taking part. Attendance figures alone would not tell us whether the studio was serving the students who need it most.'
    ],
    'discussion-fieldwork' => ['kind' => 'audio', 'title' => 'Three researchers plan a community field survey', 'audio_script' =>
        'Arun: We need to finalise our plan for the neighbourhood noise survey. My first thought was to ask volunteers to measure sound with their phones at a fixed time every evening. That would give us comparable observations across different streets. We could publish a clear instruction sheet and ask everyone to stand in the same kind of location, away from walls and sheltered doorways. A single evening time would keep the task simple enough for people to repeat for a week.

        Sofia: Simplicity is useful, but one time might miss the main problem. Residents near the market talk about delivery vehicles before dawn, whereas people near the sports ground mention weekend events. If we measure only at seven in the evening, we could produce a tidy dataset that represents neither concern. I would start with short interviews to identify important periods. Then we could choose a few time windows and let volunteers select the ones they can realistically manage.

        Daniel: I would add a caution about phone measurements. Different devices and microphone covers can produce different readings. We cannot describe this as a precise comparison of every street unless we understand those differences. Perhaps the university can lend us a small number of calibrated meters. Volunteers could rotate them between locations, and phone recordings could provide supporting observations about the kind of sound, rather than numbers we treat as directly interchangeable.

        Arun: That makes the project more reliable, although rotating equipment introduces a coordination problem. Someone must collect each meter, charge it and explain how to use it. If the process becomes too complicated, volunteers may drop out. Could we recruit a smaller core group for the meters while allowing everyone else to keep a simple sound diary? The diaries could record the time, source and effect of a disturbance, such as waking someone or interrupting a conversation.

        Sofia: I like that combination. It also avoids assuming that the loudest sound is always the most troublesome. A short, unexpected noise at night may disturb residents more than a steady daytime sound. We should ask about the situation without leading people towards a particular complaint. And we must explain what will happen to their information. Publishing exact home addresses could make participants uncomfortable, so the public map should use broader areas.

        Daniel: Agreed. Let us run a two-day pilot before the full week. We can check whether the instructions are clear, compare the meters and estimate how much time volunteers spend. The final report should distinguish measured sound levels from residents’ experiences. Neither tells the complete story alone. If we present both carefully, the council will have a better basis for deciding whether to adjust delivery routes, event schedules or something else entirely.'
    ],
    'situation-lab' => ['kind' => 'audio', 'title' => 'A booking conflict in the laboratory', 'audio_script' =>
        'You arrive at a laboratory for a group experiment and discover that another group has booked the same instrument. Your project deadline is tomorrow. A technician says the instrument will be free for only forty minutes later today, but your group originally requested ninety minutes. Speak to the technician. Explain the problem politely, ask what preparation can be completed elsewhere, and propose a practical way to use the shorter slot.'
    ],
    'situation-work' => ['kind' => 'audio', 'title' => 'A workplace presentation needs a change', 'audio_script' =>
        'Your supervisor has asked you to give an in-person demonstration to new staff on Friday morning. You have just received notice of a compulsory university assessment at the same time. A colleague knows the equipment but has never led the demonstration. Speak to your supervisor. Explain the clash, offer a realistic alternative and say how you can help your colleague prepare if a substitute presenter is needed.'
    ],
    'swt-repair' => ['kind' => 'passage', 'title' => 'Repair cafés and what they teach', 'paragraphs' => [
        'At a repair café, visitors bring damaged household objects to a community venue, where volunteers help diagnose faults and attempt repairs. A successful repair may keep a lamp, jacket or small appliance in use, reducing both waste and the immediate cost of replacement. Yet counting repaired objects captures only one part of the activity. Organisers increasingly describe these events as informal learning spaces in which practical knowledge is shared across generations and occupations.',
        'The relationship between expert and visitor is important. If a volunteer silently fixes an object while its owner waits outside, the object may survive but little knowledge changes hands. When the owner observes a diagnosis, asks questions and performs a safe part of the repair, the encounter can build confidence. Visitors may learn how to identify a loose connection, recognise a worn component or decide when a repair requires professional assistance. That last ability matters because confidence without an understanding of risk is not a useful outcome.',
        'Researchers evaluating repair cafés therefore suggest combining several measures. Alongside the number and weight of objects repaired, they examine repeat participation, visitors’ reported understanding and the connections formed between local groups. They also recognise limits: volunteers cannot overcome every design barrier, obtain every spare part or accept responsibility for unsafe equipment. Repair cafés are consequently best understood as one contribution to a wider repair culture, supported by product design, access to parts and clear safety guidance, rather than as a complete solution to household waste.',
    ]],
    'swt-timetables' => ['kind' => 'passage', 'title' => 'The timetable as a public service', 'paragraphs' => [
        'A transport timetable is usually presented as a list of departures, but it also expresses decisions about whose time matters. Frequent services at the busiest hour support the largest number of travellers, while an early or late service may enable a smaller group to reach essential work, education or care. These journeys can be invisible in a simple count of passengers because people may already have abandoned public transport when suitable services are unavailable.',
        'To understand unmet demand, some transport agencies now combine ticket records with interviews and travel diaries. Ticket data show where current customers board; interviews reveal why potential passengers do not. A worker may finish a shift ten minutes after the last bus, for example, or a parent may need a connection that exists on paper but leaves too little time to cross a station with a child. In each case, a small timetable change could have a larger effect than adding another departure to an already frequent route.',
        'This broader approach does not remove financial constraints. Running almost empty vehicles indefinitely may divert resources from other useful services. Instead, planners can test changes for a defined period, explain the intended benefit and assess both ridership and the opportunities created. A timetable then becomes more than an operational schedule: it is a tool for improving access, whose success depends on understanding people’s complete journeys rather than considering each vehicle movement in isolation.',
    ]],
    'sst-citizen' => ['kind' => 'audio', 'title' => 'Designing a useful citizen science project', 'audio_script' =>
        'Citizen science projects invite members of the public to contribute to research, but participation alone does not guarantee useful evidence. Consider a project asking residents to record flowering dates. If everyone reports only the most colourful plant in a garden, the results may tell us more about attention than about seasonal change. Researchers need a clear observation protocol: which species to examine, how often to visit and what counts as an open flower. They must also make it possible to record that nothing has happened. An absence can be scientifically important, even though it feels less satisfying to submit. Training does not have to mean a lengthy course. A few contrasting photographs and a short practice activity can expose misunderstandings early. Feedback is equally valuable. When volunteers see how their records contribute to a larger pattern, they are more likely to continue and to notice unusual observations carefully. However, feedback should not encourage people to change an inconvenient result to match an expected trend. Projects also need to recognise differences in access. A system requiring a recent phone and constant internet connection may exclude knowledgeable participants. Paper forms or occasional supported sessions can widen involvement. The strongest projects therefore design research quality and public participation together. They give people a meaningful role while being explicit about uncertainty, practical limitations and the care required to turn everyday observations into evidence.'
    ],
    'lmulti-campus' => ['kind' => 'audio', 'title' => 'Changes to a campus food survey', 'audio_script' =>
        'Our first food survey asked students whether they liked the campus cafés, and most respondents said yes. That sounded encouraging, but it did not tell us much about access. This term we will ask when people need meals, how long they can wait and whether the advertised options are actually available near closing time. We will still collect comments about taste, although those will no longer be the whole survey. We are also arranging short interviews with students who usually bring food from home. Some may simply prefer their own cooking, but others may find prices or opening hours unsuitable. We will not assume the reasons in advance. Finally, the survey will remain anonymous; meal purchases will not be linked to individual student records.'
    ],
    'lmulti-museum' => ['kind' => 'audio', 'title' => 'An accessible museum audio tour', 'audio_script' =>
        'For the new museum audio tour, we first considered recording every label word for word. Trial visitors told us that this was tiring and offered little help with finding the objects being discussed. We are now producing shorter tracks that begin with the object’s position and a concise physical description. Visitors can choose a longer interpretation if they want more detail. We will offer the tour on loan devices as well as personal phones, because requiring a download would exclude some visitors. We have not decided to replace the printed labels. They remain useful, particularly for people who prefer reading or cannot comfortably use headphones. The next trial will include visitors with different access needs before the scripts and navigation are finalised.'
    ],
    'lfib-soil' => ['kind' => 'audio', 'title' => 'Why soil sampling needs a plan', 'audio_script' =>
        'A soil sample seems simple, but a useful result depends on the sampling plan. Conditions may vary substantially between the edge and centre of a field. Researchers therefore mark several locations before collecting material, rather than choosing only the most convenient patch. Samples must also come from a consistent depth. A shallow sample and a deeper one can contain different proportions of organic matter and minerals. Each container receives a label immediately, including the location and collection date. Back in the laboratory, the team records any unusual conditions, such as recent rainfall or fertiliser application. These details help distinguish a genuine environmental pattern from a difference caused by the way the material was collected.'
    ],
    'lfib-podcast' => ['kind' => 'audio', 'title' => 'Producing a short research podcast', 'audio_script' =>
        'A good research podcast begins with a question the audience can understand. Technical definitions can come later, once listeners know why the subject matters. In our workshop, each team first records a brief conversation without editing it. Listening back reveals where an explanation becomes confusing or where a useful example is missing. The next step is to write a flexible outline, rather than a script that makes every speaker sound identical. During editing, pauses can be shortened, but uncertainty must not be removed merely to make a finding seem more decisive. Finally, obtain permission for music and identify the source of any recording made outside the studio. Clear sound is valuable, but accuracy and consent are equally central to responsible production.'
    ],
    'lfib-river' => ['kind' => 'audio', 'title' => 'Restoring a small urban stream', 'audio_script' =>
        'Restoring an urban stream requires attention to the surrounding streets as well as the water channel. Rain falling on roofs and roads reaches the stream quickly, carrying sediment and other pollutants. One approach is to create planted areas that temporarily retain this runoff and allow some of it to enter the ground. Their capacity is limited, so engineers must provide a safe overflow route during intense storms. Maintenance is also essential: blocked inlets can prevent water from reaching the plants at all. Monitoring should continue after construction, because a successful design on paper may behave differently through several wet and dry seasons. The aim is a healthier stream and a drainage system that remains dependable over time.'
    ],
    'summary-map' => ['kind' => 'audio', 'title' => 'When a historical map is evidence', 'audio_script' =>
        'Historical maps are valuable sources, but they should not be treated as transparent windows onto the past. A mapmaker selected what to include for a particular audience and purpose. A trading company might carefully mark a river route while leaving neighbouring settlements vague. A military map might show roads in detail but omit buildings considered unimportant. We therefore learn from both what is represented and what is absent, although an omission does not prove that a place did not exist. Comparing maps with diaries, land records or physical evidence can reveal those choices. The point is not to reject maps because they are selective. All sources are selective. The task is to understand the selection before using the map to support a historical claim.'
    ],
    'summary-team' => ['kind' => 'audio', 'title' => 'How teams share expertise', 'audio_script' =>
        'A project team does not need every member to remember every detail. It does, however, need a reliable understanding of who knows what. Psychologists sometimes call this a shared map of expertise. The map develops when people work together and observe one another solving problems. It becomes less reliable when responsibilities change without being communicated, or when a confident speaker is assumed to be the expert on every topic. Brief explanations of current work can help keep it accurate. So can inviting a quieter colleague to describe a specialised skill. Documentation remains useful, but it is most effective when the team also knows whom to ask about its interpretation. Coordinating knowledge involves maintaining relationships as well as storing information.'
    ],
    'lsingle-batteries' => ['kind' => 'audio', 'title' => 'Interpreting a battery trial', 'audio_script' =>
        'The new battery lasted longer in our test, but we should be cautious about announcing a general improvement. Both batteries powered the same sensor, yet the newer model was tested in a cooler room. Temperature affects performance, so the trials did not isolate the effect of the battery design. The result is promising enough to justify another experiment, not strong enough to settle the comparison. We will repeat the test with both models at three controlled temperatures and use several examples of each model. If the advantage persists, we can be more confident that it comes from the design rather than the conditions. A careful follow-up is more useful than a dramatic claim based on one favourable measurement.'
    ],
    'lsingle-feedback' => ['kind' => 'audio', 'title' => 'Using feedback during a course', 'audio_script' =>
        'Students often tell us that feedback arrives too late to be useful. The final assignment is returned after teaching has ended, so even detailed comments cannot improve that particular piece of work. Our revised course asks students to submit a short proposal halfway through the term. Tutors then comment on the argument and the evidence while changes are still possible. The proposal receives no grade; its purpose is to guide the larger project. Students must include a brief note with the final submission explaining which suggestions they used and why. They can disagree with advice, but they need to make a reasoned decision. This approach turns feedback into part of the work, rather than simply an explanation of a mark.'
    ],
    'missing-seeds' => ['kind' => 'audio', 'title' => 'Storing seeds for future research', 'audio_script' =>
        'A seed collection is sometimes described as a library of future possibilities. Storage conditions can extend the life of many seeds, but no collection can simply be locked away and forgotten. Staff periodically test small samples to see whether they still germinate. They also maintain records showing where the material came from and how it was handled. These records become especially valuable when researchers compare populations from different environments. Without them, a healthy seed may still grow into a plant, yet much of its scientific value has been lost. Protecting a collection therefore means looking after the seeds and preserving their [tone].'
    ],
    'missing-design' => ['kind' => 'audio', 'title' => 'Testing a public information sign', 'audio_script' =>
        'The first version of our station sign looked elegant in the design studio. When we tested it at the entrance, however, passengers struggled to read the pale lettering against the bright windows. Some also interpreted an arrow as pointing towards an exit rather than a platform. Enlarging the letters helped, but it did not resolve the directional confusion. We changed the layout and tested the sign again with people who had never visited the station. Their comments showed us that an attractive object and a useful information system are not necessarily the same thing. The sign should be judged primarily by whether passengers can [tone].'
    ],
    'hiw-archive' => ['kind' => 'audio', 'title' => 'A community photo archive', 'audio_script' =>
        'The archive began when residents donated photographs of the old harbour. Volunteers scanned each image and recorded the owner’s account of when and where it was taken. Some dates were uncertain, so the catalogue clearly distinguishes confirmed information from estimates. The team also invited people to identify unnamed workers and buildings. These contributions added valuable context, but conflicting memories were kept rather than quietly removed. Researchers can therefore see how an identification developed over time. The collection now supports school projects and local exhibitions, while the original photographs remain with their owners unless they choose to donate them permanently.'
    ],
    'hiw-rooms' => ['kind' => 'audio', 'title' => 'Improving seminar rooms', 'audio_script' =>
        'The university tested new seating arrangements in four seminar rooms last term. Students could move the lightweight tables into small groups without blocking the main walkway. Tutors reported that discussions began more quickly, although some activities still required everyone to face the screen. The evaluation also identified a practical problem: there were too few power sockets near the centre of each room. Rather than replacing all the furniture immediately, the facilities team will add safe charging points and repeat the trial. The final decision will consider accessibility, maintenance costs and feedback from both students and teaching staff.'
    ],
];

$repeatSentences = [
    'The revised timetable will be published on the department website tomorrow.',
    'Please leave enough time to transfer between the two campus buses.',
    'Our findings suggest that the effect becomes stronger during dry weather.',
    'Students should discuss any change of topic with their project supervisor.',
    'The museum is extending its opening hours throughout the summer exhibition.',
    'Each sample must be labelled before it is placed in the refrigerator.',
    'Although the equipment is expensive, several departments can share the cost.',
    'The guest lecturer will answer questions after a short refreshment break.',
    'We need additional evidence before we can recommend a permanent policy.',
    'Your report should explain both the method and the limitations of the study.',
];
foreach ($repeatSentences as $i => $sentence) {
    $resources['repeat-' . ($i + 1)] = ['kind' => 'audio', 'title' => 'Sentence ' . ($i + 1), 'audio_script' => $sentence];
}
$shortQuestions = [
    ['question' => 'What do we call a list of the books and articles used to prepare an academic report?', 'sample' => 'A bibliography, or a reference list.'],
    ['question' => 'What instrument is used to measure atmospheric pressure?', 'sample' => 'A barometer.'],
    ['question' => 'In a research study, what do we call the people who take part in the investigation?', 'sample' => 'Participants.'],
    ['question' => 'What is the name for a word that has the opposite meaning to another word?', 'sample' => 'An antonym.'],
    ['question' => 'What do we call money provided to help a student pay for education, often on the basis of achievement?', 'sample' => 'A scholarship.'],
];
foreach ($shortQuestions as $i => $question) {
    $resources['short-question-' . ($i + 1)] = ['kind' => 'audio', 'title' => 'Short question ' . ($i + 1), 'audio_script' => $question['question']];
}
$dictations = [
    'The revised proposal includes a more detailed explanation of the costs.',
    'Several local businesses have agreed to support the student exhibition.',
    'Please return all borrowed equipment before the laboratory closes.',
    'The relationship between rainfall and river flow requires further investigation.',
];
foreach ($dictations as $i => $sentence) {
    $resources['dictation-' . ($i + 1)] = ['kind' => 'audio', 'title' => 'Dictation sentence ' . ($i + 1), 'audio_script' => $sentence];
}

$speakingWriting = [];
$readAloudResources = ['ra-library', 'ra-wetlands', 'ra-peer-learning', 'ra-materials', 'ra-night-sky', 'ra-public-data'];
foreach ($readAloudResources as $i => $resource) {
    $speakingWriting[] = $spoken('pte-sw' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT), 'Read Aloud', $resource,
        'Read the displayed passage aloud clearly and naturally. Begin after your preparation time.', 35, 40,
        ['title' => 'Read Aloud ' . ($i + 1), 'cues' => ['Use the preparation time to identify phrase groups and unfamiliar words.', 'Read the passage as written, without adding a commentary.']]);
}
foreach ($repeatSentences as $i => $sentence) {
    $speakingWriting[] = $spoken('pte-sw' . str_pad((string) ($i + 7), 2, '0', STR_PAD_LEFT), 'Repeat Sentence', 'repeat-' . ($i + 1),
        'Listen once, then repeat the sentence as accurately as you can.', 0, 15,
        ['title' => 'Repeat Sentence ' . ($i + 1), 'cues' => ['Preserve the words, grammatical endings and phrase order.', 'Continue smoothly if you miss a word; no automated speech mark is awarded.']]);
}
foreach (['di-travel', 'di-water', 'di-budget', 'di-library', 'di-seedlings'] as $i => $resource) {
    $speakingWriting[] = $spoken('pte-sw' . ($i + 17), 'Describe Image', $resource,
        'Describe the chart. Identify its subject, the main pattern and one or two meaningful comparisons.', 25, 40,
        ['title' => 'Describe Image ' . ($i + 1), 'cues' => ['Use figures selectively to support the overview.', 'Do not assume a cause that the chart does not establish.']]);
}
foreach (['lecture-heat', 'lecture-memory'] as $i => $resource) {
    $speakingWriting[] = $spoken('pte-sw' . ($i + 22), 'Retell Lecture', $resource,
        'Listen to the lecture, take brief notes if useful, and retell its main ideas in your own words.', 10, 40,
        ['title' => 'Retell Lecture ' . ($i + 1), 'cues' => ['State the topic and connect the important supporting points.', 'You do not need to reproduce every example or sentence.']]);
}
foreach ($shortQuestions as $i => $question) {
    $speakingWriting[] = $spoken('pte-sw' . ($i + 24), 'Answer Short Question', 'short-question-' . ($i + 1),
        'Listen and answer aloud with one or a few words.', 0, 10,
        ['title' => 'Answer Short Question ' . ($i + 1), 'rubric' => ['Self-check the meaning of your short spoken answer against the example after submission.', 'No speech recognition or official content/pronunciation score is applied.'], 'explanation' => 'Example response: ' . $question['sample']]);
}
foreach (['discussion-studio', 'discussion-fieldwork'] as $i => $resource) {
    $speakingWriting[] = $spoken('pte-sw' . ($i + 29), 'Summarize Group Discussion', $resource,
        'Listen to the three-person discussion and give a spoken summary of the main viewpoints and how they relate.', 10, 120,
        ['title' => 'Summarize Group Discussion ' . ($i + 1), 'cues' => ['Identify the shared problem, each speaker’s contribution and the outcome.', 'Summarise relationships, such as agreement, a concern or a proposed compromise.'],
         'rubric' => ['Include the central issue and the important contributions of all three speakers.', 'Explain how ideas develop towards the proposed plan; avoid listing disconnected details.', 'Use your own connected language with clear pronunciation and an intelligible pace. This task is ungraded.']]);
}
foreach (['situation-lab', 'situation-work'] as $i => $resource) {
    $speakingWriting[] = $spoken('pte-sw' . ($i + 31), 'Respond to a Situation', $resource,
        'Listen to the situation and respond as if speaking directly to the person described.', 10, 40,
        ['title' => 'Respond to a Situation ' . ($i + 1), 'cues' => ['Use a suitable polite register.', 'Address the problem, give the requested details and propose an actionable next step.'],
         'rubric' => ['Check that you addressed every requested communicative purpose.', 'Use a register appropriate for a technician or supervisor, and make the proposed solution clear.', 'Review fluency, grammar and pronunciation yourself; there is no automated speaking score.']]);
}
foreach (['swt-repair', 'swt-timetables'] as $i => $resource) {
    $speakingWriting[] = [
        'id' => 'pte-sw' . ($i + 33), 'type' => 'essay', 'task_type' => 'Summarize Written Text',
        'title' => 'Summarize Written Text ' . ($i + 1), 'resource_id' => $resource,
        'prompt' => 'Summarise the passage in ONE sentence of 5–75 words. Include the central argument and its most important support.',
        'instructions' => 'You have 10 minutes. Write one complete sentence; use your own wording where possible.',
        'min_words' => 5, 'max_words' => 75, 'suggested_seconds' => 600,
        'rubric' => ['Is the response one grammatical sentence between 5 and 75 words?', 'Does it retain the central argument rather than only one example?', 'Does it connect the key ideas accurately without adding unsupported claims?', 'Review grammar, spelling and word choice. This writing is ungraded.'],
    ];
}
$speakingWriting[] = [
    'id' => 'pte-sw35', 'type' => 'essay', 'task_type' => 'Write Essay', 'title' => 'Write Essay',
    'prompt' => 'Some universities require every undergraduate to complete a project with a local community organisation. Discuss the benefits and difficulties of this requirement and give your view on whether it should apply to all students. Support your position with reasons and examples.',
    'instructions' => 'You have 20 minutes. Write 200–300 words in connected paragraphs.',
    'min_words' => 200, 'max_words' => 300, 'suggested_seconds' => 1200,
    'rubric' => ['Address both potential benefits and practical difficulties, and make your position clear.', 'Develop relevant reasons with examples; do not rely on a memorised unrelated response.', 'Organise an introduction, connected discussion and conclusion within 200–300 words.', 'Check range and control of grammar, vocabulary, punctuation and spelling. This writing requires self-review.'],
];

$reading = [
    [
        'id' => 'pte-r01', 'type' => 'gaps', 'task_type' => 'Reading & Writing: Fill in the Blanks', 'title' => 'Research communication',
        'prompt' => 'Select the word that best completes each blank in meaning and grammar.',
        'text' => 'Researchers who communicate with the public face a problem of selection. A short article cannot reproduce every qualification in a technical paper, yet removing all uncertainty may {{g1}} the findings. The most effective writers identify the central question and explain why it matters before introducing specialist terms. They also distinguish evidence from interpretation: an observed association does not, by itself, {{g2}} a cause. Examples can make an explanation accessible, provided they remain {{g3}} of the wider evidence. Communication is therefore not simply a process of replacing difficult words with easier ones. It requires decisions about emphasis, context and the limits of a claim, while preserving enough detail for readers to {{g4}} the reasoning.',
        'blanks' => [
            ['id' => 'g1', 'answer' => ['distort'], 'options' => ['distort', 'restore', 'assemble', 'predict']],
            ['id' => 'g2', 'answer' => ['establish'], 'options' => ['establish', 'distribute', 'perform', 'accompany']],
            ['id' => 'g3', 'answer' => ['representative'], 'options' => ['representative', 'competitive', 'temporary', 'unrelated']],
            ['id' => 'g4', 'answer' => ['evaluate'], 'options' => ['evaluate', 'evaporate', 'decorate', 'manufacture']],
        ],
        'explanation' => 'Removing qualifications may distort a result; association does not establish causation. Examples should be representative, and sufficient context allows readers to evaluate an argument.',
    ],
    [
        'id' => 'pte-r02', 'type' => 'gaps', 'task_type' => 'Reading & Writing: Fill in the Blanks', 'title' => 'A useful timetable',
        'prompt' => 'Select the best word for each blank.',
        'text' => 'When a university changes its timetable, the consequences extend beyond the classroom. A later laboratory session may reduce conflicts between courses but create difficulties for students who {{g1}} on the last bus home. To understand the full effect, planners need information about complete daily journeys rather than isolated teaching periods. Consultation is most useful when proposed changes are still {{g2}} to revision. Asking for comments after rooms and staffing have been fixed can make participation feel symbolic. This does not mean that every preference can be {{g3}}. It means that unavoidable trade-offs should be made explicit and that the institution should explain the {{g4}} on which its final decision rests.',
        'blanks' => [
            ['id' => 'g1', 'answer' => ['depend'], 'options' => ['depend', 'consist', 'arrive', 'refer']],
            ['id' => 'g2', 'answer' => ['open'], 'options' => ['open', 'equal', 'absent', 'loose']],
            ['id' => 'g3', 'answer' => ['accommodated'], 'options' => ['accommodated', 'measured', 'postponed', 'repeated']],
            ['id' => 'g4', 'answer' => ['basis'], 'options' => ['basis', 'surface', 'branch', 'display']],
        ],
        'explanation' => 'Students depend on transport; a proposal is open to revision; preferences may be accommodated; and a decision rests on a basis of evidence and priorities.',
    ],
    [
        'id' => 'pte-r03', 'type' => 'gaps', 'task_type' => 'Reading & Writing: Fill in the Blanks', 'title' => 'Keeping field notes',
        'prompt' => 'Select the best word for each blank.',
        'text' => 'A field notebook should preserve more than a researcher’s successful observations. Recording a failed measurement can reveal a recurring problem with equipment or show why a later procedure was {{g1}}. The value of such notes depends on their clarity. An abbreviation that seems obvious during a busy afternoon may become {{g2}} months later, especially to another member of the team. Researchers therefore benefit from recording dates, locations and units consistently. They should also separate what they directly observed from what they {{g3}} about it. A note that an animal entered a shaded area is an observation; a claim that it was avoiding heat is an interpretation requiring further {{g4}}.',
        'blanks' => [
            ['id' => 'g1', 'answer' => ['modified'], 'options' => ['modified', 'admired', 'borrowed', 'inherited']],
            ['id' => 'g2', 'answer' => ['ambiguous'], 'options' => ['ambiguous', 'compulsory', 'durable', 'frequent']],
            ['id' => 'g3', 'answer' => ['inferred'], 'options' => ['inferred', 'installed', 'occupied', 'transported']],
            ['id' => 'g4', 'answer' => ['evidence'], 'options' => ['evidence', 'permission', 'employment', 'equipment']],
        ],
        'explanation' => 'A procedure is modified after problems; unclear shorthand becomes ambiguous; researchers infer interpretations; an interpretation needs supporting evidence.',
    ],
    [
        'id' => 'pte-r04', 'type' => 'gaps', 'task_type' => 'Reading & Writing: Fill in the Blanks', 'title' => 'Shared equipment',
        'prompt' => 'Select the best word for each blank.',
        'text' => 'Sharing expensive equipment can improve access to research facilities, but successful arrangements require more than an online calendar. Users need to understand which tasks have {{g1}} when demand exceeds capacity, and who will pay for repairs. A booking system may be technically fair while still {{g2}} newcomers who do not know how far in advance to reserve a slot. Introductory support can reduce this barrier. Equally important is a clear record of maintenance, so that a damaged component is reported before it {{g3}} another experiment. The purpose of these rules is not to create unnecessary bureaucracy. It is to make the facility dependable and to ensure that its benefits are {{g4}} across the research community.',
        'blanks' => [
            ['id' => 'g1', 'answer' => ['priority'], 'options' => ['priority', 'similarity', 'permission', 'quantity']],
            ['id' => 'g2', 'answer' => ['disadvantaging'], 'options' => ['disadvantaging', 'publishing', 'repairing', 'rewarding']],
            ['id' => 'g3', 'answer' => ['disrupts'], 'options' => ['disrupts', 'describes', 'resembles', 'releases']],
            ['id' => 'g4', 'answer' => ['distributed'], 'options' => ['distributed', 'dissolved', 'withheld', 'abandoned']],
        ],
        'explanation' => 'Demand requires priorities. A system can disadvantage inexperienced users; damage may disrupt experiments; shared benefits should be distributed through the community.',
    ],
    [
        'id' => 'pte-r05', 'type' => 'gaps', 'task_type' => 'Reading & Writing: Fill in the Blanks', 'title' => 'Understanding vacancy data',
        'prompt' => 'Select the best word for each blank.',
        'text' => 'A count of empty shops may appear to provide a straightforward measure of a town centre’s health. However, the meaning of a vacancy depends on its {{g1}}. A shop closed for a week between tenants differs from one that has remained unused for several years. Some spaces also host temporary activities that are not visible in conventional business registers. Researchers should therefore combine a count with information about duration, ownership and current use. Repeated surveys are particularly valuable because they {{g2}} change rather than a single moment. Interviews can add another {{g3}} by revealing obstacles to occupation, such as repair costs or inflexible leases. Together, these methods produce a more {{g4}} picture than an isolated headline figure.',
        'blanks' => [
            ['id' => 'g1', 'answer' => ['context'], 'options' => ['context', 'precision', 'ingredient', 'direction']],
            ['id' => 'g2', 'answer' => ['capture'], 'options' => ['capture', 'interrupt', 'invent', 'resist']],
            ['id' => 'g3', 'answer' => ['dimension'], 'options' => ['dimension', 'container', 'boundary', 'receipt']],
            ['id' => 'g4', 'answer' => ['nuanced'], 'options' => ['nuanced', 'identical', 'accidental', 'stationary']],
        ],
        'explanation' => 'Context changes the interpretation of vacancy; repeat surveys capture change; interviews add a dimension; the combined picture is nuanced rather than simplistic.',
    ],
    [
        'id' => 'pte-r06', 'type' => 'multi', 'task_type' => 'Reading: Multiple Choice, Multiple Answers', 'title' => 'A community cooling project',
        'prompt' => 'Which TWO considerations does the passage support? Select all correct answers.',
        'text' => 'A council plans to create cooler walking routes between homes, clinics and shops. Initial proposals focused on planting the largest possible number of trees. Consultation revealed that residents also wanted places to sit, reliable drinking fountains and shade at road crossings, where they often waited. Planners subsequently mapped routes at different times of day. They found that some streets with many trees offered little midday shade because the canopies were narrow or on the wrong side of the road. The revised plan combines planting with shelters at key waiting points and repairs to existing fountains. It will be assessed using both temperature measurements and residents’ accounts of whether they can make necessary journeys more comfortably. The council does not expect every route to be equally cool, but it wants improvements to serve people who have few alternatives to walking.',
        'options' => $options(['The location and timing of shade matter as well as the number of trees.', 'Only temperature readings can establish whether the project is useful.', 'Waiting places and essential journeys should inform the design.', 'Planting trees has been rejected in favour of shelters alone.', 'The proposal guarantees equal cooling along every street.']),
        'answer' => ['a', 'c'],
        'explanation' => 'Hourly mapping exposed gaps in useful shade, and consultation highlighted crossings and necessary journeys. The plan retains planting and uses both measurements and residents’ experiences; it makes no equal-cooling guarantee.',
    ],
    [
        'id' => 'pte-r07', 'type' => 'multi', 'task_type' => 'Reading: Multiple Choice, Multiple Answers', 'title' => 'Evaluating a mentoring scheme',
        'prompt' => 'Which TWO conclusions are supported? Select all correct answers.',
        'text' => 'A university mentoring scheme paired new students with volunteers from later years. Participants reported feeling more confident about contacting staff, and many valued learning how to find specialist support. Their examination results were also higher than the average for their year group. The evaluation team cautioned against attributing this difference entirely to mentoring: students had chosen whether to join, and those who enrolled may already have been more likely to seek help. To improve the next evaluation, the team plans to compare students with similar previous achievement and to interview people who declined a place. These interviews may reveal practical barriers, such as meeting times, that satisfaction surveys of participants cannot capture. The team considers the positive reports encouraging but wants clearer evidence about whom the scheme reaches and what changes it produces.',
        'options' => $options(['Higher participant marks alone prove that mentoring caused the difference.', 'Participants reported benefits in confidence and finding support.', 'Students who declined a place can provide information about access barriers.', 'The team recommends ending mentoring until a perfect evaluation is possible.', 'Satisfaction surveys already represent every new student’s experience.']),
        'answer' => ['b', 'c'],
        'explanation' => 'The passage explicitly reports confidence/support benefits and proposes interviews with non-participants to reveal barriers. Self-selection prevents a simple causal claim about examination results.',
    ],
    [
        'id' => 'pte-r08', 'type' => 'reorder', 'task_type' => 'Reorder Paragraphs', 'title' => 'Recovering an overlooked stream',
        'prompt' => 'Arrange these paragraphs into the most coherent order.',
        'options' => $options([
            'To test that possibility, the team examined old street plans and interviewed long-term residents about flooding after heavy rain.',
            'Their findings persuaded the council to investigate reopening a short section, with a public walkway beside it.',
            'A geography class noticed that a narrow line of damp ground crossed a car park even during dry weather.',
            'Those sources confirmed that a stream had once followed the same route before being enclosed in a pipe.',
            'The observation led the students to wonder whether an earlier watercourse still influenced the site.',
        ]),
        'answer' => ['c', 'e', 'a', 'd', 'b'],
        'explanation' => 'The initial observation is c; e proposes the watercourse explanation; a tests “that possibility”; d reports what “those sources” confirmed; b gives the consequence of the findings. Practice points count correct adjacent pairs.',
    ],
    [
        'id' => 'pte-r09', 'type' => 'reorder', 'task_type' => 'Reorder Paragraphs', 'title' => 'Testing an appointment reminder',
        'prompt' => 'Arrange these paragraphs into the most coherent order.',
        'options' => $options([
            'The interviews revealed that several recipients understood the message but could not find a convenient way to cancel.',
            'A student clinic wanted to reduce the number of booked appointments that nobody attended.',
            'After the reply option was added, cancellations increased and the clinic could offer unused appointments to other students.',
            'Staff initially sent a short reminder message, then interviewed students who still missed their appointment.',
            'As a result, the clinic revised the reminder to include a simple reply option rather than merely repeating the appointment time.',
        ]),
        'answer' => ['b', 'd', 'a', 'e', 'c'],
        'explanation' => 'The goal in b comes first; d introduces the reminder and interviews; a reports the interviews; e describes the resulting change; c reports the effect after that change. Correct adjacent pairs earn practice points.',
    ],
    [
        'id' => 'pte-r10', 'type' => 'gaps', 'task_type' => 'Reading: Fill in the Blanks', 'title' => 'An exhibition of sound',
        'prompt' => 'Complete each blank with one word from the available choices.',
        'text' => 'An exhibition about a city need not rely entirely on photographs. Recordings of markets, workshops and public celebrations can {{g1}} aspects of everyday life that images miss. Yet sound presents its own challenges. A recording without information about its location or date may be difficult to {{g2}}. Curators must also consider consent, especially when individual voices can be recognised. Carefully chosen labels and maps help visitors connect each sound to its {{g3}}, while quiet spaces prevent several recordings from competing for {{g4}}.',
        'blanks' => [
            ['id' => 'g1', 'answer' => ['reveal'], 'options' => ['reveal', 'interpret', 'setting', 'attention', 'replace', 'volume']],
            ['id' => 'g2', 'answer' => ['interpret'], 'options' => ['reveal', 'interpret', 'setting', 'attention', 'replace', 'volume']],
            ['id' => 'g3', 'answer' => ['setting'], 'options' => ['reveal', 'interpret', 'setting', 'attention', 'replace', 'volume']],
            ['id' => 'g4', 'answer' => ['attention'], 'options' => ['reveal', 'interpret', 'setting', 'attention', 'replace', 'volume']],
        ],
        'explanation' => 'Sounds reveal overlooked life; metadata help interpret them; maps identify their setting; separate listening areas reduce competition for visitors’ attention.',
    ],
    [
        'id' => 'pte-r11', 'type' => 'gaps', 'task_type' => 'Reading: Fill in the Blanks', 'title' => 'Measuring a tree',
        'prompt' => 'Complete each blank with one word from the available choices.',
        'text' => 'Long-term tree studies depend on consistent measurement. If one observer records the trunk at waist height and another measures near the ground, an apparent increase in width may be an {{g1}} of the method. Researchers therefore mark a standard point and describe it in the field record. They also note damage or unusual growth that might {{g2}} later comparisons. A photograph provides useful supporting {{g3}}, but it does not replace the numerical record. Consistency allows small annual changes to be distinguished from {{g4}} introduced by different observers.',
        'blanks' => [
            ['id' => 'g1', 'answer' => ['artefact'], 'options' => ['artefact', 'complicate', 'evidence', 'variation', 'climate', 'proposal']],
            ['id' => 'g2', 'answer' => ['complicate'], 'options' => ['artefact', 'complicate', 'evidence', 'variation', 'climate', 'proposal']],
            ['id' => 'g3', 'answer' => ['evidence'], 'options' => ['artefact', 'complicate', 'evidence', 'variation', 'climate', 'proposal']],
            ['id' => 'g4', 'answer' => ['variation'], 'options' => ['artefact', 'complicate', 'evidence', 'variation', 'climate', 'proposal']],
        ],
        'explanation' => 'A measurement artefact is created by the method; unusual growth can complicate comparisons; photographs add evidence; consistent procedures reduce observer variation.',
    ],
    [
        'id' => 'pte-r12', 'type' => 'gaps', 'task_type' => 'Reading: Fill in the Blanks', 'title' => 'Temporary uses of buildings',
        'prompt' => 'Complete each blank with one word from the available choices.',
        'text' => 'An empty building can sometimes support community activities while its long-term future is being decided. Temporary use may make a neighbourhood more {{g1}} and provide space that small organisations could not otherwise afford. However, users need clear agreements about duration, safety and responsibility for repairs. Without these, an apparently generous offer may create {{g2}} that the organisation cannot meet. A successful arrangement also acknowledges the possibility of {{g3}} when a permanent tenant arrives. Planning for that change from the outset helps groups preserve their work and find another suitable {{g4}}.',
        'blanks' => [
            ['id' => 'g1', 'answer' => ['lively'], 'options' => ['lively', 'obligations', 'relocation', 'venue', 'private', 'prediction']],
            ['id' => 'g2', 'answer' => ['obligations'], 'options' => ['lively', 'obligations', 'relocation', 'venue', 'private', 'prediction']],
            ['id' => 'g3', 'answer' => ['relocation'], 'options' => ['lively', 'obligations', 'relocation', 'venue', 'private', 'prediction']],
            ['id' => 'g4', 'answer' => ['venue'], 'options' => ['lively', 'obligations', 'relocation', 'venue', 'private', 'prediction']],
        ],
        'explanation' => 'Activities make a place lively; agreements may impose obligations; temporary users may face relocation and need another venue.',
    ],
    [
        'id' => 'pte-r13', 'type' => 'gaps', 'task_type' => 'Reading: Fill in the Blanks', 'title' => 'Questions before a survey',
        'prompt' => 'Complete each blank with one word from the available choices.',
        'text' => 'Before launching a questionnaire, researchers often test it with a small group. Participants describe how they {{g1}} each question and explain which response they would select. This can expose wording that seems clear to the research team but carries a different meaning for others. A question combining two issues may also produce an {{g2}} answer when a participant agrees with one part and disagrees with the other. Revising such problems early improves the {{g3}} of the final data. The pilot is therefore an opportunity to examine understanding, not merely to check how many minutes {{g4}} requires.',
        'blanks' => [
            ['id' => 'g1', 'answer' => ['interpret'], 'options' => ['interpret', 'ambiguous', 'quality', 'completion', 'calculate', 'delivery']],
            ['id' => 'g2', 'answer' => ['ambiguous'], 'options' => ['interpret', 'ambiguous', 'quality', 'completion', 'calculate', 'delivery']],
            ['id' => 'g3', 'answer' => ['quality'], 'options' => ['interpret', 'ambiguous', 'quality', 'completion', 'calculate', 'delivery']],
            ['id' => 'g4', 'answer' => ['completion'], 'options' => ['interpret', 'ambiguous', 'quality', 'completion', 'calculate', 'delivery']],
        ],
        'explanation' => 'Pilot participants explain how they interpret wording; double questions create ambiguous answers; revisions improve data quality; completion is the act being timed.',
    ],
    [
        'id' => 'pte-r14', 'type' => 'choice', 'task_type' => 'Reading: Multiple Choice, Single Answer', 'title' => 'The role of a negative result',
        'prompt' => 'What is the main point of the passage?',
        'text' => 'In research, an experiment that fails to support a prediction can still be informative. Its value depends partly on whether the test was capable of detecting the proposed effect. If a measuring device was unreliable or the sample extremely small, the absence of an observed effect may say little. When the method is sound and the study is well documented, however, a negative result can help others avoid pursuing an unproductive explanation or refine the conditions under which a claim applies. Publishing such findings also makes the evidence base less distorted, since a collection consisting only of positive outcomes can exaggerate how consistent an effect really is. The appropriate response to a negative result is therefore neither automatic dismissal nor unquestioning acceptance, but an examination of what was tested and how convincingly.',
        'options' => $options(['Any experiment with a negative result should be repeated until it succeeds.', 'Negative results can be useful, but their meaning depends on the quality and scope of the test.', 'Positive results are generally less reliable than negative ones.', 'A negative result proves that the proposed effect cannot occur under any conditions.']),
        'answer' => 'b',
        'explanation' => 'The passage argues for interpreting a negative result through the method and what was actually tested. It neither dismisses all such results nor treats them as universal proof.',
    ],
    [
        'id' => 'pte-r15', 'type' => 'choice', 'task_type' => 'Reading: Multiple Choice, Single Answer', 'title' => 'Learning from a prototype',
        'prompt' => 'Why does the author favour an early, simple prototype?',
        'text' => 'A prototype is not necessarily a nearly finished product. In the early stages of design, a rough model may be more useful because it makes a particular question easy to test. A cardboard arrangement can reveal whether people understand where to queue, even though it cannot demonstrate the final material’s durability. By making only the features relevant to the current question, designers avoid spending time polishing details that may later disappear. A rough appearance can also encourage users to suggest substantial changes: people are sometimes reluctant to criticise something that looks complete and expensive. This does not mean that realistic prototypes have no place. As a design develops, different questions require different levels of detail. The central issue is whether the prototype supports the decision that needs to be made at that stage.',
        'options' => $options(['It guarantees that the final design will require no further testing.', 'It removes the need to consult potential users.', 'It can test a focused question and invite changes before unnecessary detail is fixed.', 'It provides the most accurate measure of the finished material’s strength.']),
        'answer' => 'c',
        'explanation' => 'The simple prototype tests a specific issue economically and encourages criticism. The passage explicitly distinguishes that purpose from durability testing and later, more realistic models.',
    ],
];

$listening = [
    [
        'id' => 'pte-l01', 'type' => 'essay', 'task_type' => 'Summarize Spoken Text', 'title' => 'Summarize Spoken Text',
        'resource_id' => 'sst-citizen',
        'prompt' => 'Listen to the lecture and write a summary of 50–70 words. Include its main argument and the important supporting points.',
        'instructions' => 'You have 10 minutes for this task, including listening and writing. Listen once in exam practice.',
        'min_words' => 50, 'max_words' => 70, 'suggested_seconds' => 600,
        'rubric' => ['Identify the need to design research quality and public participation together.', 'Include important support about protocols, training/feedback and access without attempting to list every example.', 'Use coherent sentences and accurate vocabulary in 50–70 words.', 'Review grammar and spelling yourself; this response is ungraded.'],
    ],
    [
        'id' => 'pte-l02', 'type' => 'multi', 'task_type' => 'Listening: Multiple Choice, Multiple Answers', 'title' => 'A revised food survey',
        'resource_id' => 'lmulti-campus', 'prompt' => 'Which TWO changes or plans does the speaker describe? Select all correct answers.',
        'options' => $options(['The new survey will consider meal times and availability.', 'Individual purchases will be linked to named student records.', 'Students who bring food from home will be interviewed.', 'Questions about taste will be removed completely.', 'The team will assume all non-customers find the food too expensive.']),
        'answer' => ['a', 'c'],
        'explanation' => 'The speaker adds questions on timing, waiting and availability, plus interviews with students who bring food. Taste remains a topic, anonymity is retained and reasons for non-use will not be assumed.',
    ],
    [
        'id' => 'pte-l03', 'type' => 'multi', 'task_type' => 'Listening: Multiple Choice, Multiple Answers', 'title' => 'A new museum audio tour',
        'resource_id' => 'lmulti-museum', 'prompt' => 'Which TWO features are part of the revised plan? Select all correct answers.',
        'options' => $options(['Every printed label will be removed.', 'Short tracks will introduce an object’s location and appearance.', 'Visitors must download the tour onto a personal phone.', 'Loan devices will be available alongside phone access.', 'The scripts have already been finalised without another trial.']),
        'answer' => ['b', 'd'],
        'explanation' => 'Tracks begin with position and a physical description, and both loan devices and personal phones are planned. Printed labels remain and an inclusive trial will precede finalisation.',
    ],
    [
        'id' => 'pte-l04', 'type' => 'gaps', 'task_type' => 'Listening: Fill in the Blanks', 'title' => 'Soil sampling',
        'resource_id' => 'lfib-soil', 'prompt' => 'Listen and type the missing words. Write ONE WORD in each blank.',
        'text' => 'A soil sample seems simple, but a useful result depends on the sampling plan. Conditions may vary {{g1}} between the edge and centre of a field. Researchers therefore mark several locations before collecting material, rather than choosing only the most convenient patch. Samples must also come from a consistent {{g2}}. A shallow sample and a deeper one can contain different proportions of organic matter and minerals. Each container receives a {{g3}} immediately, including the location and collection date. Back in the laboratory, the team records any unusual conditions, such as recent rainfall or fertiliser application. These details help distinguish a genuine environmental {{g4}} from a difference caused by the way the material was collected.',
        'blanks' => [
            ['id' => 'g1', 'answer' => ['substantially']], ['id' => 'g2', 'answer' => ['depth']],
            ['id' => 'g3', 'answer' => ['label']], ['id' => 'g4', 'answer' => ['pattern']],
        ],
        'explanation' => 'The recording says “vary substantially”, “consistent depth”, “receives a label” and “environmental pattern”. Each correctly transcribed blank earns one practice point.',
    ],
    [
        'id' => 'pte-l05', 'type' => 'gaps', 'task_type' => 'Listening: Fill in the Blanks', 'title' => 'Research podcasts',
        'resource_id' => 'lfib-podcast', 'prompt' => 'Listen and type the missing words. Write ONE WORD in each blank.',
        'text' => 'A good research podcast begins with a question the audience can understand. Technical {{g1}} can come later, once listeners know why the subject matters. In our workshop, each team first records a brief conversation without editing it. Listening back reveals where an explanation becomes confusing or where a useful example is missing. The next step is to write a flexible {{g2}}, rather than a script that makes every speaker sound identical. During editing, pauses can be shortened, but {{g3}} must not be removed merely to make a finding seem more decisive. Finally, obtain permission for music and identify the source of any recording made outside the studio. Clear sound is valuable, but accuracy and {{g4}} are equally central to responsible production.',
        'blanks' => [
            ['id' => 'g1', 'answer' => ['definitions']], ['id' => 'g2', 'answer' => ['outline']],
            ['id' => 'g3', 'answer' => ['uncertainty']], ['id' => 'g4', 'answer' => ['consent']],
        ],
        'explanation' => 'The missing words are “definitions”, “outline”, “uncertainty” and “consent”. They distinguish accessible presentation from distortion and unlicensed use.',
    ],
    [
        'id' => 'pte-l06', 'type' => 'gaps', 'task_type' => 'Listening: Fill in the Blanks', 'title' => 'Stream restoration',
        'resource_id' => 'lfib-river', 'prompt' => 'Listen and type the missing words. Write ONE WORD in each blank.',
        'text' => 'Restoring an urban stream requires attention to the surrounding streets as well as the water channel. Rain falling on roofs and roads reaches the stream quickly, carrying {{g1}} and other pollutants. One approach is to create planted areas that temporarily {{g2}} this runoff and allow some of it to enter the ground. Their capacity is limited, so engineers must provide a safe overflow route during intense storms. {{g3}} is also essential: blocked inlets can prevent water from reaching the plants at all. Monitoring should continue after construction, because a successful design on paper may behave differently through several wet and dry seasons. The aim is a healthier stream and a drainage system that remains {{g4}} over time.',
        'blanks' => [
            ['id' => 'g1', 'answer' => ['sediment']], ['id' => 'g2', 'answer' => ['retain']],
            ['id' => 'g3', 'answer' => ['maintenance']], ['id' => 'g4', 'answer' => ['dependable']],
        ],
        'explanation' => 'The recording names sediment, retaining runoff, maintenance of inlets and a dependable drainage system. Case does not affect the practice mark.',
    ],
    [
        'id' => 'pte-l07', 'type' => 'choice', 'task_type' => 'Highlight Correct Summary', 'title' => 'Historical maps',
        'resource_id' => 'summary-map', 'prompt' => 'Select the paragraph that best summarises the recording.',
        'options' => $options([
            'Historical maps should be replaced by diaries because omissions make maps too unreliable to support any claim about the past.',
            'Maps accurately record everything important to their makers, so modern researchers can use absent features as proof that those features did not exist.',
            'Historical maps reflect choices made for particular purposes; researchers should examine those choices and compare other evidence before drawing conclusions.',
            'Trading maps are more trustworthy than military maps because river routes are less likely than buildings to change over time.',
        ]),
        'answer' => 'c',
        'explanation' => 'The central point is selection, purpose and comparison with other evidence. The speaker neither rejects maps nor treats omissions as proof, and does not rank trading maps above military maps.',
    ],
    [
        'id' => 'pte-l08', 'type' => 'choice', 'task_type' => 'Highlight Correct Summary', 'title' => 'A team’s map of expertise',
        'resource_id' => 'summary-team', 'prompt' => 'Select the paragraph that best summarises the recording.',
        'options' => $options([
            'Effective teams combine documentation with an accurate, regularly updated understanding of each member’s expertise, developed through communication and working relationships.',
            'Every team member should learn all project details because relying on colleagues inevitably causes mistakes when responsibilities change.',
            'Confident speakers make the best coordinators because their knowledge usually covers more areas than that of quieter colleagues.',
            'Written records are unnecessary once a team has worked together long enough to identify its most experienced member.',
        ]),
        'answer' => 'a',
        'explanation' => 'The speaker links a reliable map of who knows what with communication, relationships and useful documentation. Universal knowledge, confidence as expertise and abandoning records are unsupported.',
    ],
    [
        'id' => 'pte-l09', 'type' => 'choice', 'task_type' => 'Listening: Multiple Choice, Single Answer', 'title' => 'A battery experiment',
        'resource_id' => 'lsingle-batteries', 'prompt' => 'Why is the speaker cautious about the first result?',
        'options' => $options(['The sensor was deliberately changed during the test.', 'The newer battery was tested at a different temperature.', 'The old battery was never connected to the sensor.', 'Several controlled trials have already contradicted the result.']),
        'answer' => 'b',
        'explanation' => 'The newer battery was tested in a cooler room, and temperature affects performance. The planned controlled trials are intended to separate design effects from that confound.',
    ],
    [
        'id' => 'pte-l10', 'type' => 'choice', 'task_type' => 'Listening: Multiple Choice, Single Answer', 'title' => 'Feedback before the final submission',
        'resource_id' => 'lsingle-feedback', 'prompt' => 'What is the main purpose of the ungraded proposal?',
        'options' => $options(['To replace the final project with a shorter assessment.', 'To ensure that students accept every tutor suggestion.', 'To provide guidance while students can still improve the project.', 'To explain a final mark after the course has ended.']),
        'answer' => 'c',
        'explanation' => 'The mid-term proposal makes feedback available while revision is possible. Students may disagree with advice if they give a reasoned explanation.',
    ],
    [
        'id' => 'pte-l11', 'type' => 'choice', 'task_type' => 'Select Missing Word', 'title' => 'A seed collection',
        'resource_id' => 'missing-seeds', 'prompt' => 'The final word has been replaced by a tone. Select the word that best completes the recording.',
        'options' => $options(['appearance', 'history', 'weight', 'price']),
        'answer' => 'b',
        'explanation' => 'The speaker stresses records of origin and handling. Preserving a seed collection’s “history” retains that scientific context; the other endings do not complete the argument.',
    ],
    [
        'id' => 'pte-l12', 'type' => 'choice', 'task_type' => 'Select Missing Word', 'title' => 'Testing a station sign',
        'resource_id' => 'missing-design', 'prompt' => 'The final phrase has been replaced by a tone. Select the phrase that best completes the recording.',
        'options' => $options(['admire the colour scheme', 'recognise the designer’s style', 'memorise the station’s history', 'find their way confidently']),
        'answer' => 'd',
        'explanation' => 'The tests address legibility and direction. The final criterion is whether passengers can find their way confidently, rather than appreciate the visual design.',
    ],
    [
        'id' => 'pte-l13', 'type' => 'highlight', 'task_type' => 'Highlight Incorrect Words', 'title' => 'Community photographs',
        'resource_id' => 'hiw-archive', 'prompt' => 'Listen and select each word in the transcript that differs from the recording.',
        'text' => 'The archive began when residents donated paintings of the old harbour. Volunteers scanned each image and recorded the owner’s account of when and where it was taken. Some dates were certain, so the catalogue clearly distinguishes confirmed information from estimates. The team also invited people to identify unnamed workers and buildings. These contributions added valuable context, but conflicting memories were kept rather than quietly removed. Researchers can therefore see how an identification developed over time. The collection now supports school projects and local exhibitions, while the original photographs remain with their owners unless they choose to sell them permanently.',
        'answer' => [],
        'correct_text' => $resources['hiw-archive']['audio_script'],
        'explanation' => 'The recording says “photographs”, not “paintings”; “uncertain”, not “certain”; and “donate”, not “sell”. Select only the differing words: incorrect selections deduct practice points, with a minimum of zero.',
    ],
    [
        'id' => 'pte-l14', 'type' => 'highlight', 'task_type' => 'Highlight Incorrect Words', 'title' => 'Seminar room trial',
        'resource_id' => 'hiw-rooms', 'prompt' => 'Listen and select each word in the transcript that differs from the recording.',
        'text' => 'The university tested new seating arrangements in four seminar rooms last term. Students could move the heavy tables into small groups without blocking the main walkway. Tutors reported that discussions began more slowly, although some activities still required everyone to face the screen. The evaluation also identified a practical problem: there were too few power sockets near the centre of each room. Rather than replacing all the furniture immediately, the facilities team will remove safe charging points and repeat the trial. The final decision will consider accessibility, maintenance costs and feedback from both students and teaching staff.',
        'answer' => [],
        'correct_text' => $resources['hiw-rooms']['audio_script'],
        'explanation' => 'The recording says “lightweight”, not “heavy”; “quickly”, not “slowly”; and “add”, not “remove”. Correct selected tokens earn points; wrong selections deduct points down to zero.',
    ],
];
/* Derive zero-based whitespace token indices from explicit changed words, not punctuation. */
foreach ([12 => ['paintings', 'certain,', 'sell'], 13 => ['heavy', 'slowly,', 'remove']] as $itemIndex => $changedWords) {
    $tokens = preg_split('/\s+/u', trim($listening[$itemIndex]['text'])) ?: [];
    foreach ($changedWords as $word) {
        $index = array_search($word, $tokens, true);
        if ($index === false) {
            throw new RuntimeException('An original PTE highlight key could not be located.');
        }
        $listening[$itemIndex]['answer'][] = (string) $index;
    }
}
foreach ($dictations as $i => $sentence) {
    $listening[] = [
        'id' => 'pte-l' . ($i + 15), 'type' => 'short', 'task_type' => 'Write from Dictation',
        'title' => 'Write from Dictation ' . ($i + 1), 'resource_id' => 'dictation-' . ($i + 1),
        'prompt' => 'Listen once and type the complete sentence you hear.',
        'instructions' => 'Write the complete sentence. This practice item uses one point for an exact sentence match after case, punctuation and whitespace normalisation; it does not reproduce official word-level scoring.',
        'answer' => [$sentence],
        'explanation' => 'The sentence is: “' . $sentence . '” This simplified practice mark requires the complete sentence; case, punctuation and whitespace are normalised. Official PTE integrated and word-level scoring are not reproduced.',
    ];
}

return [
    'id' => 'pte-academic-01', 'exam' => 'PTE', 'name' => 'PTE Academic · Full mock 01',
    'version' => '2026-10-original-01',
    'description' => 'An original 68-item Academic mock covering all 22 task formats, including Summarize Group Discussion and Respond to a Situation. Explore campus, research, community and workplace topics across three timed sections.',
    'format_note' => 'Original, unofficial practice with configured budgets of 80 minutes Speaking & Writing, 25 minutes Reading and 35 minutes Listening (140 minutes total). Actual test counts and timings vary. Objective results are raw practice points with deductions for multiple selections and incorrect-word selections; writing and speaking require self-review. No official PTE 10–90 score or integrated skill score is estimated. Browser speech is a practice stimulus, not an official recording. Write from Dictation uses simplified whole-sentence marking.',
    'resources' => $resources,
    'sections' => [
        [
            'id' => 'pte-speaking-writing', 'title' => 'Speaking & Writing', 'duration_seconds' => 4800, 'navigation' => 'forward',
            'instructions' => 'Complete 35 tasks in order. Once you move forward, you cannot return in exam mode. Use the preparation and response limits shown for spoken tasks. Listen once to audio stimuli; notes are allowed. Read Aloud uses 35 seconds of preparation and 40 seconds of speaking in this configured mock. Written summaries allow 10 minutes each and the essay 20 minutes, within the section budget. Optional local recordings are for your own review and are not automatically scored. The five Answer Short Question responses are spoken and ungraded, with example answers available after submission.',
            'items' => $speakingWriting,
        ],
        [
            'id' => 'pte-reading', 'title' => 'Reading', 'duration_seconds' => 1500, 'navigation' => 'forward',
            'instructions' => 'Complete 15 tasks in order within 25 minutes. Choose words that fit meaning and grammar. Multiple-answer tasks deduct for wrong selections, down to zero; this mock contains two correct options in each. Reorder tasks award practice points for correctly adjacent paragraph pairs. Each correct gap or single-choice response earns one raw practice point.',
            'items' => $reading,
        ],
        [
            'id' => 'pte-listening', 'title' => 'Listening', 'duration_seconds' => 2100, 'navigation' => 'forward',
            'instructions' => 'Complete 18 tasks in order within 35 minutes. Listen once in exam mode and take notes if useful. Allow up to 10 minutes for Summarize Spoken Text, including its audio. Multiple-answer and incorrect-word tasks deduct for incorrect selections down to zero. For missing-word tasks, the words “tone” mark the omitted ending in the browser speech stimulus. Dictation uses simplified one-point whole-sentence marking, not official word-level scoring. Transcripts and replay assistance belong to assisted practice and are reflected in the result.',
            'items' => $listening,
        ],
    ],
];

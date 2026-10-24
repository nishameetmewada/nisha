<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/generated-library.php';
$all = in_array('--all',$argv,true); $questions = 0; $mocks = 0; $semantics = [];
foreach(nisha_generated_courses() as $course=>$definition) {
    $limit=$all?7500:20;
    for($n=1;$n<=$limit;$n++) {
        $session=nisha_generated_question_session($course,$n); nisha_content_validate_session($session);
        $semantics[nisha_content_semantic_hash($session)]=true; ++$questions;
    }
    $limit=$all?750:2;
    for($n=1;$n<=$limit;$n++) { $session=nisha_generated_mock($course,$n); nisha_content_validate_session($session); ++$mocks; }
}
echo "Validated draft structure: $questions question slots and $mocks assembled paper slots.\n";
echo 'Distinct normalised question structures in this run: '.count($semantics).".\n";
echo "This is not teacher review and does not publish anything. Repeated or unsuitable draft variations must be edited or rejected by approved teachers.\n";
echo "Published inventory is read from the human review ledger, never from this validator.\n";

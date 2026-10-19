<?php
declare(strict_types=1);

// For a new install, copy this file to config.php and enter the MySQL details from your hosting
// control panel. Keep config.php private; it is excluded from version control.
// On an upgrade, add the AI entries to your existing private config without
// replacing database credentials. Alternatively use DB_* / NISHA_OPENAI_* env vars.
return [
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => '',
    'db_user' => '',
    'db_password' => '',
    // Add these to your EXISTING private config.php; preserve your database values.
    // A separately billed OpenAI API project/key is required. Never put this key in JS.
    'openai_api_key' => '',
    'openai_model' => 'gpt-4.1-mini',
    'openai_transcription_model' => 'gpt-4o-mini-transcribe',
    'ai_reviews_per_student_day' => 10,
    'ai_reviews_per_day' => 100,
];

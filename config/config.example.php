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
    // Existing registered account IDs only. Or use the private CLI owner setup.
    'admin_user_ids' => [],
    // Hosted Stripe Checkout; no charges until configured and enabled by owner.
    'payments_enabled' => false,
    'payment_amount_minor' => 2900,
    'payment_currency' => 'aud',
    'payment_access_days' => 30,
    'site_url' => 'https://nishaglobal.com',
    'stripe_secret_key' => '',
    'stripe_webhook_secret' => '',
    // Rewards are 90% of verified positive net profit, shared by qualified views.
    // The owner must activate policy and approve each closed accounting period.
    'teacher_rewards_enabled' => false,
    'teacher_reward_share_percent' => 90,
    'teacher_reward_currency' => 'AUD',
    'teacher_qualified_watch_percent' => 70,
    'teacher_paid_views_only' => true,
    // Exact HTTPS hosts for teacher-owned MP4/WebM links, e.g. your video CDN.
    'teacher_video_hosts' => [],
];

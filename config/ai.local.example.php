<?php
// config/ai.local.example.php
// Copy to config/ai.local.php and paste your Gemini API key.
//
// Get a free key: https://aistudio.google.com/apikey (sign in with a Google account → Create API key).
// Free-tier note: Google may use free-tier prompts (the concern text) to improve its models.
// Mention this in the system's privacy notice.

return [
    'provider'       => 'gemini',
    'gemini_api_key' => 'paste-your-key-here',
];

<?php
// config/ai.php - AI prioritization settings
//
// DEFAULT: without an API key the built-in rule-based engine (includes/ai_engine.php) is used.
// TO USE GOOGLE GEMINI: copy config/ai.local.example.php to config/ai.local.php and paste your key
// from https://aistudio.google.com/apikey. ai.local.php is not in Git.

$aiConfig = [
    'provider'          => 'gemini',            // 'gemini' | 'rules'
    'gemini_api_key'    => getenv('GEMINI_API_KEY') ?: '',
    'gemini_model'      => 'gemini-flash-lite-latest', // Google's alias for the current Flash-Lite model (fast, light demand)
    'gemini_fallback_model' => 'gemini-3.5-flash-lite', // tried once when the main model is busy or retired
    'thinking_budget'   => null,                // null = model default (newer models reject a thinking budget)
    'timeout'           => 12,                  // seconds to wait for Gemini before using the rules (usually answers in ~3 s)
    'backoff_seconds'   => 60,                  // after a quota/server error, use the rules for this long
];

$local = __DIR__ . '/ai.local.php';
if (is_file($local)) {
    $aiConfig = array_merge($aiConfig, (array)require $local);
}

return $aiConfig;

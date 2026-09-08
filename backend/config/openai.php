<?php
return [
    'api_key' => env('OPENAI_API_KEY'),
    'organization' => env('OPENAI_ORGANIZATION'),
    'project' => env('OPENAI_PROJECT'),
    'base_uri' => env('OPENAI_BASE_URL'),
    'request_timeout' => env('OPENAI_REQUEST_TIMEOUT', 30),
];
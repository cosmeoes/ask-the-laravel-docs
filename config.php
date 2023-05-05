<?php

return [
    'redis' => [
        'scheme' => 'tcp',
        'host'   => 'localhost',
        'port'   => 6379,
        'index_name' => 'laravel-docs-index'
    ],
    'openai' => [
        'api_key' => $_ENV['OPENAI_API_KEY'],
        'embeddings_model' => 'text-embedding-ada-002',
        'completions_model' => 'gpt-3.5-turbo',
    ],
    'docs_path' => 'docs/'
];

<?php
// backend/api/test_parser.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/parser.php';

// --- Test Cases ---

$test_cases = [
    'Standard WhatsApp' => [
        'input' => "[8/15/2024, 10:30 AM] John Doe: This is a test message.",
        'expected' => [
            'type' => 'chat',
            'data' => [
                [
                    'Date' => '8/15/2024',
                    'Time' => '10:30 AM',
                    'Sender' => 'John Doe',
                    'Message' => 'This is a test message.'
                ]
            ]
        ]
    ],
    'ISO Format with Multi-line' => [
        'input' => "[2024-08-15, 10:30:45] Jane Smith: Another test message.\nAnd this is a second line.",
        'expected' => [
            'type' => 'chat',
            'data' => [
                [
                    'Date' => '2024-08-15',
                    'Time' => '10:30:45',
                    'Sender' => 'Jane Smith',
                    'Message' => "Another test message.\nAnd this is a second line."
                ]
            ]
        ]
    ],
    'Tricky Multi-line with fake timestamp' => [
        'input' => "[2024.08.15, 10:32:00] Captain Hook: A message with a link\nthat might look like a timestamp: [2024-01-01] but isn't.",
        'expected' => [
            'type' => 'chat',
            'data' => [
                [
                    'Date' => '2024.08.15',
                    'Time' => '10:32:00',
                    'Sender' => 'Captain Hook',
                    'Message' => "A message with a link\nthat might look like a timestamp: [2024-01-01] but isn't."
                ]
            ]
        ]
    ],
    'Empty Input' => [
        'input' => '',
        'expected' => ['type' => 'text', 'data' => []]
    ],
    'Plain Text (URLs)' => [
        'input' => "https://google.com\nhttps://cloudflare.com\nhttps://github.com",
        'expected' => [
            'type' => 'text',
            'data' => [
                ['content' => 'https://google.com'],
                ['content' => 'https://cloudflare.com'],
                ['content' => 'https://github.com']
            ]
        ]
    ],
    'Plain Text (Mixed)' => [
        'input' => "This is a line.\n\nAnother line, with some spaces.  \nAnd a final line.",
        'expected' => [
            'type' => 'text',
            'data' => [
                ['content' => 'This is a line.'],
                ['content' => ''],
                ['content' => 'Another line, with some spaces.'],
                ['content' => 'And a final line.']
            ]
        ]
    ],
    'File with only garbage lines' => [
        'input' => "This is just some random text.\nAnd another line.",
        'expected' => [
            'type' => 'text',
            'data' => [
                ['content' => 'This is just some random text.'],
                ['content' => 'And another line.']
            ]
        ]
    ]
];

// --- Test Runner ---

$results = ['passed' => 0, 'failed' => 0];

echo "Running Parser Tests...\n\n";

foreach ($test_cases as $name => $case) {
    $actual_output = parseChatLog($case['input']);

    if (json_encode($actual_output) === json_encode($case['expected'])) {
        echo "✅ PASSED: $name\n";
        $results['passed']++;
    } else {
        echo "❌ FAILED: $name\n";
        echo "  Input:\n---\n" . $case['input'] . "\n---\n";
        echo "  Expected:\n---\n" . json_encode($case['expected'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n---\n";
        echo "  Actual:\n---\n" . json_encode($actual_output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n---\n\n";
        $results['failed']++;
    }
}

echo "\n--- Test Summary ---\n";
echo "Passed: {$results['passed']}\n";
echo "Failed: {$results['failed']}\n";
echo "--------------------\n";

if ($results['failed'] > 0) {
    exit(1);
}
exit(0);

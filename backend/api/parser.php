<?php
// backend/api/parser.php

/**
 * Parses the text content of a file, detecting whether it's a structured
 * chat log or generic plain text.
 *
 * @param string $fileContent The raw text content from the file.
 * @return array A structured array indicating the type of content and the parsed data.
 *               Example for chat: ['type' => 'chat', 'data' => [[...]]]
 *               Example for text: ['type' => 'text', 'data' => [['content' => '...']]]
 */
function parseChatLog(string $fileContent): array {
    $trimmedContent = trim($fileContent);
    if (empty($trimmedContent)) {
        return ['type' => 'text', 'data' => []];
    }

    $lines = explode("\n", $trimmedContent);

    // Heuristic: Check if the first non-empty line looks like a chat message.
    $firstLine = '';
    foreach ($lines as $line) {
        if (trim($line) !== '') {
            $firstLine = trim($line);
            break;
        }
    }

    $chatPattern = '/^\[(\d{1,4}[-\/\.]\d{1,2}[-\/\.]\d{1,4}),?\s+(\d{1,2}:\d{1,2}(?::\d{1,2})?\s*(?:AM|PM)?)\]\s+([^:]+):\s+(.*)$/U';

    if (preg_match($chatPattern, $firstLine)) {
        // --- Looks like a CHAT LOG ---
        $parsedData = [];
        $currentMessage = null;

        foreach ($lines as $line) {
            $line = rtrim($line); // Keep leading whitespace for multiline messages but trim trailing.
            if (empty($line) && $currentMessage === null) continue;

            if (preg_match($chatPattern, $line, $matches)) {
                if ($currentMessage) {
                    $parsedData[] = $currentMessage;
                }
                $currentMessage = [
                    'Date' => trim($matches[1]),
                    'Time' => trim($matches[2]),
                    'Sender' => trim($matches[3]),
                    'Message' => trim($matches[4])
                ];
            } else if ($currentMessage) {
                $currentMessage['Message'] .= "\n" . $line;
            }
        }

        if ($currentMessage) {
            $parsedData[] = $currentMessage;
        }

        return ['type' => 'chat', 'data' => $parsedData];
    } else {
        // --- Looks like PLAIN TEXT ---
        $textData = [];
        // Use original lines before trimming everything
        $originalLines = explode("\n", $fileContent);
        foreach ($originalLines as $line) {
            $textData[] = ['content' => rtrim($line)];
        }
        return ['type' => 'text', 'data' => $textData];
    }
}
?>

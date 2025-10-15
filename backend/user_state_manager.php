<?php

// Define the path for our simple state-tracking file.
const STATE_FILE = __DIR__ . '/user_states.json';

/**
 * Gets the current state for a given user (chat_id).
 *
 * @param int $userId The user's Telegram Chat ID.
 * @return string|null The user's current state or null if not set.
 */
function getUserState($userId) {
    // Before reading, check if the file exists and is readable.
    if (!file_exists(STATE_FILE) || !is_readable(STATE_FILE)) {
        return null;
    }

    $content = file_get_contents(STATE_FILE);
    if ($content === false) {
        return null; // Failed to read
    }

    $states = json_decode($content, true);

    // Check for JSON errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        // If the file is corrupt, we can't determine state.
        return null;
    }

    return $states[$userId] ?? null;
}

/**
 * Sets the state for a given user.
 *
 * @param int $userId The user's Telegram Chat ID.
 * @param string|null $state The state to set. Use null to clear the state.
 * @return bool True on success, false on failure.
 */
function setUserState($userId, $state) {
    $states = [];
    // Check if the directory is writable. This is the most critical check.
    $directory = dirname(STATE_FILE);
    if (!is_writable($directory)) {
        return false; // Cannot write, so fail early.
    }

    // Check if the file itself is writable if it exists.
    if (file_exists(STATE_FILE) && !is_writable(STATE_FILE)) {
        return false;
    }

    if (file_exists(STATE_FILE) && is_readable(STATE_FILE)) {
        $content = file_get_contents(STATE_FILE);
        if ($content !== false) {
            $states = json_decode($content, true);
            // If JSON is invalid, reset to avoid corrupting the file further.
            if (json_last_error() !== JSON_ERROR_NONE) {
                $states = [];
            }
        }
    }

    if ($state === null) {
        unset($states[$userId]);
    } else {
        $states[$userId] = $state;
    }

    // Attempt to write the file. The @ suppresses PHP's warning on failure,
    // allowing our custom logic to handle the error.
    $result = @file_put_contents(STATE_FILE, json_encode($states, JSON_PRETTY_PRINT), LOCK_EX);

    // Return true if the write was successful, false otherwise.
    return $result !== false;
}
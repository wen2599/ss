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
    if (!file_exists(STATE_FILE)) {
        return null;
    }

    $states = json_decode(file_get_contents(STATE_FILE), true);
    return $states[$userId] ?? null;
}

/**
 * Sets the state for a given user.
 *
 * @param int $userId The user's Telegram Chat ID.
 * @param string|null $state The state to set. Use null to clear the state.
 */
function setUserState($userId, $state) {
    $states = [];
    if (file_exists(STATE_FILE)) {
        $content = file_get_contents(STATE_FILE);
        // Handle case where file is empty or contains invalid JSON
        $states = $content ? json_decode($content, true) : [];
        if (json_last_error() !== JSON_ERROR_NONE) {
             error_log("Error decoding user state file: " . json_last_error_msg());
             $states = []; // Reset states if file is corrupt
        }
    }

    if ($state === null) {
        unset($states[$userId]);
    } else {
        $states[$userId] = $state;
    }

    // Check if the directory is writable before attempting to write.
    $directory = dirname(STATE_FILE);
    if (!is_writable($directory)) {
        error_log("State file directory is not writable: {$directory}");
        return false;
    }

    // Attempt to write the file and check for failure.
    $result = file_put_contents(STATE_FILE, json_encode($states, JSON_PRETTY_PRINT));

    if ($result === false) {
        error_log("Failed to write to state file: " . STATE_FILE);
        return false;
    }

    return true;
}
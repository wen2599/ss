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
        return null; // File doesn't exist, so no state.
    }
    if (!is_readable(STATE_FILE)) {
        error_log("getUserState failed: state file is not readable at " . STATE_FILE);
        return null;
    }

    $content = @file_get_contents(STATE_FILE);
    if ($content === false) {
        error_log("getUserState failed: could not read content from " . STATE_FILE);
        return null;
    }

    $states = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("getUserState failed: invalid JSON in state file. Error: " . json_last_error_msg());
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
    $directory = dirname(STATE_FILE);

    // Pre-emptive check for directory writability
    if (!is_writable($directory)) {
        error_log("setUserState failed: directory is not writable at " . $directory);
        return false;
    }
    // Pre-emptive check for file writability if it exists
    if (file_exists(STATE_FILE) && !is_writable(STATE_FILE)) {
        error_log("setUserState failed: state file is not writable at " . STATE_FILE);
        return false;
    }

    // Read existing states if possible
    if (file_exists(STATE_FILE)) {
        if (!is_readable(STATE_FILE)) {
            error_log("setUserState failed: state file exists but is not readable.");
            return false;
        }
        $content = @file_get_contents(STATE_FILE);
        if ($content !== false) {
            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $states = $decoded;
            } else {
                error_log("setUserState warning: could not decode JSON from state file, will overwrite. Error: " . json_last_error_msg());
            }
        }
    }

    // Update state
    if ($state === null) {
        unset($states[$userId]);
    } else {
        $states[$userId] = $state;
    }

    // Write updated states back to the file
    $jsonPayload = json_encode($states, JSON_PRETTY_PRINT);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("setUserState failed: could not encode states to JSON. Error: " . json_last_error_msg());
        return false;
    }

    $result = @file_put_contents(STATE_FILE, $jsonPayload, LOCK_EX);

    if ($result === false) {
        error_log("setUserState failed: file_put_contents returned false for " . STATE_FILE);
    }

    return $result !== false;
}
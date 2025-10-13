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
        $states = json_decode(file_get_contents(STATE_FILE), true);
    }

    if ($state === null) {
        // Clear the state for the user.
        unset($states[$userId]);
    } else {
        // Set the new state for the user.
        $states[$userId] = $state;
    }

    file_put_contents(STATE_FILE, json_encode($states));
}
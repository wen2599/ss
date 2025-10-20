<?php
// backend/user_state_manager.php
// Manages user state and associated data for interactive conversations with the Telegram bot.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db_operations.php';

// Define user states for the Telegram bot
const STATE_NONE = 'none';
const STATE_AWAITING_USERNAME = 'awaiting_username';
const STATE_AWAITING_PASSWORD = 'awaiting_password';
const STATE_AWAITING_EMAIL = 'awaiting_email';
const STATE_AWAITING_REGISTER_USERNAME = 'awaiting_register_username';
const STATE_AWAITING_REGISTER_EMAIL = 'awaiting_register_email';
const STATE_AWAITING_REGISTER_PASSWORD = 'awaiting_register_password';
const STATE_AWAITING_LOGIN_USERNAME = 'awaiting_login_username';
const STATE_AWAITING_LOGIN_PASSWORD = 'awaiting_login_password';

/**
 * Gets the current state and associated data of a user.
 * @param PDO $pdo The PDO database connection object.
 * @param string $telegramChatId The Telegram chat ID of the user.
 * @return array An associative array containing 'state' (string) and 'data' (array), defaults to STATE_NONE and empty array.
 */
function getUserStateAndData(PDO $pdo, string $telegramChatId): array
{
    $user = fetchOne($pdo, "SELECT user_state, state_data FROM users WHERE telegram_chat_id = :chat_id", [':chat_id' => $telegramChatId]);
    if ($user) {
        return [
            'state' => $user['user_state'] ?? STATE_NONE,
            'data' => json_decode($user['state_data'] ?? '{}', true) ?? []
        ];
    } else {
        return ['state' => STATE_NONE, 'data' => []];
    }
}

/**
 * Sets the state and optionally updates associated data for a user.
 * @param PDO $pdo The PDO database connection object.
 * @param string $telegramChatId The Telegram chat ID of the user.
 * @param string $state The new state to set.
 * @param array $stateData Optional: new data to store with the state.
 * @return bool True on success, false on failure.
 */
function setUserStateAndData(PDO $pdo, string $telegramChatId, string $state, array $stateData = []): bool
{
    $dataToUpdate = [
        'user_state' => $state,
        'state_data' => json_encode($stateData)
    ];
    $affectedRows = update($pdo, 'users', $dataToUpdate, 'telegram_chat_id = :chat_id', [':chat_id' => $telegramChatId]);
    return $affectedRows > 0;
}

/**
 * Clears the state and data of a user (sets state to STATE_NONE and data to empty).
 * @param PDO $pdo The PDO database connection object.
 * @param string $telegramChatId The Telegram chat ID of the user.
 * @return bool True on success, false on failure.
 */
function clearUserStateAndData(PDO $pdo, string $telegramChatId): bool
{
    return setUserStateAndData($pdo, $telegramChatId, STATE_NONE, []);
}

/**
 * Links a Telegram chat ID to an existing user.
 * @param PDO $pdo The PDO database connection object.
 * @param int $userId The ID of the user to link.
 * @param string $telegramChatId The Telegram chat ID to link.
 * @return bool True on success, false on failure.
 */
function linkTelegramUser(PDO $pdo, int $userId, string $telegramChatId): bool
{
    $affectedRows = update($pdo, 'users', ['telegram_chat_id' => $telegramChatId], 'id = :user_id', [':user_id' => $userId]);
    return $affectedRows > 0;
}

/**
 * Registers a new user via Telegram.
 * @param PDO $pdo The PDO database connection object.
 * @param string $username The desired username.
 * @param string $email The user's email.
 * @param string $password The user's password (will be hashed).
 * @param string $telegramChatId The Telegram chat ID to link immediately.
 * @return int|false The new user's ID on success, false on failure.
 */
function registerUserViaTelegram(PDO $pdo, string $username, string $email, string $password, string $telegramChatId): int|false
{
    // Check if username or email already exists
    $existingUser = fetchOne($pdo, "SELECT id FROM users WHERE username = :username OR email = :email", [
        ':username' => $username,
        ':email' => $email
    ]);

    if ($existingUser) {
        return false; // User already exists
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    try {
        $userId = insert($pdo, 'users', [
            'username' => $username,
            'password' => $hashed_password,
            'email' => $email,
            'telegram_chat_id' => $telegramChatId,
            'user_state' => STATE_NONE // Initial state
        ]);
        return $userId;
    } catch (PDOException $e) {
        error_log("Telegram registration database error: " . $e->getMessage());
        return false;
    }
}

/**
 * Authenticates a user for Telegram login.
 * @param PDO $pdo The PDO database connection object.
 * @param string $usernameOrEmail The username or email.
 * @param string $password The plain text password.
 * @return array|false An associative array of user data (id, username, email) on success, false on failure.
 */
function authenticateTelegramUser(PDO $pdo, string $usernameOrEmail, string $password): array|false
{
    $user = fetchOne($pdo, "SELECT id, username, email, password FROM users WHERE username = :username_or_email OR email = :username_or_email", [
        ':username_or_email' => $usernameOrEmail
    ]);

    if ($user && password_verify($password, $user['password'])) {
        unset($user['password']); // Don't return hashed password
        return $user;
    }
    return false;
}

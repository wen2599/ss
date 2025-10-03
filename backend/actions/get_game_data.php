<?php
/**
 * Action: get_game_data
 *
 * This script retrieves static data required for the game, such as the color map
 * for lottery numbers. This data is used by the frontend to render UI elements correctly.
 *
 * HTTP Method: GET
 *
 * Response:
 * - On success: { "success": true, "colorMap": { ... } }
 * - On error: { "success": false, "error": "Error message." }
 */

// The main router (index.php) handles initialization.
// Global variables $pdo and $log are available.

use App\GameData;

try {
    // Access the static color map from the GameData class, now handled by the autoloader.
    $colorMap = GameData::$colorMap;

    http_response_code(200);
    $log->info("Successfully retrieved game data (color map).");
    echo json_encode(['success' => true, 'colorMap' => $colorMap]);

} catch (Throwable $e) {
    // Use Throwable to catch both Errors (e.g., class not found) and Exceptions.
    $log->error("Failed to retrieve game data.", ['error' => $e->getMessage()]);

    // Let the global exception handler in init.php manage the final response.
    throw $e;
}
?>
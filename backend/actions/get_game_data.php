<?php
// Action: Get static game data (e.g., color maps)

// The GameData library is already included by the index.php router
// but for clarity and potential standalone use, we can include it again.
require_once __DIR__ . '/../lib/GameData.php';

try {
    // We only need the color map for this feature
    $colorMap = GameData::$colorMap;

    http_response_code(200);
    echo json_encode(['success' => true, 'colorMap' => $colorMap]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to retrieve game data.']);
}
?>

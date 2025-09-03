<?php
require_once 'db.php';
require_once 'game.php';

/**
 * Saves the current state of a Game object to the database.
 * This is a comprehensive update that writes to multiple tables.
 * @param Game $game The game object to save.
 * @return bool True on success, false on failure.
 */
function save_game_state(Game $game): bool {
    $conn = get_db();
    $conn->begin_transaction();

    try {
        $gameId = $game->getGameId();
        $state = $game->getFullStateForDb();
        $playerIds = $game->getPlayerIds();

        // 1. Update the `games` table
        // Determine winner info if game is finished
        $winnerId = null;
        $winnerSide = null;
        if ($state['gameState'] === 'finished') {
            $winnerId = $state['lastPlayerId'];
            $winnerSide = ($winnerId === $state['landlordPlayerId']) ? 'landlord' : 'peasants';
        }

        $stmt_game = $conn->prepare(
            "UPDATE games SET
                game_state = ?,
                landlord_id = ?,
                current_turn_player_id = ?,
                bottom_cards = ?,
                current_bid = ?,
                bids_history = ?,
                last_played_cards = ?,
                last_player_id = ?,
                winner_id = ?,
                winner_side = ?
            WHERE id = ?"
        );

        $landlordId = $state['landlordPlayerId'];
        $currentTurn = $state['currentTurnPlayerId'];
        $bottomCardsJson = json_encode($state['landlordsCards']);
        $highestBid = $state['highestBid'];
        $bidsHistoryJson = json_encode($state['bids']);
        $lastPlayedCardsJson = json_encode($state['lastPlayedCards']);
        $lastPlayerId = $state['lastPlayerId'];

        $stmt_game->bind_param("ssssisssssi",
            $state['gameState'],
            $landlordId,
            $currentTurn,
            $bottomCardsJson,
            $highestBid,
            $bidsHistoryJson,
            $lastPlayedCardsJson,
            $lastPlayerId,
            $winnerId,
            $winnerSide,
            $gameId
        );
        $stmt_game->execute();
        $stmt_game->close();

        // 2. Find the room_id associated with this game
        $stmt_get_room = $conn->prepare("SELECT room_id FROM games WHERE id = ?");
        $stmt_get_room->bind_param("i", $gameId);
        $stmt_get_room->execute();
        $result = $stmt_get_room->get_result();
        $room = $result->fetch_assoc();
        $roomId = $room['room_id'] ?? null;
        $stmt_get_room->close();

        if (!$roomId) {
            throw new Exception("Could not find room for game ID: " . $gameId);
        }

        // 3. Update each player's state in `room_players`
        $stmt_player = $conn->prepare(
            "UPDATE room_players SET hand_cards = ?, is_landlord = ? WHERE room_id = ? AND user_id = ?"
        );

        foreach ($playerIds as $playerId) {
            $player = $game->getPlayer($playerId);
            if ($player) {
                $handJson = json_encode($player->getHand());
                $isLandlordInt = $player->isLandlord() ? 1 : 0;
                $stmt_player->bind_param("siis", $handJson, $isLandlordInt, $roomId, $playerId);
                $stmt_player->execute();
            }
        }
        $stmt_player->close();

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        // error_log("Failed to save game state: " . $e->getMessage());
        return false;
    }
}

function get_player_count(int $gameId): int {
    $conn = get_db();
    $stmt_get_room = $conn->prepare("SELECT room_id FROM games WHERE id = ?");
    $stmt_get_room->bind_param("i", $gameId);
    $stmt_get_room->execute();
    $result = $stmt_get_room->get_result();
    $room = $result->fetch_assoc();
    $roomId = $room['room_id'] ?? null;
    $stmt_get_room->close();

    if (!$roomId) return 0;

    $stmt_count = $conn->prepare("SELECT COUNT(*) as c FROM room_players WHERE room_id = ?");
    $stmt_count->bind_param("i", $roomId);
    $stmt_count->execute();
    $count = $stmt_count->get_result()->fetch_assoc()['c'];
    $stmt_count->close();

    return (int)$count;
}

function get_active_game_id_for_chat(string $chatId): ?int {
    $conn = get_db();
    $stmt = $conn->prepare(
        "SELECT current_game_id FROM rooms WHERE chat_id = ? AND state != 'finished'"
    );
    $stmt->bind_param("s", $chatId);
    $stmt->execute();
    $result = $stmt->get_result();
    $room = $result->fetch_assoc();
    $stmt->close();
    return $room['current_game_id'] ?? null;
}

function load_game_state(int $gameId): ?Game {
    $conn = get_db();

    // 1. Fetch game data
    $stmt_game = $conn->prepare("SELECT * FROM games WHERE id = ?");
    $stmt_game->bind_param("i", $gameId);
    $stmt_game->execute();
    $gameData = $stmt_game->get_result()->fetch_assoc();
    $stmt_game->close();
    if (!$gameData) return null;

    $roomId = $gameData['room_id'];

    // 2. Fetch player data
    $stmt_players = $conn->prepare("SELECT * FROM room_players WHERE room_id = ? ORDER BY seat ASC");
    $stmt_players->bind_param("i", $roomId);
    $stmt_players->execute();
    $playersResult = $stmt_players->get_result();
    $playersData = [];
    while ($row = $playersResult->fetch_assoc()) {
        $playersData[$row['user_id']] = $row;
    }
    $stmt_players->close();

    // 3. Reconstruct Game object
    $game = new Game($gameId); // Constructor takes gameId, which is not ideal but we'll work with it

    // Use Reflection to set private properties, avoiding complex constructors or public setters
    $reflection = new ReflectionObject($game);

    $players = [];
    foreach($playersData as $userId => $pData) {
        // Assuming player names can be fetched or are not critical for game state
        $player = new Player($userId, 'Player ' . $pData['seat']);
        $player->addCards(json_decode($pData['hand_cards'], true) ?? []);
        if($pData['is_landlord']) {
            $player->setLandlord(true);
        }
        $players[$userId] = $player;
    }

    $propertyMap = [
        'roomId' => $roomId,
        'players' => $players,
        'playerIds' => array_keys($playersData),
        'deck' => [], // Deck is always empty for a game in progress
        'landlordsCards' => json_decode($gameData['bottom_cards'], true) ?? [],
        'gameState' => $gameData['game_state'],
        'currentTurnPlayerId' => $gameData['current_turn_player_id'],
        'landlordPlayerId' => $gameData['landlord_id'],
        'bids' => json_decode($gameData['bids_history'], true) ?? [],
        'highestBid' => $gameData['current_bid'],
        'lastPlayedCards' => json_decode($gameData['last_played_cards'], true) ?? [],
        'lastPlayerId' => $gameData['last_player_id'],
    ];

    foreach ($propertyMap as $key => $value) {
        if ($reflection->hasProperty($key)) {
            $prop = $reflection->getProperty($key);
            $prop->setAccessible(true);
            $prop->setValue($game, $value);
        }
    }

    return $game;
}

function create_game_and_room(string $chatId, string $userId, string $userName): ?int {
    $conn = get_db();
    $conn->begin_transaction();
    try {
        // 1. Create a room and associate it with the chat
        $roomCode = uniqid();
        $stmt_room = $conn->prepare("INSERT INTO rooms (chat_id, room_code, state, created_at) VALUES (?, ?, 'waiting', NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id), room_code=VALUES(room_code)");
        $stmt_room->bind_param("ss", $chatId, $roomCode);
        $stmt_room->execute();
        $roomId = $conn->insert_id;
        $stmt_room->close();

        // 2. Create a game associated with the room
        $stmt_game = $conn->prepare("INSERT INTO games (room_id, game_state, created_at) VALUES (?, 'waiting', NOW())");
        $stmt_game->bind_param("i", $roomId);
        $stmt_game->execute();
        $gameId = $conn->insert_id;
        $stmt_game->close();

        // 3. Update room with current_game_id
        $stmt_update_room = $conn->prepare("UPDATE rooms SET current_game_id = ? WHERE id = ?");
        $stmt_update_room->bind_param("ii", $gameId, $roomId);
        $stmt_update_room->execute();
        $stmt_update_room->close();

        // 4. Add the creator as the first player
        // We need a user table to store names, for now, we'll ignore it.
        $stmt_player = $conn->prepare("INSERT INTO room_players (room_id, user_id, seat, joined_at) VALUES (?, ?, 1, NOW())");
        $stmt_player->bind_param("is", $roomId, $userId);
        $stmt_player->execute();
        $stmt_player->close();

        $conn->commit();
        return $gameId;

    } catch (Exception $e) {
        $conn->rollback();
        // error_log("Failed to create game: " . $e->getMessage());
        return null;
    }
}

function add_player_to_game(int $gameId, string $userId, string $userName): bool {
    $conn = get_db();
    $conn->begin_transaction();
    try {
        $stmt_get_room = $conn->prepare("SELECT room_id FROM games WHERE id = ?");
        $stmt_get_room->bind_param("i", $gameId);
        $stmt_get_room->execute();
        $result = $stmt_get_room->get_result();
        $room = $result->fetch_assoc();
        $roomId = $room['room_id'] ?? null;
        $stmt_get_room->close();

        if (!$roomId) return false;

        $stmt_count = $conn->prepare("SELECT COUNT(*) as c FROM room_players WHERE room_id = ?");
        $stmt_count->bind_param("i", $roomId);
        $stmt_count->execute();
        $count = $stmt_count->get_result()->fetch_assoc()['c'];
        $stmt_count->close();

        if ($count >= 3) return false; // Room is full

        $seat = $count + 1;
        $stmt_player = $conn->prepare("INSERT INTO room_players (room_id, user_id, seat, joined_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE seat=seat");
        $stmt_player->bind_param("isi", $roomId, $userId, $seat);
        $stmt_player->execute();
        $stmt_player->close();

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

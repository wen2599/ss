<?php
require_once 'db.php';
require_once 'game.php';

function save_game_state(Game $game): bool {
    $conn = get_db();
    $conn->begin_transaction();

    try {
        $roomId = $game->getGameId();
        $state = $game->getState();

        $stmt_room = $conn->prepare("UPDATE rooms SET state = ?, game_state = ? WHERE id = ?");
        $gameStateJson = json_encode($state['comparison_results']);
        $stmt_room->bind_param("ssi", $state['state'], $gameStateJson, $roomId);
        $stmt_room->execute();
        $stmt_room->close();

        $stmt_player = $conn->prepare(
            "UPDATE room_players SET hand_cards = ?, front_hand = ?, middle_hand = ?, back_hand = ?, hand_is_set = ? WHERE room_id = ? AND user_id = ?"
        );
        foreach ($state['players'] as $playerId => $player_data) {
            $handJson = json_encode($player_data['hand']);
            $frontJson = json_encode($player_data['front_hand']);
            $middleJson = json_encode($player_data['middle_hand']);
            $backJson = json_encode($player_data['back_hand']);
            $handIsSet = $player_data['hand_is_set'] ? 1 : 0;
            $stmt_player->bind_param("ssssiis", $handJson, $frontJson, $middleJson, $backJson, $handIsSet, $roomId, $playerId);
            $stmt_player->execute();
        }
        $stmt_player->close();

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

function get_player_count(int $roomId): int {
    $conn = get_db();
    $stmt_count = $conn->prepare("SELECT COUNT(*) as c FROM room_players WHERE room_id = ?");
    $stmt_count->bind_param("i", $roomId);
    $stmt_count->execute();
    $count = $stmt_count->get_result()->fetch_assoc()['c'];
    $stmt_count->close();
    return (int)$count;
}

function get_active_room_id_for_chat(string $chatId): ?int {
    $conn = get_db();
    $stmt = $conn->prepare(
        "SELECT id FROM rooms WHERE chat_id = ? AND state != 'finished' LIMIT 1"
    );
    $stmt->bind_param("s", $chatId);
    $stmt->execute();
    $result = $stmt->get_result();
    $room = $result->fetch_assoc();
    $stmt->close();
    return $room['id'] ?? null;
}

function load_game_state(int $roomId): ?Game {
    $conn = get_db();

    $stmt_room = $conn->prepare("SELECT * FROM rooms WHERE id = ?");
    $stmt_room->bind_param("i", $roomId);
    $stmt_room->execute();
    $roomData = $stmt_room->get_result()->fetch_assoc();
    $stmt_room->close();
    if (!$roomData) return null;

    $stmt_players = $conn->prepare("SELECT * FROM room_players WHERE room_id = ? ORDER BY seat ASC");
    $stmt_players->bind_param("i", $roomId);
    $stmt_players->execute();
    $playersResult = $stmt_players->get_result();

    $game = new Game($roomId);
    $reflection = new ReflectionObject($game);

    $players = [];
    $playerIds = [];
    while ($pData = $playersResult->fetch_assoc()) {
        $player = new Player($pData['user_id'], 'Player ' . $pData['seat']);
        $player->addCards(json_decode($pData['hand_cards'] ?? '[]', true));
        if ($pData['hand_is_set']) {
            $player->setHandSegments(
                json_decode($pData['front_hand'], true),
                json_decode($pData['middle_hand'], true),
                json_decode($pData['back_hand'], true)
            );
        }
        $players[$pData['user_id']] = $player;
        $playerIds[] = $pData['user_id'];
    }
    $stmt_players->close();

    $propertyMap = [
        'players' => $players,
        'playerIds' => $playerIds,
        'gameState' => $roomData['state'],
        'comparison_results' => json_decode($roomData['game_state'] ?? '[]', true),
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
        $roomCode = uniqid();
        $stmt_room = $conn->prepare("INSERT INTO rooms (room_code, state, created_at, chat_id) VALUES (?, 'waiting', NOW(), ?)");
        $stmt_room->bind_param("ss", $roomCode, $chatId);
        $stmt_room->execute();
        $roomId = $conn->insert_id;
        $stmt_room->close();

        $stmt_player = $conn->prepare("INSERT INTO room_players (room_id, user_id, seat) VALUES (?, ?, 1)");
        $stmt_player->bind_param("is", $roomId, $userId);
        $stmt_player->execute();
        $stmt_player->close();

        $conn->commit();
        return $roomId;

    } catch (Exception $e) {
        $conn->rollback();
        return null;
    }
}

function add_player_to_game(int $roomId, string $userId, string $userName): bool {
    $conn = get_db();
    $conn->begin_transaction();
    try {
        $count = get_player_count($roomId);

        if ($count >= 4) return false;

        $seat = $count + 1;
        $stmt_player = $conn->prepare("INSERT INTO room_players (room_id, user_id, seat) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE seat=seat");
        $stmt_player->bind_param("isi", $roomId, $userId, $seat);
        $stmt_player->execute();
        $stmt_player->close();

        if ($seat === 4) {
            $game = load_game_state($roomId);
            if ($game) {
                // The new player is already in the DB, so load_game_state will find them.
                // The addPlayer logic inside the Game constructor will handle dealing.
                save_game_state($game);
            }
        }

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

function get_player_ids_in_room(int $roomId): array {
    $conn = get_db();
    $stmt = $conn->prepare("SELECT user_id FROM room_players WHERE room_id = ? ORDER BY seat ASC");
    $stmt->bind_param("i", $roomId);
    $stmt->execute();
    $result = $stmt->get_result();
    $ids = [];
    while($row = $result->fetch_assoc()) {
        $ids[] = $row['user_id'];
    }
    $stmt->close();
    return $ids;
}

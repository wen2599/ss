<?php
// --- Card Utilities for Thirteen (十三张) ---

/**
 * Creates a standard 52-card deck.
 * @return array
 */
function create_deck(): array {
    $suits = ['S', 'H', 'D', 'C']; // Spades, Hearts, Diamonds, Clubs
    $ranks = ['2', '3', '4', '5', '6', '7', '8', '9', 'T', 'J', 'Q', 'K', 'A'];
    $deck = [];
    foreach ($suits as $suit) {
        foreach ($ranks as $rank) {
            $deck[] = $suit . $rank;
        }
    }
    return $deck;
}

/**
 * Shuffles the deck.
 * @param array $deck
 * @return array
 */
function shuffle_deck(array $deck): array {
    shuffle($deck);
    return $deck;
}

/**
 * Gets the numerical value of a card's rank.
 * @param string $card
 * @return int
 */
function get_rank_value(string $card): int {
    $rank = substr($card, 1);
    $values = ['2'=>2, '3'=>3, '4'=>4, '5'=>5, '6'=>6, '7'=>7, '8'=>8, '9'=>9, 'T'=>10, 'J'=>11, 'Q'=>12, 'K'=>13, 'A'=>14];
    return $values[$rank] ?? 0;
}

/**
 * Gets the suit of a card.
 * @param string $card
 * @return string
 */
function get_suit(string $card): string {
    return substr($card, 0, 1);
}

/**
 * Sorts an array of cards based on their rank value (descending).
 * @param array $cards
 * @return void
 */
function sort_cards_by_rank(array &$cards): void {
    usort($cards, function ($a, $b) {
        return get_rank_value($b) <=> get_rank_value($a);
    });
}

// --- Thirteen Card Analyzer ---

class ThirteenCardAnalyzer {
    // Hand types for 5-card hands, ordered by strength
    const TYPE_HIGH_CARD = 1;
    const TYPE_PAIR = 2;
    const TYPE_TWO_PAIR = 3;
    const TYPE_THREE_OF_A_KIND = 4;
    const TYPE_STRAIGHT = 5;
    const TYPE_FLUSH = 6;
    const TYPE_FULL_HOUSE = 7;
    const TYPE_FOUR_OF_A_KIND = 8;
    const TYPE_STRAIGHT_FLUSH = 9;

    // Hand types for 3-card hands
    const TYPE_FRONT_HIGH_CARD = 1;
    const TYPE_FRONT_PAIR = 2;
    const TYPE_FRONT_THREE_OF_A_KIND = 3;

    // Royalty point values
    const ROYALTY_BACK_FOUR_OF_A_KIND = 4;
    const ROYALTY_BACK_STRAIGHT_FLUSH = 5;
    const ROYALTY_MIDDLE_FULL_HOUSE = 2;
    const ROYALTY_MIDDLE_FOUR_OF_A_KIND = 8;
    const ROYALTY_MIDDLE_STRAIGHT_FLUSH = 10;
    const ROYALTY_FRONT_THREE_OF_A_KIND = 3;

    /**
     * Calculates the total royalty points for a set of three hands.
     * @param array $front_details Analyzed front hand.
     * @param array $middle_details Analyzed middle hand.
     * @param array $back_details Analyzed back hand.
     * @return array A map of points for each hand and the total.
     */
    public static function calculate_royalties(array $front_details, array $middle_details, array $back_details): array {
        $points = [
            'front' => 0,
            'middle' => 0,
            'back' => 0,
            'total' => 0
        ];

        // Front hand royalties
        if ($front_details['type'] === self::TYPE_FRONT_THREE_OF_A_KIND) {
            $points['front'] = self::ROYALTY_FRONT_THREE_OF_A_KIND;
        }

        // Middle hand royalties
        switch ($middle_details['type']) {
            case self::TYPE_FULL_HOUSE:
                $points['middle'] = self::ROYALTY_MIDDLE_FULL_HOUSE;
                break;
            case self::TYPE_FOUR_OF_A_KIND:
                $points['middle'] = self::ROYALTY_MIDDLE_FOUR_OF_A_KIND;
                break;
            case self::TYPE_STRAIGHT_FLUSH:
                $points['middle'] = self::ROYALTY_MIDDLE_STRAIGHT_FLUSH;
                break;
        }

        // Back hand royalties
        switch ($back_details['type']) {
            case self::TYPE_FOUR_OF_A_KIND:
                $points['back'] = self::ROYALTY_BACK_FOUR_OF_A_KIND;
                break;
            case self::TYPE_STRAIGHT_FLUSH:
                $points['back'] = self::ROYALTY_BACK_STRAIGHT_FLUSH;
                break;
        }

        $points['total'] = $points['front'] + $points['middle'] + $points['back'];
        return $points;
    }

    /**
     * Analyzes a hand of 3 or 5 cards to determine its type and value.
     * @param array $hand The hand to analyze.
     * @return array|null Details of the hand, or null if invalid size.
     */
    public static function analyze_hand(array $hand): ?array {
        $count = count($hand);
        if ($count === 5) {
            return self::analyze_5_card_hand($hand);
        }
        if ($count === 3) {
            return self::analyze_3_card_hand($hand);
        }
        return null;
    }

    private static function get_hand_metrics(array $hand): array {
        usort($hand, fn($a, $b) => get_rank_value($a) <=> get_rank_value($b));
        $ranks = array_map('get_rank_value', $hand);
        $suits = array_map('get_suit', $hand);
        $rank_counts = array_count_values($ranks);
        arsort($rank_counts); // Sort by frequency, then key
        return ['ranks' => $ranks, 'suits' => $suits, 'rank_counts' => $rank_counts];
    }

    private static function analyze_5_card_hand(array $hand): array {
        $m = self::get_hand_metrics($hand);
        $ranks = $m['ranks'];
        $suits = $m['suits'];
        $rank_counts = $m['rank_counts'];
        $primary_rank = array_key_first($rank_counts);

        // Check for straight and flush
        $is_flush = count(array_unique($suits)) === 1;
        $is_straight = false;
        if (count(array_unique($ranks)) === 5) {
            // Ace-low straight (A-2-3-4-5) check
            if ($ranks === [2, 3, 4, 5, 14]) {
                $is_straight = true;
                $primary_rank = 5; // Highest card in an A-5 straight is 5
            } elseif ($ranks[4] - $ranks[0] === 4) {
                $is_straight = true;
                $primary_rank = $ranks[4];
            }
        }

        // Evaluate hand type
        if ($is_straight && $is_flush) {
            return ['type' => self::TYPE_STRAIGHT_FLUSH, 'value' => $primary_rank, 'kickers' => []];
        }
        if (current($rank_counts) === 4) {
            return ['type' => self::TYPE_FOUR_OF_A_KIND, 'value' => $primary_rank, 'kickers' => array_keys(array_slice($rank_counts, 1))];
        }
        if (current($rank_counts) === 3 && next($rank_counts) === 2) {
            return ['type' => self::TYPE_FULL_HOUSE, 'value' => $primary_rank, 'kickers' => array_keys(array_slice($rank_counts, 1))];
        }
        if ($is_flush) {
            return ['type' => self::TYPE_FLUSH, 'value' => $primary_rank, 'kickers' => array_reverse(array_slice($ranks, 0, 4))];
        }
        if ($is_straight) {
            return ['type' => self::TYPE_STRAIGHT, 'value' => $primary_rank, 'kickers' => []];
        }
        if (current($rank_counts) === 3) {
            return ['type' => self::TYPE_THREE_OF_A_KIND, 'value' => $primary_rank, 'kickers' => array_keys(array_slice($rank_counts, 1))];
        }
        if (current($rank_counts) === 2 && next($rank_counts) === 2) {
            return ['type' => self::TYPE_TWO_PAIR, 'value' => $primary_rank, 'kickers' => array_keys(array_slice($rank_counts, 2))];
        }
        if (current($rank_counts) === 2) {
            return ['type' => self::TYPE_PAIR, 'value' => $primary_rank, 'kickers' => array_keys(array_slice($rank_counts, 1))];
        }
        return ['type' => self::TYPE_HIGH_CARD, 'value' => $primary_rank, 'kickers' => array_reverse(array_slice($ranks, 0, 4))];
    }

    private static function analyze_3_card_hand(array $hand): array {
        $m = self::get_hand_metrics($hand);
        $ranks = $m['ranks'];
        $rank_counts = $m['rank_counts'];
        $primary_rank = array_key_first($rank_counts);

        if (current($rank_counts) === 3) {
            return ['type' => self::TYPE_FRONT_THREE_OF_A_KIND, 'value' => $primary_rank, 'kickers' => []];
        }
        if (current($rank_counts) === 2) {
            return ['type' => self::TYPE_FRONT_PAIR, 'value' => $primary_rank, 'kickers' => array_keys(array_slice($rank_counts, 1))];
        }
        return ['type' => self::TYPE_FRONT_HIGH_CARD, 'value' => $primary_rank, 'kickers' => array_reverse(array_slice($ranks, 0, 2))];
    }

    /**
     * Compares two analyzed hands.
     * @return int 1 if handA > handB, -1 if handA < handB, 0 if equal.
     */
    public static function compare_hands(array $handA, array $handB): int {
        if ($handA['type'] !== $handB['type']) {
            return $handA['type'] > $handB['type'] ? 1 : -1;
        }
        if ($handA['value'] !== $handB['value']) {
            return $handA['value'] > $handB['value'] ? 1 : -1;
        }
        // Compare kickers
        for ($i = 0; $i < count($handA['kickers']); $i++) {
            if ($handA['kickers'][$i] !== $handB['kickers'][$i]) {
                return $handA['kickers'][$i] > $handB['kickers'][$i] ? 1 : -1;
            }
        }
        return 0; // Hands are identical
    }
}

// --- Game Class ---

class Game {
    /**
     * Starts a new game in a room.
     * @param mysqli $db The database connection.
     * @param int $room_id The ID of the room.
     * @return int The ID of the new game.
     * @throws Exception If the game fails to start.
     */
    public static function startGame(mysqli $db, int $room_id): int {
        $stmt = $db->prepare("SELECT user_id FROM room_players WHERE room_id = ?");
        $stmt->bind_param('i', $room_id);
        $stmt->execute();
        $players_res = $stmt->get_result();
        $players = [];
        while($row = $players_res->fetch_assoc()) {
            $players[] = $row['user_id'];
        }

        if (count($players) < 2) {
            throw new Exception('Not enough players (min 2).');
        }

        $deck = shuffle_deck(create_deck());
        $hands = array_fill_keys($players, []);
        for ($i = 0; $i < 13; $i++) {
            foreach ($players as $player_id) {
                $hands[$player_id][] = array_pop($deck);
            }
        }

        $stmt = $db->prepare("UPDATE room_players SET hand_cards = ? WHERE room_id = ? AND user_id = ?");
        foreach ($players as $player_id) {
            $hand_json = json_encode($hands[$player_id]);
            $stmt->bind_param('sii', $hand_json, $room_id, $player_id);
            $stmt->execute();
        }

        $stmt = $db->prepare("INSERT INTO games (room_id, game_state, created_at) VALUES (?, 'setting_hands', NOW())");
        $stmt->bind_param('i', $room_id);
        $stmt->execute();
        $game_id = $db->insert_id;

        $stmt = $db->prepare("UPDATE rooms SET state = 'playing', current_game_id = ? WHERE id = ?");
        $stmt->bind_param('ii', $game_id, $room_id);
        $stmt->execute();

        $stmt = $db->prepare("INSERT INTO player_hands (game_id, player_id) VALUES (?, ?)");
        foreach ($players as $player_id) {
            $stmt->bind_param('ii', $game_id, $player_id);
            $stmt->execute();
        }

        return $game_id;
    }

    /**
     * Submits a player's hand for a game.
     * @param mysqli $db
     * @param int $user_id
     * @param int $game_id
     * @param array $front
     * @param array $middle
     * @param array $back
     * @return void
     * @throws Exception
     */
    public static function submitHand(mysqli $db, int $user_id, int $game_id, array $front, array $middle, array $back): void {
        $analyzer = new ThirteenCardAnalyzer();
        $front_details = $analyzer->analyze_hand($front);
        $middle_details = $analyzer->analyze_hand($middle);
        $back_details = $analyzer->analyze_hand($back);
        $is_valid = $analyzer->compare_hands($back_details, $middle_details) >= 0 && $analyzer->compare_hands($middle_details, $front_details) >= 0;

        $stmt = $db->prepare("UPDATE player_hands SET is_submitted=1, is_valid=?, front_hand=?, middle_hand=?, back_hand=?, front_hand_details=?, middle_hand_details=?, back_hand_details=? WHERE game_id=? AND player_id=?");
        $stmt->bind_param('issssssii', $is_valid, json_encode($front), json_encode($middle), json_encode($back), json_encode($front_details), json_encode($middle_details), json_encode($back_details), $game_id, $user_id);
        $stmt->execute();

        // Check if all players have submitted
        $stmt = $db->prepare("SELECT COUNT(*) as submitted_count FROM player_hands WHERE game_id=? AND is_submitted=1");
        $stmt->bind_param('i', $game_id);
        $stmt->execute();
        $submitted_count = $stmt->get_result()->fetch_assoc()['submitted_count'];

        $stmt = $db->prepare("SELECT COUNT(*) as total_count FROM player_hands WHERE game_id=?");
        $stmt->bind_param('i', $game_id);
        $stmt->execute();
        $total_count = $stmt->get_result()->fetch_assoc()['total_count'];

        if ($submitted_count === $total_count) {
            self::calculateAndRecordScores($db, $game_id, $analyzer);
        }
    }

    /**
     * Calculates and records the scores for a finished game.
     * @param mysqli $db
     * @param int $game_id
     * @param ThirteenCardAnalyzer $analyzer
     * @return void
     */
    private static function calculateAndRecordScores(mysqli $db, int $game_id, ThirteenCardAnalyzer $analyzer): void {
        $stmt = $db->prepare("SELECT game_mode FROM rooms r JOIN games g ON r.id = g.room_id WHERE g.id = ?");
        $stmt->bind_param('i', $game_id);
        $stmt->execute();
        $game_mode = $stmt->get_result()->fetch_assoc()['game_mode'] ?? 'normal_2';
        $score_multipliers = ['normal_2' => ['base' => 2, 'double' => 1], 'normal_5' => ['base' => 5, 'double' => 1], 'double_2' => ['base' => 2, 'double' => 2], 'double_5' => ['base' => 5, 'double' => 2]];
        $multiplier = $score_multipliers[$game_mode];

        $stmt = $db->prepare("SELECT * FROM player_hands WHERE game_id=?");
        $stmt->bind_param('i', $game_id);
        $stmt->execute();
        $hands_res = $stmt->get_result();
        $all_hands = [];
        while($row = $hands_res->fetch_assoc()) {
            $all_hands[$row['player_id']] = ['isValid' => (bool)$row['is_valid'], 'front' => json_decode($row['front_hand_details'], true), 'middle' => json_decode($row['middle_hand_details'], true), 'back' => json_decode($row['back_hand_details'], true)];
        }

        $player_royalties = [];
        foreach($all_hands as $pid => $hand) {
            if ($hand['isValid']) {
                $player_royalties[$pid] = $analyzer->calculate_royalties($hand['front'], $hand['middle'], $hand['back']);
            } else {
                $player_royalties[$pid] = ['total' => 0, 'front' => 0, 'middle' => 0, 'back' => 0];
            }
        }

        $player_comparison_scores = array_fill_keys(array_keys($all_hands), 0);
        $player_ids = array_keys($all_hands);
        for ($i = 0; $i < count($player_ids); $i++) {
            for ($j = $i + 1; $j < count($player_ids); $j++) {
                $p1_id = $player_ids[$i];
                $p2_id = $player_ids[$j];
                $p1_hand = $all_hands[$p1_id];
                $p2_hand = $all_hands[$p2_id];
                $score_diff = 0;
                if (!$p1_hand['isValid'] && $p2_hand['isValid']) {
                    $score_diff = -6;
                } elseif ($p1_hand['isValid'] && !$p2_hand['isValid']) {
                    $score_diff = 6;
                } elseif ($p1_hand['isValid'] && $p2_hand['isValid']) {
                    $score_diff += $analyzer->compare_hands($p1_hand['front'], $p2_hand['front']);
                    $score_diff += $analyzer->compare_hands($p1_hand['middle'], $p2_hand['middle']);
                    $score_diff += $analyzer->compare_hands($p1_hand['back'], $p2_hand['back']);
                }
                $player_comparison_scores[$p1_id] += $score_diff;
                $player_comparison_scores[$p2_id] -= $score_diff;
            }
        }

        $total_royalty_sum = 0;
        foreach ($player_royalties as $royalty) {
            $total_royalty_sum += $royalty['total'];
        }

        $stmt_update_hand = $db->prepare("UPDATE player_hands SET round_score=?, score_details=? WHERE game_id=? AND player_id=?");
        $stmt_update_total_score = $db->prepare("UPDATE users SET points = points + ? WHERE id = ?");
        $num_players = count($player_ids);

        foreach($player_comparison_scores as $pid => $comp_score) {
            $player_royalty_total = $player_royalties[$pid]['total'];
            $total_royalty_payout = ($num_players * $player_royalty_total) - $total_royalty_sum;

            $final_round_score = ($comp_score * $multiplier['base'] * $multiplier['double']) + $total_royalty_payout;
            $score_details = json_encode([
                'comparison_score' => $comp_score,
                'base_points' => $multiplier['base'],
                'double_factor' => $multiplier['double'],
                'royalty_details' => $player_royalties[$pid],
                'total_royalty_payout' => $total_royalty_payout,
                'final_score' => $final_round_score
            ]);

            $stmt_update_hand->bind_param('isii', $final_round_score, $score_details, $game_id, $pid);
            $stmt_update_hand->execute();
            $stmt_update_total_score->bind_param('ii', $final_round_score, $pid);
            $stmt_update_total_score->execute();
        }
        $stmt_update_hand->close();
        $stmt_update_total_score->close();

        $stmt = $db->prepare("UPDATE games SET game_state='finished' WHERE id=?");
        $stmt->bind_param('i', $game_id);
        $stmt->execute();
    }
}

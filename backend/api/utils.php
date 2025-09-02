php
<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors', 1);
session_start();

// utils.php - Placeholder for utility functions

// Place helper functions here

/**
 * Creates a standard deck of 54 poker cards for Dou Dizhu.
 * Cards are represented as strings matching the frontend assets, e.g., "3_of_clubs.svg".
 *
 * @return array An ordered deck of cards.
 */
function create_deck() {
    $suits = ['spades', 'hearts', 'diamonds', 'clubs'];
    $values = ['3', '4', '5', '6', '7', '8', '9', '10', 'jack', 'queen', 'king', 'ace', '2'];

    $deck = [];
    foreach ($suits as $suit) {
        foreach ($values as $value) {
            $deck[] = $value . '_of_' . $suit . '.svg';
        }
    }
    $deck[] = 'black_joker.svg';
    $deck[] = 'red_joker.svg';
    return $deck;
}

/**
 * Example: Function to shuffle a deck of cards.
 */
function shuffle_deck($deck) {
    shuffle($deck);
    return $deck;
}

/**
 * Gets the numerical rank of a card for comparison.
 * @param string $card The card string (e.g., '10_of_spades.svg').
 * @return int The rank of the card.
 */
function get_card_rank($card) {
    if (strpos($card, 'red_joker') !== false) return 17;
    if (strpos($card, 'black_joker') !== false) return 16;

    $value_str = explode('_', $card)[0];

    $rank_map = [
        '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9, '10' => 10,
        'jack' => 11, 'queen' => 12, 'king' => 13, 'ace' => 14, '2' => 15
    ];

    return $rank_map[$value_str] ?? 0;
}

/**
 * Analyzes a set of cards to determine its type, value, and length.
 * This is the core of the Dou Dizhu validation logic.
 * @param array $cards An array of card strings.
 * @return array|false An array with hand details (type, value, length) or false if invalid.
 */
function get_hand_details($cards) {
    $count = count($cards);
    if ($count === 0) {
        return false;
    }

    // Get ranks and counts of each rank
    $ranks = array_map('get_card_rank', $cards);
    sort($ranks);
    $rank_counts = array_count_values($ranks);

    // --- Special Hands ---
    // Rocket (Jokers)
    if ($count === 2 && in_array(16, $ranks) && in_array(17, $ranks)) {
        return ['type' => 'rocket', 'value' => 17, 'length' => 1];
    }
    // Bomb
    if ($count === 4 && count($rank_counts) === 1) {
        return ['type' => 'bomb', 'value' => $ranks[0], 'length' => 1];
    }

    // --- Simple Hands ---
    // Solo
    if ($count === 1) {
        return ['type' => 'solo', 'value' => $ranks[0], 'length' => 1];
    }
    // Pair
    if ($count === 2 && count($rank_counts) === 1) {
        return ['type' => 'pair', 'value' => $ranks[0], 'length' => 1];
    }
    // Trio
    if ($count === 3 && count($rank_counts) === 1) {
        return ['type' => 'trio', 'value' => $ranks[0], 'length' => 1];
    }

    // --- Hands with Kickers ---
    // Trio with solo
    if ($count === 4 && count($rank_counts) === 2) {
        if (in_array(3, $rank_counts)) {
            $trio_rank = array_search(3, $rank_counts);
            return ['type' => 'trio_solo', 'value' => $trio_rank, 'length' => 1];
        }
    }
    // Trio with pair (Full House)
    if ($count === 5 && count($rank_counts) === 2) {
        if (in_array(3, $rank_counts)) {
            $trio_rank = array_search(3, $rank_counts);
            return ['type' => 'trio_pair', 'value' => $trio_rank, 'length' => 1];
        }
    }

    // --- Chain Hands ---
    $unique_ranks = array_keys($rank_counts);
    $is_consecutive = true;
    for ($i = 0; $i < count($unique_ranks) - 1; $i++) {
        if ($unique_ranks[$i] + 1 !== $unique_ranks[$i+1]) {
            $is_consecutive = false;
            break;
        }
    }
    // Straights cannot include 2 or jokers
    if (max($ranks) > 14) {
        $is_consecutive = false;
    }

    if ($is_consecutive) {
        // Solo Chain (Straight)
        if ($count >= 5 && count($rank_counts) === $count) {
            return ['type' => 'solo_chain', 'value' => max($ranks), 'length' => $count];
        }
        // Pair Chain (Sisters)
        if ($count >= 6 && ($count % 2 === 0) && count(array_unique(array_values($rank_counts))) === 1 && current($rank_counts) === 2) {
            return ['type' => 'pair_chain', 'value' => max($ranks), 'length' => $count / 2];
        }
        // Trio Chain (Airplane)
        if ($count >= 6 && ($count % 3 === 0) && count(array_unique(array_values($rank_counts))) === 1 && current($rank_counts) === 3) {
            return ['type' => 'airplane', 'value' => max($ranks), 'length' => $count / 3];
        }
    }

    // --- Airplane with Kickers ---
    if ($count > 0 && ($count % 4 === 0 || $count % 5 === 0)) {
        $trios = [];
        $kickers = [];
        foreach ($rank_counts as $rank => $num) {
            if ($num === 3) $trios[$rank] = $num;
            else $kickers[$rank] = $num;
        }

        if (!empty($trios)) {
            $trio_ranks = array_keys($trios);
            sort($trio_ranks);
            $is_trio_consecutive = true;
            for ($i = 0; $i < count($trio_ranks) - 1; $i++) {
                if ($trio_ranks[$i] + 1 !== $trio_ranks[$i+1] || $trio_ranks[$i] >= 15) {
                    $is_trio_consecutive = false;
                    break;
                }
            }

            if ($is_trio_consecutive) {
                // Airplane with small wings (solo kickers)
                if (count($trios) === count($kickers) && $count === count($trios) * 4) {
                     return ['type' => 'airplane_solo', 'value' => max($trio_ranks), 'length' => count($trios)];
                }
                // Airplane with large wings (pair kickers)
                $is_all_pairs = true;
                foreach($kickers as $kicker_count) { if($kicker_count !== 2) $is_all_pairs = false; }
                if (count($trios) === count($kickers) && $count === count($trios) * 5 && $is_all_pairs) {
                    return ['type' => 'airplane_pair', 'value' => max($trio_ranks), 'length' => count($trios)];
                }
            }
        }
    }

    // --- Four with Kickers ---
    // Four with two solos
    if ($count === 6 && count($rank_counts) === 3) {
        if (in_array(4, $rank_counts)) {
            $four_rank = array_search(4, $rank_counts);
            return ['type' => 'four_two_solo', 'value' => $four_rank, 'length' => 1];
        }
    }
    // Four with two pairs
    if ($count === 8 && count($rank_counts) === 3) {
         if (in_array(4, $rank_counts) && count(array_keys($rank_counts, 2)) === 2) {
            $four_rank = array_search(4, $rank_counts);
            return ['type' => 'four_two_pair', 'value' => $four_rank, 'length' => 1];
        }
    }

    return false; // Return false if no valid hand type is matched
}

/**
 * Validates a player's move against the previous play.
 * @param array $played_cards The cards played by the current player.
 * @param array|null $last_played_cards The cards played by the previous player.
 * @return array|false The details of the move if valid, false otherwise.
 */
function validate_move($played_cards, $last_played_cards) {
    $current_move = get_hand_details($played_cards);
    if (!$current_move) {
        return false; // The played cards do not form a valid hand.
    }

    // If there's no previous play, any valid hand is playable.
    if (!$last_played_cards || empty($last_played_cards)) {
        return $current_move;
    }

    $last_move = get_hand_details($last_played_cards);
    if (!$last_move) {
        // This should not happen if the logic is correct, but as a safeguard:
        return false;
    }

    // --- Comparison Logic ---
    // Rocket beats everything
    if ($current_move['type'] === 'rocket') {
        return $current_move;
    }

    // Bomb beats any non-bomb/non-rocket hand
    if ($current_move['type'] === 'bomb' && $last_move['type'] !== 'rocket' && $last_move['type'] !== 'bomb') {
        return $current_move;
    }

    // If both are bombs, the current one must be of higher value
    if ($current_move['type'] === 'bomb' && $last_move['type'] === 'bomb') {
        return $current_move['value'] > $last_move['value'] ? $current_move : false;
    }

    // For all other plays, types and lengths must match
    if ($current_move['type'] !== $last_move['type'] || $current_move['length'] !== $last_move['length']) {
        return false;
    }

    // And the value must be higher
    return $current_move['value'] > $last_move['value'] ? $current_move : false;
}

?>

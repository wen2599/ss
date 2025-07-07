php
<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors', 1);
session_start();

// utils.php - Placeholder for utility functions

// Place helper functions here

/**
 * Creates a standard deck of 54 poker cards for Dou Dizhu.
 * Includes three Jokers (big, small, and a placeholder for the third, if needed, or just 54 total).
 * Each card is represented as a string (e.g., "3C" for 3 of Clubs, "BH" for Big Joker).
 *
 * @return array The deck of cards.
 */
function create_deck() {
    $suits = ['C', 'D', 'H', 'S']; // Clubs, Diamonds, Hearts, Spades
    $values = ['3', '4', '5', '6', '7', '8', '9', 'T', 'J', 'Q', 'K', 'A', '2']; // 3 to 2, T for 10

    $deck = [];
    foreach ($values as $value) {
        foreach ($suits as $suit) {
            $deck[] = $value . $suit;
        }
    }
    $deck[] = 'SJ'; // Small Joker
    $deck[] = 'BJ'; // Big Joker
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
 * Example: Function to validate a player's move and determine its type and value.
 * This is a complex function that needs full Dou Dizhu rules implementation.
 *
 * @param array $played_cards The cards played by the current player.
 * @param array $last_played_cards The cards played by the previous player.
 * @return mixed Returns an array with move type and value if valid, false otherwise.
 */
function validate_move($played_cards, $last_played_cards) {
    // TODO: Implement complex Dou Dizhu move validation logic
    // Check if the played cards are a valid combination (single, pair, triplet, sequence, bomb, etc.)
    // Check if the played combination is larger than the last played combination (if any)

    // Placeholder: Always return true for now
    return ['type' => 'unknown', 'value' => 0]; // Placeholder return
}

/**
 * Example: Function to compare two valid moves.
 *
 * @param array $move1 Valid move structure (from validate_move).
 * @param array $move2 Valid move structure (from validate_move).
 * @return bool True if move1 is larger than move2, false otherwise.
 */
function compare_moves($move1, $move2) {
    // TODO: Implement complex Dou Dizhu move comparison logic
    // Compare based on move type hierarchy (bombs > others) and then value within type

    // Placeholder: Always return true (move1 is always larger) for now
    return true;
}

?>
?>
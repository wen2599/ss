php
<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors', 1);
session_start();

// game.php

class Game {
    private $roomId;
    private $players = []; // Associative array of Player objects, indexed by playerId
    private $deck = [];
    private $discardedCards = [];
    private $gameState = 'waiting'; // waiting, bidding, playing, finished
    private $currentTurn = null; // playerId of the current player
    private $landlordPlayerId = null;
    private $currentBid = 0; // Highest bid value
    private $lastPlayedCards = []; // Cards played by the previous player
    private $lastPlayerId = null; // Player who played the last cards

    public function __construct($roomId) {
        $this->roomId = $roomId;
        $this->initializeDeck();
    }

    public function addPlayer(Player $player) {
        if (count($this->players) < 3) { // Dou Dizhu is for 3 players
            $this->players[$player->getId()] = $player;
            if (count($this->players) === 3) {
                $this->gameState = 'bidding'; // Game can start bidding when 3 players join
                // TODO: Start the bidding phase
            }
            return true;
        }
        return false; // Room is full
    }

    private function initializeDeck() {
        // TODO: Generate a full deck of 54 cards (including Jokers)
        // Store them as card objects or strings (e.g., ['H_A', 'S_K', 'BJ', 'RJ'])
        $this->deck = ['placeholder_card_1', 'placeholder_card_2', 'placeholder_card_3']; // Placeholder
        shuffle($this->deck); // Shuffle the deck
    }

    public function dealCards() {
        if ($this->gameState === 'bidding' && count($this->deck) === 54) { // Ensure deck is full before dealing
            // TODO: Deal 17 cards to each of the 3 players
            // TODO: Keep 3 cards as landlord's cards
            // Update player hands
            // Transition state from bidding to playing once landlord is determined
        }
    }

    public function processBid($playerId, $bidValue) {
        if ($this->gameState === 'bidding' && isset($this->players[$playerId])) {
            // TODO: Implement bidding logic (0, 1, 2, 3)
            // Update $this->currentBid
            // Handle passing (bid 0)
            // Determine the landlord
            // Transition state to playing after landlord is determined
        }
    }

    public function playCards($playerId, array $cards) {
        if ($this->gameState === 'playing' && $this->currentTurn === $playerId && isset($this->players[$playerId])) {
            // TODO: Validate the played cards (valid combination, bigger than last played, in player's hand)
            // If valid:
            // Move cards from player's hand to $this->discardedCards
            // Update $this->lastPlayedCards and $this->lastPlayerId
            // Check win condition
            // Switch turn to the next player
        }
    }

    public function getState() {
        $playerStates = [];
        foreach ($this->players as $playerId => $player) {
            $playerStates[$playerId] = $player->getState();
        }
        return [
            'id' => $this->roomId,
            'state' => $this->gameState,
            'players' => $playerStates,
            'discarded_cards' => $this->discardedCards,
            'current_turn' => $this->currentTurn,
            'landlord_player_id' => $this->landlordPlayerId,
            'current_bid' => $this->currentBid,
            'last_played_cards' => $this->lastPlayedCards,
            'last_player_id' => $this->lastPlayerId,
            // TODO: Add landlord's cards if game started
        ];
    }

    // TODO: Add more game logic methods (e.g., passTurn, checkWinCondition)
}

class Player {
    private $id;
    private $name;
    private $hand = []; // Array of card strings/objects
    private $score = 0;
    private $isLandlord = false;

    public function __construct($id, $name) {
        $this->id = $id;
        $this->name = $name;
    }

    public function getId() {
        return $this->id;
    }

    public function addCards(array $cards) {
        $this->hand = array_merge($this->hand, $cards);
        // TODO: Sort the hand
    }

    public function getState() {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'hand_count' => count($this->hand), // Usually don't show other players' hands
            'score' => $this->score,
            'isLandlord' => $this->isLandlord,
            // If it's this player's state being sent, include the full hand
            // 'hand' => $this->hand,
        ];
    }

    // TODO: Add more player methods (e.g., removeCardsFromHand, setLandlord)
}

// TODO: Implement functions for dealing cards, shuffling, validating moves, etc.

// Example function (placeholder):
/*
function dealCards($deck, &$players) {
    // Logic to distribute cards to players
}
*/

?>

// TODO: Implement functions for dealing cards, shuffling, validating moves, etc.

// Example function (placeholder):
/*
function dealCards($deck, &$players) {
    // Logic to distribute cards to players
}
*/

?>
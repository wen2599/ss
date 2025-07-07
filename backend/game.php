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
        $this->roomId = $roomId; // Assuming Room ID is passed during game creation
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
        $suits = ['spades', 'hearts', 'diamonds', 'clubs'];
        $values = ['3', '4', '5', '6', '7', '8', '9', '10', 'jack', 'queen', 'king', 'ace', '2']; // Ordered by value
        $deck = [];
        foreach ($suits as $suit) {
            foreach ($values as $value) {
                $deck[] = $value . '_of_' . $suit . '.svg';
            }
        }
        $deck[] = 'black_joker.svg';
        $deck[] = 'red_joker.svg';

        shuffle($deck); // Shuffle the deck
        $this->deck = $deck;
    }

    public function dealCards() {
        if ($this->gameState === 'bidding' && count($this->deck) === 54) { // Ensure deck is full before dealing
            // Deal 17 cards to each of the 3 players
            $playerKeys = array_keys($this->players);
            for ($i = 0; $i < 17; $i++) {
                foreach ($playerKeys as $key) {
                    $card = array_shift($this->deck);
                    $this->players[$key]->addCards([$card]);
                }
            }
            // Keep 3 cards as landlord's cards (these will be added to the landlord's hand later)
            $this->landlordsCards = $this->deck; // The remaining 3 cards are the landlord's cards
            $this->deck = []; // Deck is now empty after dealing and setting landlord's cards
            // Game state will transition to playing after bidding is complete and landlord is set
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
    
    public function processBid($playerId, $bidValue) {
        // TODO: Implement bidding logic (0, 1, 2, 3)
        // Update $this->currentBid
        // Handle passing (bid 0)
        // Determine the landlord
        // Transition state to playing after landlord is determined
    }

    public function getState() {
        $playerStates = [];
        foreach ($this->players as $playerId => $player) {
            $playerStates[$playerId] = $player->getState();
        }
            'landlord_player_id' => $this->landlordPlayerId,
            'current_bid' => $this->currentBid,
            'last_played_cards' => $this->lastPlayedCards,
            'last_player_id' => $this->lastPlayerId,
            // TODO: Add landlord's cards if game started
        ];
    }
    return [
        'id' => $this->roomId,
        'state' => $this->gameState,
        'players' => $playerStates,
        'discarded_cards' => $this->discardedCards,
        'current_turn' => $this->currentTurn,
    ];

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
        // TODO: Sort the hand
    }

    public function getState() {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'hand_count' => count($this->hand), // Usually don't show other players' hands
            'score' => $this->score,
            'isLandlord' => $this->isLandlord,
            'hand' => $this->hand, // For testing, include the full hand for now
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
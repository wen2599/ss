<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// --- Card Utilities ---

/**
 * Creates a standard 52-card deck + 2 jokers.
 * @return array
 */
function create_deck(): array {
    $suits = ['S', 'H', 'D', 'C']; // Spades, Hearts, Diamonds, Clubs
    $ranks = ['3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A', '2'];
    $deck = [];
    foreach ($suits as $suit) {
        foreach ($ranks as $rank) {
            $deck[] = $suit . $rank;
        }
    }
    $deck[] = 'BJ'; // Black Joker
    $deck[] = 'RJ'; // Red Joker
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
 * Defines the value of each card for sorting purposes.
 * @param string $card
 * @return int
 */
function get_card_value(string $card): int {
    $rank = substr($card, 1);
    if ($card === 'RJ') return 17;
    if ($card === 'BJ') return 16;
    if ($rank === '2') return 15;
    if ($rank === 'A') return 14;
    if ($rank === 'K') return 13;
    if ($rank === 'Q') return 12;
    if ($rank === 'J') return 11;
    if ($rank === '10') return 10;
    return (int)$rank;
}

/**
 * Sorts an array of cards based on their value.
 * @param array $cards
 * @return void
 */
function sort_cards(array &$cards): void {
    usort($cards, function ($a, $b) {
        return get_card_value($b) <=> get_card_value($a); // Sort descending
    });
}


// --- Card Analyzer ---

class CardAnalyzer {
    const TYPE_INVALID = 0;
    const TYPE_SINGLE = 1;
    const TYPE_PAIR = 2;
    const TYPE_TRIO = 3;
    const TYPE_TRIO_SOLO = 4;
    const TYPE_TRIO_PAIR = 5;
    const TYPE_STRAIGHT = 6;
    const TYPE_STRAIGHT_PAIR = 7;
    const TYPE_AIRPLANE = 8;
    const TYPE_AIRPLANE_SOLO = 9;
    const TYPE_AIRPLANE_PAIR = 10;
    const TYPE_QUAD_SOLO = 11;
    const TYPE_QUAD_PAIR = 12;
    const TYPE_BOMB = 13;
    const TYPE_ROCKET = 14;

    private static function get_rank(string $card): string {
        if ($card === 'BJ' || $card === 'RJ') return $card;
        return substr($card, 1);
    }

    private static function getRankCounts(array $cards): array {
        $counts = [];
        foreach ($cards as $card) {
            $rank = self::get_rank($card);
            $counts[$rank] = ($counts[$rank] ?? 0) + 1;
        }
        return $counts;
    }

    public static function parse(array $cards): ?array {
        $count = count($cards);
        if ($count === 0) return null;

        usort($cards, fn($a, $b) => get_card_value($a) <=> get_card_value($b));
        $counts = self::getRankCounts($cards);
        $rankValues = array_map('get_card_value', $cards);

        // Rocket
        if ($count === 2 && $rankValues[0] === 16 && $rankValues[1] === 17) {
            return ['type' => self::TYPE_ROCKET, 'value' => 999, 'length' => 2];
        }

        // Bomb
        if ($count === 4 && count($counts) === 1) {
            return ['type' => self::TYPE_BOMB, 'value' => $rankValues[0], 'length' => 4];
        }

        // Single, Pair, Trio (pure)
        if ($count <= 3 && count($counts) === 1) {
            if ($count === 1) return ['type' => self::TYPE_SINGLE, 'value' => $rankValues[0], 'length' => 1];
            if ($count === 2) return ['type' => self::TYPE_PAIR, 'value' => $rankValues[0], 'length' => 2];
            if ($count === 3) return ['type' => self::TYPE_TRIO, 'value' => $rankValues[0], 'length' => 3];
        }

        // Trio with attachments
        if ($count === 4 && in_array(3, $counts)) { // 3 + 1
            $trioRank = array_search(3, $counts);
            return ['type' => self::TYPE_TRIO_SOLO, 'value' => get_card_value('S' . $trioRank), 'length' => 3];
        }
        if ($count === 5 && in_array(3, $counts) && in_array(2, $counts)) { // 3 + 2
            $trioRank = array_search(3, $counts);
            return ['type' => self::TYPE_TRIO_PAIR, 'value' => get_card_value('S' . $trioRank), 'length' => 3];
        }

        // Straight
        if ($count >= 5 && count($counts) === $count) { // All cards unique
            if ($rankValues[$count - 1] > 14) return null; // No 2s or Jokers
            $isStraight = true;
            for ($i = 0; $i < $count - 1; $i++) {
                if ($rankValues[$i+1] !== $rankValues[$i] + 1) {
                    $isStraight = false;
                    break;
                }
            }
            if ($isStraight) {
                return ['type' => self::TYPE_STRAIGHT, 'value' => $rankValues[0], 'length' => $count];
            }
        }

        // Straight Pair
        if ($count >= 6 && ($count % 2 === 0) && count($counts) === $count / 2) {
            $allPairs = true;
            foreach ($counts as $num) { if ($num !== 2) $allPairs = false; }
            if ($allPairs) {
                $uniqueRankValues = array_values(array_unique($rankValues));
                if ($uniqueRankValues[count($uniqueRankValues) - 1] > 14) return null;
                $isStraight = true;
                for ($i = 0; $i < count($uniqueRankValues) - 1; $i++) {
                    if ($uniqueRankValues[$i+1] !== $uniqueRankValues[$i] + 1) $isStraight = false;
                }
                if ($isStraight) {
                    return ['type' => self::TYPE_STRAIGHT_PAIR, 'value' => $uniqueRankValues[0], 'length' => count($uniqueRankValues)];
                }
            }
        }

        // Airplanes and Quads
        $trios = array_keys($counts, 3);
        $quads = array_keys($counts, 4);

        if (count($trios) >= 2) {
            $trioValues = array_map(fn($r) => get_card_value('S'.$r), $trios);
            sort($trioValues);
            if ($trioValues[count($trioValues)-1] > 14) return null;
            $isStraight = true;
            for ($i=0; $i < count($trioValues) - 1; $i++) {
                if ($trioValues[$i+1] !== $trioValues[$i] + 1) $isStraight = false;
            }
            if ($isStraight) {
                $singles = array_keys($counts, 1);
                $pairs = array_keys($counts, 2);
                if (count($trios) * 3 === $count) {
                    return ['type' => self::TYPE_AIRPLANE, 'value' => $trioValues[0], 'length' => count($trios)];
                }
                if (count($singles) === count($trios) && $count === count($trios) * 4) {
                     return ['type' => self::TYPE_AIRPLANE_SOLO, 'value' => $trioValues[0], 'length' => count($trios)];
                }
                if (count($pairs) === count($trios) && $count === count($trios) * 5) {
                     return ['type' => self::TYPE_AIRPLANE_PAIR, 'value' => $trioValues[0], 'length' => count($trios)];
                }
            }
        }

        if (count($quads) === 1) {
            $quadValue = get_card_value('S' . $quads[0]);
            $singles = array_keys($counts, 1);
            $pairs = array_keys($counts, 2);
            if (count($singles) === 2 && $count === 6) {
                return ['type' => self::TYPE_QUAD_SOLO, 'value' => $quadValue, 'length' => 1];
            }
            if (count($pairs) === 2 && $count === 8) {
                return ['type' => self::TYPE_QUAD_PAIR, 'value' => $quadValue, 'length' => 1];
            }
        }

        return null;
    }
}


// --- Game Classes ---

class Player {
    private string $id;
    private string $name;
    private array $hand = [];
    private bool $isLandlord = false;

    public function __construct(string $id, string $name) {
        $this->id = $id;
        $this->name = $name;
    }

    public function getId(): string {
        return $this->id;
    }

    public function addCards(array $cards): void {
        $this->hand = array_merge($this->hand, $cards);
        sort_cards($this->hand);
    }

    public function removeCards(array $cardsToRemove): bool {
        foreach ($cardsToRemove as $card) {
            $index = array_search($card, $this->hand);
            if ($index !== false) {
                unset($this->hand[$index]);
            } else {
                // Should not happen if validation is correct
                return false; // Card not found
            }
        }
        $this->hand = array_values($this->hand); // Re-index array
        return true;
    }

    public function getHand(): array {
        return $this->hand;
    }

    public function setLandlord(bool $isLandlord): void {
        $this->isLandlord = $isLandlord;
    }

    public function isLandlord(): bool {
        return $this->isLandlord;
    }

    public function getState(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'hand_count' => count($this->hand),
            'isLandlord' => $this->isLandlord,
            // For security, the actual hand should only be sent to the player themselves
            // 'hand' => $this->hand,
        ];
    }
}


class Game {
    private string $roomId;
    private array $players = [];
    private array $playerIds = []; // Ordered list of player IDs for turn tracking
    private array $deck = [];
    private array $landlordsCards = [];
    private string $gameState = 'waiting';
    private ?string $currentTurnPlayerId = null;
    private ?string $landlordPlayerId = null;

    // Bidding state
    private array $bids = [];
    private int $highestBid = 0;
    private ?string $highestBidderId = null;
    private int $passesInARow = 0;

    // Playing state
    private array $lastPlayedCards = [];
    private ?string $lastPlayerId = null;

    public function __construct(string $roomId) {
        $this->roomId = $roomId;
        $this->deck = shuffle_deck(create_deck());
    }

    public function addPlayer(Player $player): bool {
        if (count($this->players) < 3) {
            $this->players[$player->getId()] = $player;
            $this->playerIds[] = $player->getId();
            if (count($this->players) === 3) {
                $this->gameState = 'bidding';
                $this->dealCards();
            }
            return true;
        }
        return false;
    }

    public function dealCards(): bool {
        // This is called when the 3rd player joins
        if ($this->gameState !== 'bidding' || count($this->players) !== 3) {
            return false;
        }

        // Deal 17 cards to each player
        for ($i = 0; $i < 17; $i++) {
            foreach ($this->playerIds as $playerId) {
                $card = array_shift($this->deck);
                if ($card) {
                    $this->players[$playerId]->addCards([$card]);
                }
            }
        }

        // The remaining 3 cards are the landlord's cards
        $this->landlordsCards = $this->deck;
        sort_cards($this->landlordsCards);
        $this->deck = [];

        // Randomly select a player to start the bidding
        $this->currentTurnPlayerId = $this->playerIds[array_rand($this->playerIds)];

        return true;
    }

    public function processBid(string $playerId, int $bidValue): bool {
        if ($this->gameState !== 'bidding' || $playerId !== $this->currentTurnPlayerId) {
            return false; // Not in bidding state or not player's turn
        }

        if (!in_array($bidValue, [0, 1, 2, 3]) || ($bidValue > 0 && $bidValue <= $this->highestBid)) {
            return false; // Invalid bid value (0 for pass)
        }

        $this->bids[$playerId] = $bidValue;

        if ($bidValue > 0) {
            $this->highestBid = $bidValue;
            $this->highestBidderId = $playerId;
            $this->passesSinceLastBid = 0;
        } else {
            $this->passesSinceLastBid++;
        }

        $allPlayersHaveHadATurn = count($this->bids) >= 3;

        if ($bidValue === 3 || ($this->passesSinceLastBid >= 2 && $this->highestBidderId !== null)) {
            $this->_setLandlord($this->highestBidderId);
        } elseif ($allPlayersHaveHadATurn && $this->highestBidderId === null) {
            $this->gameState = 'misdeal'; // All passed
        } else {
            $this->currentTurnPlayerId = $this->_nextPlayer();
        }

        return true;
    }

    private function _setLandlord(string $landlordId): void {
        $this->landlordPlayerId = $landlordId;
        $this->players[$landlordId]->setLandlord(true);
        $this->players[$landlordId]->addCards($this->landlordsCards);
        $this->landlordsCards = [];

        $this->gameState = 'playing';
        $this->currentTurnPlayerId = $landlordId;
    }

    private function _nextPlayer(): string {
        $currentIndex = array_search($this->currentTurnPlayerId, $this->playerIds);
        $nextIndex = ($currentIndex + 1) % 3;
        return $this->playerIds[$nextIndex];
    }

    public function passTurn(string $playerId): bool {
        if ($this->gameState !== 'playing' || $playerId !== $this->currentTurnPlayerId) {
            return false;
        }
        // Player cannot pass if they are starting a new round (i.e., they were the last to play).
        if ($this->lastPlayerId === $playerId || $this->lastPlayerId === null) {
            return false;
        }

        $this->passesInARow++;
        $this->currentTurnPlayerId = $this->_nextPlayer();

        // If the turn gets back to the person who last played, they can play anything.
        if ($this->currentTurnPlayerId === $this->lastPlayerId) {
            $this->passesInARow = 0; // Reset for the new round.
        }

        return true;
    }

    public function playCards(string $playerId, array $cards): bool {
        if ($this->gameState !== 'playing' || $playerId !== $this->currentTurnPlayerId) {
            return false; // Not in playing state or not player's turn
        }

        // Verify player has the cards
        if (count(array_intersect($this->players[$playerId]->getHand(), $cards)) !== count($cards)) {
            return false;
        }

        $play = CardAnalyzer::parse($cards);
        if ($play === null) {
            return false; // Invalid card combination
        }

        $isNewRound = $this->lastPlayerId === null || $this->currentTurnPlayerId === $this->lastPlayerId;

        if (!$isNewRound) {
            $lastPlay = CardAnalyzer::parse($this->lastPlayedCards);

            $canBeat = false;
            if ($play['type'] === CardAnalyzer::TYPE_ROCKET) {
                $canBeat = true;
            } else if ($play['type'] === CardAnalyzer::TYPE_BOMB && $lastPlay['type'] < CardAnalyzer::TYPE_BOMB) {
                $canBeat = true;
            } else if ($play['type'] === $lastPlay['type'] && $play['length'] === $lastPlay['length'] && $play['value'] > $lastPlay['value']) {
                $canBeat = true;
            }

            if (!$canBeat) {
                return false;
            }
        }

        $this->players[$playerId]->removeCards($cards);
        $this->lastPlayedCards = $cards;
        $this->lastPlayerId = $playerId;
        $this->passesInARow = 0;

        if (count($this->players[$playerId]->getHand()) === 0) {
            $this->gameState = 'finished';
        } else {
            $this->currentTurnPlayerId = $this->_nextPlayer();
        }

        return true;
    }

    // TODO: Implement checkWinCondition() in Step 5

    public function getState(): array {
        $playerStates = [];
        foreach ($this->players as $playerId => $player) {
            $playerStates[$playerId] = $player->getState();
        }
        return [
            'id' => $this->roomId,
            'state' => $this->gameState,
            'players' => $playerStates,
            'landlords_cards' => $this->landlordsCards, // For display after bidding
            'current_turn' => $this->currentTurnPlayerId,
            'landlord_player_id' => $this->landlordPlayerId,
            'last_played_cards' => $this->lastPlayedCards,
            'bids' => $this->bids, // For debugging/display
        ];
    }

    // --- DB Integration Methods ---

    public function getGameId(): string { // Assuming roomID is the game ID for now
        return $this->roomId;
    }

    public function getPlayerIds(): array {
        return $this->playerIds;
    }

    public function getPlayer(string $playerId): ?Player {
        return $this->players[$playerId] ?? null;
    }

    public function getFullStateForDb(): array {
        return [
            'gameState' => $this->gameState,
            'currentTurnPlayerId' => $this->currentTurnPlayerId,
            'landlordPlayerId' => $this->landlordPlayerId,
            'landlordsCards' => $this->landlordsCards,
            'bids' => $this->bids,
            'highestBid' => $this->highestBid,
            'highestBidderId' => $this->highestBidderId,
            'lastPlayedCards' => $this->lastPlayedCards,
            'lastPlayerId' => $this->lastPlayerId,
        ];
    }
}
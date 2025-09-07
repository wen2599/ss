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

// --- Game Classes ---

class Player {
    private string $id;
    private string $name;
    private array $hand = []; // The 13 cards

    public function __construct(string $id, string $name) {
        $this->id = $id;
        $this->name = $name;
    }

    public function getId(): string {
        return $this->id;
    }

    public function setHand(array $cards): void {
        $this->hand = $cards;
        sort_cards_by_rank($this->hand);
    }

    public function getHand(): array {
        return $this->hand;
    }

    public function getState(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'hand_count' => count($this->hand),
        ];
    }
}

class Game {
    private string $roomId;
    private array $players = []; // [playerId => Player]
    private array $playerIds = [];
    private string $gameState = 'waiting'; // waiting, dealing, setting_hands, showdown, finished
    private array $playerHands = []; // Stores submitted hands

    public function __construct(string $roomId) {
        $this->roomId = $roomId;
    }

    public function addPlayer(Player $player): bool {
        if (count($this->players) >= 4) {
            return false; // Room is full
        }
        $this->players[$player->getId()] = $player;
        $this->playerIds[] = $player->getId();
        return true;
    }

    public function startGame(): bool {
        if (count($this->players) < 2) {
            return false; // Not enough players
        }
        $this->gameState = 'dealing';
        $deck = shuffle_deck(create_deck());

        // Deal 13 cards to each player
        for ($i = 0; $i < 13; $i++) {
            foreach ($this->playerIds as $playerId) {
                $card = array_shift($deck);
                if ($card) {
                    $this->players[$playerId]->setHand(array_merge($this->players[$playerId]->getHand(), [$card]));
                }
            }
        }
        $this->gameState = 'setting_hands';
        return true;
    }

    public function submitHand(string $playerId, array $front, array $middle, array $back): bool {
        if ($this->gameState !== 'setting_hands' || !isset($this->players[$playerId])) {
            return false;
        }

        // Basic validation
        if (count($front) !== 3 || count($middle) !== 5 || count($back) !== 5) {
            return false; // Invalid hand sizes
        }
        $combined = array_merge($front, $middle, $back);
        sort($combined);
        $playerHand = $this->players[$playerId]->getHand();
        sort($playerHand);
        if ($combined !== $playerHand) {
            return false; // Submitted cards don't match dealt cards
        }

        // Analyze hands
        $front_details = ThirteenCardAnalyzer::analyze_hand($front);
        $middle_details = ThirteenCardAnalyzer::analyze_hand($middle);
        $back_details = ThirteenCardAnalyzer::analyze_hand($back);

        // Validate hand strengths (Back >= Middle >= Front)
        $is_valid = ThirteenCardAnalyzer::compare_hands($back_details, $middle_details) >= 0 &&
                    ThirteenCardAnalyzer::compare_hands($middle_details, $front_details) >= 0;

        $this->playerHands[$playerId] = [
            'is_submitted' => true,
            'is_valid' => $is_valid,
            'front' => ['cards' => $front, 'details' => $front_details],
            'middle' => ['cards' => $middle, 'details' => $middle_details],
            'back' => ['cards' => $back, 'details' => $back_details],
        ];

        // Check if all players have submitted
        if (count($this->playerHands) === count($this->players)) {
            $this->enterShowdown();
        }

        return true;
    }

    private function enterShowdown() {
        $this->gameState = 'showdown';
        // TODO: Implement scoring logic here or in a separate method.
        // This involves comparing each player's hands against every other player.
    }

    public function getState(): array {
        $playerStates = [];
        foreach ($this->players as $playerId => $player) {
            $playerStates[$playerId] = $player->getState();
            // For the current player, we should also send their actual hand
            // This requires knowing which player is requesting the state.
        }

        return [
            'id' => $this->roomId,
            'state' => $this->gameState,
            'players' => $playerStates,
            'player_hands' => $this->playerHands, // Contains submitted hands for showdown
        ];
    }
}

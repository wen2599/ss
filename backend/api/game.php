<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// --- Card Utilities for Thirteen ---

function create_deck(): array {
    $suits = ['S', 'H', 'D', 'C']; // Spades, Hearts, Diamonds, Clubs
    $ranks = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];
    $deck = [];
    foreach ($suits as $suit) {
        foreach ($ranks as $rank) {
            $deck[] = $suit . $rank;
        }
    }
    return $deck;
}

function shuffle_deck(array $deck): array {
    shuffle($deck);
    return $deck;
}

function get_card_value(string $card): int {
    $rank_str = substr($card, 1);
    $rank_map = ['2'=>2, '3'=>3, '4'=>4, '5'=>5, '6'=>6, '7'=>7, '8'=>8, '9'=>9, '10'=>10, 'J'=>11, 'Q'=>12, 'K'=>13, 'A'=>14];
    return $rank_map[$rank_str] ?? 0;
}

function get_card_suit(string $card): string {
    return substr($card, 0, 1);
}

function sort_cards(array &$cards): void {
    usort($cards, function ($a, $b) {
        return get_card_value($a) <=> get_card_value($b); // Sort ascending by value
    });
}

// --- Thirteen Card Analyzer ---

class Thirteen_CardAnalyzer {
    // Hand types, from weakest to strongest
    const TYPE_HIGH_CARD = 1;
    const TYPE_PAIR = 2;
    const TYPE_TWO_PAIR = 3;
    const TYPE_THREE_OF_A_KIND = 4;
    const TYPE_STRAIGHT = 5;
    const TYPE_FLUSH = 6;
    const TYPE_FULL_HOUSE = 7;
    const TYPE_FOUR_OF_A_KIND = 8;
    const TYPE_STRAIGHT_FLUSH = 9;

    // Special hands (not implemented in this simplified version)
    const TYPE_DRAGON = 20;

    public static function analyze_hand(array $hand): ?array {
        if (count($hand) !== 3 && count($hand) !== 5) {
            return null; // Invalid hand size
        }
        sort_cards($hand);

        $values = array_map('get_card_value', $hand);
        $suits = array_map('get_card_suit', $hand);
        $value_counts = array_count_values($values);
        $suit_counts = array_count_values($suits);

        $is_flush = (count($suit_counts) === 1);
        $is_straight = self::is_straight($values);

        if (count($hand) === 5) {
            if ($is_straight && $is_flush) return ['type' => self::TYPE_STRAIGHT_FLUSH, 'value' => max($values), 'cards' => $hand];
            if (in_array(4, $value_counts)) {
                $four_value = array_search(4, $value_counts);
                return ['type' => self::TYPE_FOUR_OF_A_KIND, 'value' => $four_value, 'cards' => $hand];
            }
            if (in_array(3, $value_counts) && in_array(2, $value_counts)) return ['type' => self::TYPE_FULL_HOUSE, 'value' => array_search(3, $value_counts), 'cards' => $hand];
            if ($is_flush) return ['type' => self::TYPE_FLUSH, 'value' => max($values), 'cards' => $hand];
            if ($is_straight) return ['type' => self::TYPE_STRAIGHT, 'value' => max($values), 'cards' => $hand];
        }

        if (in_array(3, $value_counts)) return ['type' => self::TYPE_THREE_OF_A_KIND, 'value' => array_search(3, $value_counts), 'cards' => $hand];

        $pairs = 0;
        $pair_values = [];
        foreach ($value_counts as $value => $count) {
            if ($count === 2) {
                $pairs++;
                $pair_values[] = $value;
            }
        }
        if ($pairs === 2) return ['type' => self::TYPE_TWO_PAIR, 'value' => max($pair_values), 'kickers' => $values, 'cards' => $hand];
        if ($pairs === 1) return ['type' => self::TYPE_PAIR, 'value' => max($pair_values), 'kickers' => $values, 'cards' => $hand];

        return ['type' => self::TYPE_HIGH_CARD, 'value' => max($values), 'kickers' => $values, 'cards' => $hand];
    }

    private static function is_straight(array $values): bool {
        if (count(array_unique($values)) !== count($values)) return false;
        for ($i = 0; $i < count($values) - 1; $i++) {
            if ($values[$i+1] !== $values[$i] + 1) {
                // Handle A-2-3-4-5 straight
                if ($i === count($values) - 2 && $values[0] === 2 && max($values) === 14) { // A,2,3,4,5
                    return true;
                }
                return false;
            }
        }
        return true;
    }

    public static function compare_hands(array $handA_analysis, array $handB_analysis): int {
        if ($handA_analysis['type'] !== $handB_analysis['type']) {
            return $handA_analysis['type'] <=> $handB_analysis['type'];
        }
        // If types are the same, compare by value, then kickers
        if ($handA_analysis['value'] !== $handB_analysis['value']) {
            return $handA_analysis['value'] <=> $handB_analysis['value'];
        }
        // Kicker comparison (simplified)
        if (isset($handA_analysis['kickers']) && isset($handB_analysis['kickers'])) {
             for ($i = count($handA_analysis['kickers']) - 1; $i >= 0; $i--) {
                if ($handA_analysis['kickers'][$i] !== $handB_analysis['kickers'][$i]) {
                    return $handA_analysis['kickers'][$i] <=> $handB_analysis['kickers'][$i];
                }
            }
        }
        return 0; // Tie
    }
}

// --- Game Classes for Thirteen ---

class Player {
    private string $id;
    private string $name;
    private array $hand = [];
    public array $front_hand = [];
    public array $middle_hand = [];
    public array $back_hand = [];
    public bool $hand_is_set = false;

    public function __construct(string $id, string $name) {
        $this->id = $id;
        $this->name = $name;
    }

    public function getId(): string { return $this->id; }
    public function addCards(array $cards): void {
        $this->hand = array_merge($this->hand, $cards);
        sort_cards($this->hand);
    }
    public function getHand(): array { return $this->hand; }

    public function setHandSegments(array $front, array $middle, array $back): bool {
        $all_cards = array_merge($front, $middle, $back);
        if (count($all_cards) !== 13 || count(array_diff($all_cards, $this->hand)) > 0) {
            return false; // Invalid cards submitted
        }
        $this->front_hand = $front;
        $this->middle_hand = $middle;
        $this->back_hand = $back;
        $this->hand_is_set = true;
        return true;
    }

    public function getState(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'hand_count' => count($this->hand),
            'hand_is_set' => $this->hand_is_set,
            'front_hand' => $this->front_hand,
            'middle_hand' => $this->middle_hand,
            'back_hand' => $this->back_hand,
        ];
    }
}

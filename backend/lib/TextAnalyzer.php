<?php

class TextAnalyzer {
    /**
     * Analyzes a given text and returns a structured array with the results.
     *
     * @param string $text The text to analyze.
     * @return array An associative array containing 'charCount', 'wordCount', and 'keywords'.
     */
    public function analyze($text) {
        // a. Calculate character count (multi-byte safe)
        $char_count = mb_strlen($text, 'UTF-8');

        // b. Calculate word count (handles punctuation and unicode)
        $cleaned_text_for_words = preg_replace('/[\p{P}\p{S}\s]+/u', ' ', $text);
        $word_count = str_word_count($cleaned_text_for_words);

        // c. Extract keywords (long English words and Chinese phrases)
        preg_match_all('/([a-zA-Z]{5,})|([\p{Han}]+)/u', $text, $matches);
        $keywords = array_unique(array_filter($matches[0]));

        return [
            'charCount' => $char_count,
            'wordCount' => $word_count,
            'keywords' => array_values($keywords)
        ];
    }
}

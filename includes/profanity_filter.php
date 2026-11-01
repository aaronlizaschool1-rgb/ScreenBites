<?php
/**
 * ScreenBites - Pure Procedural Regex Profanity Filter
 * Fulfills MP4: Review CRUD with Regex Masking (100% Pure Procedural PHP)
 * Sanitizes offensive terms into asterisks using regex word boundary replacement
 */

if (!function_exists('mask_profanity')) {
    /**
     * Sanitize bad/foul words in text using regular expression masking
     *
     * @param string $text Raw user review input
     * @return string Sanitized text with profane words masked by asterisks
     */
    function mask_profanity(string $text): string {
        if (trim($text) === '') {
            return '';
        }

        $badWords = [
            // English common profanities
            'damn', 'hell', 'shit', 'bullshit', 'fuck', 'fucking', 'fucker',
            'bitch', 'ass', 'asshole', 'bastard', 'crap', 'dick', 'pussy',
            'scam', 'scammer', 'fake', 'trash', 'garbage', 'idiot', 'stupid',
            // Filipino / Tagalog profanities
            'tangina', 'tang-ina', 'putangina', 'puta', 'gago', 'gaga',
            'tarantado', 'tarantada', 'ulol', 'leche', 'bwisit', 'buwisit',
            'kupal', 'pakshet', 'bobo', 'tanga', 'inutil', 'punyeta', 'hayop'
        ];

        foreach ($badWords as $word) {
            $escaped = preg_quote($word, '/');
            // Match whole word boundary, case-insensitive
            $pattern = '/\b' . $escaped . '\b/i';

            $text = preg_replace_callback($pattern, function ($matches) {
                $matched = $matches[0];
                $len = mb_strlen($matched);
                if ($len <= 2) {
                    return str_repeat('*', $len);
                }
                // Keep the initial character visible and mask subsequent characters
                return mb_substr($matched, 0, 1) . str_repeat('*', $len - 1);
            }, $text);
        }

        return $text;
    }
}

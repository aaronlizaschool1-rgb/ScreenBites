<?php
if (!function_exists('mask_profanity')) {
    function mask_profanity(string $text): string {
        if (trim($text) === '') {
            return '';
        }

        $badWords = [

            'damn', 'hell', 'shit', 'bullshit', 'fuck', 'fucking', 'fucker',
            'bitch', 'ass', 'asshole', 'bastard', 'crap', 'dick', 'pussy',
            'scam', 'scammer', 'fake', 'trash', 'garbage', 'idiot', 'stupid',

            'tangina', 'tang-ina', 'putangina', 'puta', 'gago', 'gaga',
            'tarantado', 'tarantada', 'ulol', 'leche', 'bwisit', 'buwisit',
            'kupal', 'pakshet', 'bobo', 'tanga', 'inutil', 'punyeta', 'hayop'
        ];

        foreach ($badWords as $word) {
            $escaped = preg_quote($word, '/');

            $pattern = '/\b' . $escaped . '\b/i';

            $text = preg_replace_callback($pattern, function ($matches) {
                $matched = $matches[0];
                $len = mb_strlen($matched);
                if ($len <= 2) {
                    return str_repeat('*', $len);
                }

                return mb_substr($matched, 0, 1) . str_repeat('*', $len - 1);
            }, $text);
        }

        return $text;
    }
}

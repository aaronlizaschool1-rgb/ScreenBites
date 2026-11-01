<?php
/**
 * ScreenBites - Pure Procedural Backend Validation Library
 * Modeled after PedalWorks-Dynamics procedural architecture
 * Fulfills Academic Validation Standards across MP1 - MP5
 */

/**
 * Validate that a field is not empty
 *
 * @param mixed $value
 * @param string $fieldName
 * @return string|null Error message or null if valid
 */
function validate_required($value, string $fieldName): ?string {
    if ($value === null || trim((string)$value) === '') {
        return "{$fieldName} is required and cannot be empty.";
    }
    return null;
}

/**
 * Validate string length within a range
 *
 * @param string $value
 * @param int $min
 * @param int $max
 * @param string $fieldName
 * @return string|null Error message or null if valid
 */
function validate_string_length(string $value, int $min, int $max, string $fieldName): ?string {
    $len = mb_strlen(trim($value));
    if ($len < $min || $len > $max) {
        return "{$fieldName} must be between {$min} and {$max} characters (currently {$len}).";
    }
    return null;
}

/**
 * Validate numeric value within a range
 *
 * @param float|int $value
 * @param float|int $min
 * @param float|int $max
 * @param string $fieldName
 * @return string|null Error message or null if valid
 */
function validate_numeric_range($value, $min, $max, string $fieldName): ?string {
    if (!is_numeric($value)) {
        return "{$fieldName} must be a valid numeric value.";
    }
    $num = (float)$value;
    if ($num < $min || $num > $max) {
        return "{$fieldName} must be between {$min} and {$max}.";
    }
    return null;
}

/**
 * Validate that a value belongs to an allowed list
 *
 * @param mixed $value
 * @param array $allowed
 * @param string $fieldName
 * @return string|null Error message or null if valid
 */
function validate_in_array($value, array $allowed, string $fieldName): ?string {
    if (!in_array($value, $allowed, true)) {
        return "{$fieldName} must be one of: " . implode(', ', $allowed) . ".";
    }
    return null;
}

/**
 * Validate email address format
 *
 * @param string $email
 * @return bool
 */
function validate_email(string $email): bool {
    $trimmed = trim($email);
    return filter_var($trimmed, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate that a number is strictly positive
 *
 * @param mixed $value
 * @return bool
 */
function validate_positive_number($value): bool {
    return is_numeric($value) && (float)$value > 0;
}

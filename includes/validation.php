<?php
function validate_required($value, string $fieldName): ?string {
    if ($value === null || trim((string)$value) === '') {
        return "{$fieldName} is required and cannot be empty.";
    }
    return null;
}

function validate_string_length(string $value, int $min, int $max, string $fieldName): ?string {
    $len = mb_strlen(trim($value));
    if ($len < $min || $len > $max) {
        return "{$fieldName} must be between {$min} and {$max} characters (currently {$len}).";
    }
    return null;
}

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

function validate_in_array($value, array $allowed, string $fieldName): ?string {
    if (!in_array($value, $allowed, true)) {
        return "{$fieldName} must be one of: " . implode(', ', $allowed) . ".";
    }
    return null;
}

function validate_email(string $email): bool {
    $trimmed = trim($email);
    return filter_var($trimmed, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_positive_number($value): bool {
    return is_numeric($value) && (float)$value > 0;
}

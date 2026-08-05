<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use RuntimeException;
use Sabri\CF02\Security\SensitiveContentDetector;

final class ApiInput
{
    public static function boolean(mixed $value, string $label, ?bool $default = null): bool
    {
        if ($value === null || $value === '') {
            if ($default !== null) { return $default; }
            throw new RuntimeException(sprintf('%s must be an explicit boolean.', $label));
        }
        if (is_bool($value)) { return $value; }
        if (is_int($value) && ($value === 0 || $value === 1)) { return $value === 1; }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ($normalized === 'true' || $normalized === '1') { return true; }
            if ($normalized === 'false' || $normalized === '0') { return false; }
        }
        throw new RuntimeException(sprintf('%s must be an explicit boolean.', $label));
    }

    public static function locale(mixed $value, string $default = 'ur-PK'): string
    {
        $locale = trim((string) ($value === null || $value === '' ? $default : $value));
        if (strlen($locale) > 35 || preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $locale) !== 1) {
            throw new RuntimeException('Locale is invalid.');
        }
        return $locale;
    }

    public static function safeSingleLine(mixed $value, string $label, int $maxLength = 191, bool $required = true): string
    {
        if ($maxLength < 1 || $maxLength > 2000) { throw new RuntimeException('Text limit is invalid.'); }
        $raw = (string) $value;
        if (strpbrk($raw, "\r\n") !== false) {
            throw new RuntimeException(sprintf('%s contains an unsafe line break.', $label));
        }
        $text = sanitize_text_field($raw);
        $length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        if (($required && trim($text) === '') || $length > $maxLength) {
            throw new RuntimeException(sprintf('%s is missing or exceeds its safe limit.', $label));
        }
        if ($text !== '' && SensitiveContentDetector::containsProhibitedSecret($text)) {
            throw new RuntimeException(sprintf('%s contains prohibited secret data.', $label));
        }
        return $text;
    }

    public static function safeReference(mixed $value, string $label, int $maxLength = 191): string
    {
        $reference = trim((string) $value);
        if ($maxLength < 3 || $maxLength > 512 || strlen($reference) > $maxLength
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:@\/-]*$/', $reference) !== 1) {
            throw new RuntimeException(sprintf('%s is invalid.', $label));
        }
        if (SensitiveContentDetector::containsProhibitedSecret($reference)) {
            throw new RuntimeException(sprintf('%s contains prohibited secret data.', $label));
        }
        return $reference;
    }

    /** @return array<string,string> */
    public static function safeHeaderMap(mixed $value, int $maxHeaders = 24): array
    {
        if ($value === null || $value === []) { return []; }
        if (!is_array($value) || array_is_list($value) || count($value) > $maxHeaders) {
            throw new RuntimeException('Attachment provider returned malformed upload headers.');
        }
        $headers = [];
        $forbidden = ['host', 'cookie', 'content-length', 'connection', 'transfer-encoding', 'proxy-authorization'];
        foreach ($value as $name => $headerValue) {
            $lowerName = is_string($name) ? strtolower($name) : '';
            if (!is_string($name) || preg_match('/^[A-Za-z0-9-]{1,64}$/', $name) !== 1
                || in_array($lowerName, $forbidden, true) || str_starts_with($lowerName, 'sec-')
                || str_starts_with($lowerName, 'proxy-')
                || (!is_string($headerValue) && !is_int($headerValue))
                || strlen((string) $headerValue) > 2048 || strpbrk((string) $headerValue, "\r\n") !== false) {
                throw new RuntimeException('Attachment provider returned malformed upload headers.');
            }
            $headers[$name] = (string) $headerValue;
        }
        return $headers;
    }

    /** @return list<string> */
    public static function referenceList(mixed $value, int $maxItems = 25, int $maxLength = 191): array
    {
        if ($value === null || $value === '') { return []; }
        if (!is_array($value) || count($value) > $maxItems) { throw new RuntimeException('Evidence reference list is invalid.'); }
        $result=[];
        foreach($value as $item){
            if(!is_string($item))throw new RuntimeException('Evidence reference list is invalid.');
            $item=sanitize_text_field($item);
            if($item===''||strlen($item)>$maxLength||SensitiveContentDetector::containsProhibitedSecret($item))throw new RuntimeException('Evidence reference is unsafe.');
            $result[]=$item;
        }
        return array_values(array_unique($result));
    }

    public static function safeTextarea(mixed $value, int $maxLength = 20000, bool $required = false): string
    {
        $text=sanitize_textarea_field((string)$value);
        if(($required&&trim($text)==='')||strlen($text)>$maxLength)throw new RuntimeException('Required text is missing or exceeds its safe limit.');
        if($text!==''&&SensitiveContentDetector::containsProhibitedSecret($text))throw new RuntimeException('Text contains prohibited secret data.');
        return $text;
    }
}

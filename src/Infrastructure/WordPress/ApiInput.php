<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use RuntimeException;
use Sabri\CF02\Security\SensitiveContentDetector;

final class ApiInput
{
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

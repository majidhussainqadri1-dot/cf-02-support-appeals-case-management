<?php

declare(strict_types=1);

namespace Sabri\CF02;

final class Autoload
{
    private const PREFIX = 'Sabri\\CF02\\';

    public static function register(string $sourceDirectory): void
    {
        $sourceDirectory = rtrim($sourceDirectory, '/\\') . DIRECTORY_SEPARATOR;

        spl_autoload_register(static function (string $class) use ($sourceDirectory): void {
            if (!str_starts_with($class, self::PREFIX)) {
                return;
            }

            $relativeClass = substr($class, strlen(self::PREFIX));
            $path = $sourceDirectory . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

            if (is_readable($path)) {
                require_once $path;
            }
        });
    }
}

<?php

declare(strict_types=1);

namespace OneSeven9955\LogKeeper;

final class Util
{
    public static function joinPath(string ...$paths): string
    {
        $stack = [];

        foreach ($paths as $path) {
            $stack[] = rtrim($path, \DIRECTORY_SEPARATOR);
        }

        return join(\DIRECTORY_SEPARATOR, $stack);
    }
}

<?php

declare(strict_types=1);

namespace OneSeven9955\LogKeeper;

final class ArchiveException extends LogKeeperException
{
    public static function couldNotOpen(string $path, int $errorCode): ArchiveException
    {
        $errorStr = match ($errorCode) {
            \ZipArchive::ER_INCONS => 'File already exists.',
            \ZipArchive::ER_INVAL => 'Invalid argument.',
            \ZipArchive::ER_MEMORY => 'Malloc failure.',
            \ZipArchive::ER_NOENT => 'No such file.',
            \ZipArchive::ER_NOZIP => 'Not a zip archive.',
            \ZipArchive::ER_READ => 'Read error.',
            \ZipArchive::ER_SEEK => 'Seek error.',
            \ZipArchive::ER_OPEN => 'Cant\'t open file.',
            default => 'Unknown error.',
        };

        return new ArchiveException(sprintf("Could not open zip archive '%s': %s", $path, $errorStr));
    }
}

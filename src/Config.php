<?php

declare(strict_types=1);

namespace OneSeven9955\LogKeeper;

use OneSeven9955\LogKeeper\Frequency;

final class Config
{
    public function __construct(
        private readonly string $path,
        private readonly \DateInterval $timeDelta,
        private readonly string $oldArchiveName = "old.zip",
    ) {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getTimeDelta(): \DateInterval
    {
        return $this->timeDelta;
    }

    public function getOldArchiveName(): string
    {
        return $this->oldArchiveName;
    }
}

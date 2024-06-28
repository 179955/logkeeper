<?php

declare(strict_types=1);

namespace OneSeven9955\LogKeeper;

use OneSeven9955\LogKeeper\Frequency;

final class Config
{
    public function __construct(
        /**
         * Path (glob pattern) to find log files for keeping.
         *
         * Example: __DIR__ . "/logs/*.log";
         *
         * TODO: allow to set directory, and look for *.log files in it.
         **/
        private readonly string $path,

        /**
         * The time delta from the current date used to determine
         * when log files are considered old.
         *
         * The old files are moved to the old archive.
         *
         * Example: \DateInterval::createFromDateString("1 month");
         **/
        private readonly \DateInterval $timeDelta,

        /**
         * Old files are moved to the old archive.
         * The parameter sets archive filename relative to the "path".
         *
         * Default: "old.zip"
         *
         * TODO: allow to pass relative path, i.e. with directory.
         **/
        private readonly string $oldArchiveName = "old.zip",

        /**
         * The parameter specifies the maximum number of log files
         * to retain in the old archive.
         *
         * If the count is -1, all log files will be kept in the
         * old archive.
         *
         * If the count is 0, all old log files will be removed.
         *
         * Default: -1.
         **/
        private readonly int $oldCount = -1,
    ) {
        if ($this->oldCount < -1) {
            throw new \InvalidArgumentException("Max old count parameter must not be less than -1");
        }
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

    public function getOldCount(): int
    {
        return $this->oldCount;
    }
}

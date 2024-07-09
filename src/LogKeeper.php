<?php

declare(strict_types=1);

namespace OneSeven9955\LogKeeper;

use Generator;
use OneSeven9955\LogKeeper\Util;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ZipArchive;

final class LogKeeper implements LoggerAwareInterface
{
    public function __construct(
        private readonly Config $config,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function run(): void
    {
        $now = new \DateTimeImmutable();
        $targetDate = $now->sub($this->config->getTimeDelta());

        $this->logger->debug("Starting log keeping");

        foreach ($this->listFiles() as $filename) {
            $filetime = filemtime($filename);

            if ($targetDate->getTimestamp() >= $filetime) {
                $this->compress($filename);
            }
        }

        $this->logger->debug("Log keeping has been completed");
    }

    /**
     * @return Generator<int,string>
     */
    private function listFiles(): \Generator
    {
        $pat = $this->config->getPath();

        if (\is_dir($pat)) {
            $pat = Util::joinPath($pat, "*.log");
        }

        foreach (\glob($pat) as $file) {
            if ($this->testFile($file)) {
                yield $file;
            }
        }
    }

    private function compress(string $filepath): bool
    {
        $maxCount = $this->config->getOldCount();
        if (0 === $maxCount) {
            \unlink($filepath);
            return true;
        }

        $dir = \dirname($filepath);
        $oldPath = Util::joinPath($dir, $this->config->getOldPath());
        $oldPathDir = \dirname($oldPath);
        if (!is_dir($oldPathDir)) {
            mkdir($oldPathDir, permissions: 0755, recursive: true);
        }
        $perms = \fileperms($oldPathDir);
        if (($perms & 0700) !== 0700) {
            chmod($oldPathDir, permissions: 0755);
        }

        $zip = new ZipArchive();
        if (($error = $zip->open($oldPath, ZipArchive::CREATE)) !== true) {
            throw ArchiveException::couldNotOpen($oldPath, $error);
        }

        $zipLen = $zip->count();

        if ($maxCount > 0 && $zipLen >= $maxCount) {
            $stats = [];

            for ($i = 0; $i < $zipLen; ++$i) {
                $stats[] = $zip->statIndex($i);
            }

            usort($stats, static function (array $a, array $b): int {
                return $a["mtime"] <=> $b["mtime"];
            });

            while ($zipLen >= $maxCount && count($stats) > 0) {
                $stat = array_shift($stats);
                $zip->deleteIndex($stat["index"]);
                $this->logger->debug(sprintf("Removed old file '%s'", $stat["name"]));
                --$zipLen;
            }
        }

        $zip->addFile($filepath, \basename($filepath));
        $zip->close();

        \unlink($filepath);

        return true;
    }

    private function testFile(string $filepath): bool
    {
        if (is_file($filepath) === false) {
            return false;
        }

        return true;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}

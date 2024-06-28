<?php

declare(strict_types=1);

namespace OneSeven9955\LogKeeper;

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

    private function listFiles(): \Generator
    {
        foreach (\glob($this->getPat()) as $file) {
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
        $archivePath = Util::joinPath($dir, $this->config->getOldPath());

        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::CREATE) !== true) {
            $this->logger->warning(sprintf("Could not open zip archive '%s'.", $archivePath));
            return false;
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

    private function getPat(): string
    {
        return $this->config->getPath();
    }

    private function getDir(): string
    {
        return \dirname($this->config->getPath());
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}

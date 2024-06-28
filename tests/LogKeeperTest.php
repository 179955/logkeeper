<?php

declare(strict_types=1);

namespace OneSeven9955\Tests;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use OneSeven9955\LogKeeper\LogKeeper;
use OneSeven9955\LogKeeper\Frequency;
use OneSeven9955\LogKeeper\Config;
use OneSeven9955\LogKeeper\Util;
use Symfony\Component\Finder\Finder;
use PHPUnit\Framework\TestCase;
use ZipArchive;
use DateTimeImmutable;
use DateInterval;

final class LogKeeperTest extends TestCase
{
    public const LOG_DIRECTORY = __DIR__.'/Data/logs';

    protected function tearDown(): void
    {
        $files = Finder::create()
            ->in(self::LOG_DIRECTORY)
            ->ignoreUnreadableDirs()
            ->name(['*.log', '*.zip']);

        foreach ($files as $file) {
            unlink($file->getRealPath());
        }
    }

    private function generateLogs(DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        if (!is_dir(self::LOG_DIRECTORY.'/sub')) {
            mkdir(directory: self::LOG_DIRECTORY.'/sub', recursive: true);
        }

        $cur = $start;
        $i = 0;

        while ($cur->getTimestamp() <= $end->getTimestamp()) {
            touch(Util::joinPath(self::LOG_DIRECTORY, sprintf("test-%s.log", $cur->format('Y-m-d'))), mtime: $cur->getTimestamp());
            touch(Util::joinPath(self::LOG_DIRECTORY, "sub", sprintf("test-%s.log", $cur->format('Y-m-d'))), mtime: $cur->getTimestamp());

            $cur = $cur->add(\DateInterval::createFromDateString("1 day"));
            ++$i;
        }

        return $i;
    }

    public function testKeepingLogsMonthTimeDelta(): void
    {
        $logsCount = $this->generateLogs(
            start: new DateTimeImmutable('2024-01-01 00:00:00'),
            end: new DateTimeImmutable('2024-01-31 00:00:00'),
        );
        $this->assertEquals($logsCount, 31);
        $_ = $this->generateLogs(
            start: new DateTimeImmutable(),
            end: (new DateTimeImmutable())->add(DateInterval::createFromDateString("+10 days")),
        );

        $config = new Config(
            path: Util::joinPath(self::LOG_DIRECTORY, '*.log'),
            timeDelta: \DateInterval::createFromDateString("1 month"),
        );

        $service = new LogKeeper(
            config: $config,
        );

        $service->run();

        $oldPath = Util::joinPath(self::LOG_DIRECTORY, $config->getOldArchiveName());

        $this->assertTrue(is_file($oldPath));
        $zip = new ZipArchive();

        $this->assertTrue($zip->open($oldPath));
        $this->assertEquals($zip->count(), $logsCount);
    }

    public function testKeepingLogsWeekTimeDelta(): void
    {
        $logsCount = $this->generateLogs(
            start: new DateTimeImmutable('2024-01-01 00:00:00'),
            end: new DateTimeImmutable('2024-01-7 00:00:00'),
        );
        $this->assertEquals($logsCount, 7);
        $_ = $this->generateLogs(
            start: new DateTimeImmutable(),
            end: (new DateTimeImmutable())->add(DateInterval::createFromDateString("+10 days")),
        );

        $config = new Config(
            path: Util::joinPath(self::LOG_DIRECTORY, '*.log'),
            timeDelta: \DateInterval::createFromDateString("1 week"),
        );

        $service = new LogKeeper(
            config: $config,
        );

        $service->run();

        $oldPath = Util::joinPath(self::LOG_DIRECTORY, $config->getOldArchiveName());

        $this->assertTrue(is_file($oldPath));
        $zip = new ZipArchive();

        $this->assertTrue($zip->open($oldPath));
        $this->assertEquals($zip->count(), $logsCount);
    }
}

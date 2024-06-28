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

    private function generateLogs(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        if (!is_dir(self::LOG_DIRECTORY.'/sub')) {
            mkdir(directory: self::LOG_DIRECTORY.'/sub', recursive: true);
        }

        $cur = $start;
        $i = 0;
        $stats = [];

        while ($cur->getTimestamp() <= $end->getTimestamp()) {
            $path = Util::joinPath(self::LOG_DIRECTORY, $name = sprintf("test-%s.log", $cur->format('Y-m-d')));
            $mtime = $cur->getTimestamp();

            \touch($path, mtime: $mtime);

            $stats[] = [
                "name" => $name,
                "index" => $i,
                "mtime" => $mtime,
            ];

            $cur = $cur->add(\DateInterval::createFromDateString("1 day"));
            ++$i;
        }

        return $stats;
    }

    public function testKeepingLogsMonthTimeDelta(): void
    {
        $stats = $this->generateLogs(
            start: new DateTimeImmutable('2024-01-01 00:00:00'),
            end: new DateTimeImmutable('2024-01-31 00:00:00'),
        );
        $this->assertEquals(count($stats), 31);
        $this->generateLogs(
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

        $oldPath = Util::joinPath(self::LOG_DIRECTORY, $config->getOldPath());

        $this->assertTrue(is_file($oldPath));
        $zip = new ZipArchive();

        $this->assertTrue($zip->open($oldPath));
        $this->assertEquals($zip->count(), count($stats));
    }

    public function testNeverCreatesOldFileWhenCountIsZero(): void
    {
        $stats = $this->generateLogs(
            start: (new DateTimeImmutable('today'))->modify('-1 month')->modify('-1 day'),
            end: (new DateTimeImmutable('today'))->modify('-1 month'),
        );
        $keepStats = $this->generateLogs(
            start: new DateTimeImmutable('today'),
            end: new DateTimeImmutable('today +1 day'),
        );

        $this->assertEquals(count($keepStats), 2);
        $this->assertEquals(count($stats), 2);

        $config = new Config(
            path: Util::joinPath(self::LOG_DIRECTORY, '*.log'),
            timeDelta: \DateInterval::createFromDateString("1 month"),
            oldCount: 0,
        );

        $service = new LogKeeper(
            config: $config,
        );

        $service->run();

        $oldPath = Util::joinPath(self::LOG_DIRECTORY, $config->getOldPath());

        foreach ($keepStats as ['name' => $name]) {
            $this->assertTrue(is_file(Util::joinPath(self::LOG_DIRECTORY, $name)));
        }

        foreach ($stats as ['name' => $name]) {
            $this->assertFalse(is_file(Util::joinPath(self::LOG_DIRECTORY, $name)), "The file '{$name}' was not removed");
        }

        $this->assertFalse(is_file($oldPath));
    }

    public function testRemovesTheOldestLogsWhenCountIsExceeded(): void
    {
        $stats = $this->generateLogs(
            start: new DateTimeImmutable('2024-01-01 00:00:00'),
            end: new DateTimeImmutable('2024-01-31 00:00:00'),
        );

        $this->assertEquals(count($stats), 31);
        $this->generateLogs(
            start: new DateTimeImmutable(),
            end: (new DateTimeImmutable())->add(DateInterval::createFromDateString("+10 days")),
        );

        $config = new Config(
            path: Util::joinPath(self::LOG_DIRECTORY, '*.log'),
            timeDelta: \DateInterval::createFromDateString("1 month"),
            oldCount: 10,
        );

        $service = new LogKeeper(
            config: $config,
        );

        $service->run();

        $oldPath = Util::joinPath(self::LOG_DIRECTORY, $config->getOldPath());

        $this->assertTrue(is_file($oldPath));
        $zip = new ZipArchive();

        $this->assertTrue($zip->open($oldPath));
        $this->assertEquals($zip->count(), $config->getOldCount());

        usort($stats, static function (array $a, array $b): int {
            return $b['mtime'] <=> $a['mtime'];
        });

        $oldCount = 10;
        foreach ($stats as ['name' => $name]) {
            $this->assertTrue(is_array($zip->statName($name)), "Could not stat '{$name}' in old file.");
            --$oldCount;

            if ($oldCount === 0) {
                break;
            }
        }
    }

    public function testKeepingLogsWeekTimeDelta(): void
    {
        $stats = $this->generateLogs(
            start: new DateTimeImmutable('2024-01-01 00:00:00'),
            end: new DateTimeImmutable('2024-01-7 00:00:00'),
        );
        $this->assertEquals(count($stats), 7);
        $this->generateLogs(
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

        $oldPath = Util::joinPath(self::LOG_DIRECTORY, $config->getOldPath());

        $this->assertTrue(is_file($oldPath));
        $zip = new ZipArchive();

        $this->assertTrue($zip->open($oldPath));
        $this->assertEquals($zip->count(), count($stats));
    }

    public function testCanUseDirectoryInPath(): void
    {
        $stats = $this->generateLogs(
            start: (new DateTimeImmutable('today'))->modify('-2 days'),
            end: (new DateTimeImmutable('today'))->modify('-1 day'),
        );

        $this->assertEquals(count($stats), 2);

        $config = new Config(
            path: self::LOG_DIRECTORY,
            timeDelta: \DateInterval::createFromDateString("1 day"),
            oldCount: -1,
        );

        $service = new LogKeeper(
            config: $config,
        );

        $service->run();

        foreach ($stats as ['name' => $name]) {
            $this->assertFalse(is_file(Util::joinPath(self::LOG_DIRECTORY, $name)), "The file '{$name}' was not removed");
        }

        $oldPath = Util::joinPath(self::LOG_DIRECTORY, $config->getOldPath());
        $this->assertTrue(is_file($oldPath));
        $zip = new ZipArchive();

        $this->assertTrue($zip->open($oldPath));
        $this->assertEquals($zip->count(), count($stats));
    }
}

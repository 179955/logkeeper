LogKeeper archives old files based on a customizable time delta, ensuring your logs remain organized, and easily manageable.

### Getting Started
```php
use OneSeven9955\LogKeeper\Config;
use OneSeven9955\LogKeeper\LogKeeper;

$config = new Config(
    path: '/path/to/log/files/*.log',
    timeDelta: \DateInterval::createFromDateString("1 month"),
);

$service = new LogKeeper(
    config: $config,
);

$service->run();
```

With custom old archive name:
```php
$config = new Config(
    path: '/path/to/log/files/*.log',
    timeDelta: \DateInterval::createFromDateString("1 month"),
    oldPath: 'old/custom.zip', // Default: "old.zip"
);
```

Keep 30 old files:
```php
$config = new Config(
    path: '/path/to/log/files/*.log',
    timeDelta: \DateInterval::createFromDateString("1 month"),
    oldCount: 30,
);
```

Remove all old files:
```php
$config = new Config(
    path: '/path/to/log/files/*.log',
    timeDelta: \DateInterval::createFromDateString("1 month"),
    oldCount: 0,
);
```

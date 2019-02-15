# Outlook

## Installation

Use composer to install Outlook package.

```
$ composer require symplicity/outlook "^1.0"
```

# Usage

We will be using Monolog Logger to log all info. Logger needs to be passed for all instance creations.

```
<?php
$log = new Logger('outlook_sync');
$streamHandler = new StreamHandler($file);
$log->pushHandler($streamHandler, Logger::WARNING);
```            

## Documentations
- [Starting](docs/calendar-usage.md)
- [Token Handling](docs/token-usage.md)
- [Events Handling](docs/event-usage.md)





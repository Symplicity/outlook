# Outlook

### Status
[![Build Status](https://travis-ci.org/Symplicity/outlook.svg?branch=master)](https://travis-ci.org/Symplicity/outlook)
[![Latest Stable Version](https://poser.pugx.org/symplicity/outlook/v/stable)](https://packagist.org/packages/symplicity/outlook)
[![License](https://poser.pugx.org/symplicity/outlook/license)](https://packagist.org/packages/symplicity/outlook)

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





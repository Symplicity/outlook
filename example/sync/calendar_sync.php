<?php

declare(strict_types=1);

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

require_once __DIR__ . '/LocalCalendarSyncHandler.php';

/**
 * Initialize calendar class
 * Setting up telemetry is optional (Default: NoopTracer)
 */
$streamHandler = new StreamHandler('/outlook/logs/log.log');
$logger = new Logger('outlook_sync');
$logger->pushHandler($streamHandler);

$syncHandler = new LocalCalendarSyncHandler('{{clientId}}', '{{clientToken}}', '{{AccessToken}}', [
    'logger' => $logger
]);

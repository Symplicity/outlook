<?php

declare(strict_types=1);

namespace Symplicity\Outlook;

use Psr\Log\LoggerInterface;
use Symplicity\Outlook\Entities\Reader;
use Symplicity\Outlook\Http\Connection;
use Symplicity\Outlook\Http\Request;
use Symplicity\Outlook\Http\RequestOptions;
use Symplicity\Outlook\Interfaces\CalendarInterface;
use Symplicity\Outlook\Utilities\EventTypes;
use Symplicity\Outlook\Utilities\RequestType;

abstract class Calendar implements CalendarInterface
{
    protected const EVENT_DELETED = 'deleted';

    private $token;

    protected $usePool = false;
    /** @var Request $requestHandler */
    protected $requestHandler;

    /** @var LoggerInterface | null $logger */
    protected $logger;

    /** @var Reader $reader */
    public $reader;

    public function __construct(string $token, array $args = [])
    {
        $this->token = $token;
        $this->logger = $args['logger'] ?? null;
        $this->setRequestHandler($args['request']);
        $this->reader = $args['reader'];
    }

    public function sync(array $params = [])
    {
//        if (!empty($params['batch'])) {
//            $this->batch($params);
//        } else {
//            $this->pool($params);
//        }

        $this->pull($params);
    }

    protected function pull(array $params = [])
    {
        try {
            $url = $params['endPoint'];
            /** @var ResponseIteratorInterface $events */
            $this->requestHandler->getEvents($url, $params);
            foreach ($this->requestHandler->getReponseIterator()->each() as $event) {
                if (isset($params['skipOccurrences'], $event['Type'])
                    && $event['Type'] == EventTypes::Occurrence) {
                    continue;
                }

                if (isset($event['reason']) && $event['reason'] === static::EVENT_DELETED) {
                    $this->deleteEventLocal($this->getReader()->deleted($event));
                    continue;
                }

                $entity = $this->getReader()->hydrate($event);
                $this->saveEventLocal($entity);
            }
        } catch (\Exception $e) {
            throw new ReadFailed($e->getMessage(), $e->getCode(), $e->error_details());
        }
    }

    private function setRequestHandler(?Request $requestHandler): void
    {
        if ($requestHandler === null) {
            $requestHandler = new Request($this->token, [
                'requestOptions' => function (string $url, RequestType $methodType, array $args = []) {
                    return new RequestOptions($url, $methodType, $args);
                },
                'connection' => new Connection($this->logger)
            ]);
        }

        $this->requestHandler = $requestHandler;
    }

    public function getReader(): Reader
    {
        return $this->reader instanceof Reader ? $this->reader : new Reader;
    }
}

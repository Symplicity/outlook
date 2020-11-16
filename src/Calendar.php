<?php

declare(strict_types=1);

namespace Symplicity\Outlook;

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symplicity\Outlook\Entities\Occurrence;
use Symplicity\Outlook\Entities\Reader;
use Symplicity\Outlook\Exception\ReadError;
use Symplicity\Outlook\Http\Batch;
use Symplicity\Outlook\Http\Connection;
use Symplicity\Outlook\Http\Request;
use Symplicity\Outlook\Http\RequestOptions;
use Symplicity\Outlook\Interfaces\CalendarInterface;
use Symplicity\Outlook\Interfaces\Entity\DeleteInterface;
use Symplicity\Outlook\Interfaces\Entity\ReaderEntityInterface;
use Symplicity\Outlook\Interfaces\Entity\WriterInterface;
use Symplicity\Outlook\Utilities\EventTypes;
use Symplicity\Outlook\Utilities\RequestType;
use Symplicity\Outlook\Utilities\ResponseHandler;

abstract class Calendar implements CalendarInterface
{
    protected const EVENT_DELETED = 'deleted';
    public const BATCH_BY = 20;

    private $token;

    /** @var bool $batch */
    protected $batch = false;

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
        $this->setRequestHandler($args['request'] ?? null, $args['connectionClientOptions'] ?? []);
        $this->reader = $args['reader'] ?? null;
    }

    public function sync(array $params = []) : void
    {
        $this->push($params);
        $this->pull($params);
    }

    public function push(array $params = []) : void
    {
        // TODO: add individual sync later
        $this->batch($params);
    }

    public function upsert(WriterInterface $writer, array $params = []): ResponseInterface
    {
        return $this->requestHandler->upsert($writer, $params);
    }

    public function delete(DeleteInterface $writer, array $params = []): ResponseInterface
    {
        return $this->requestHandler->delete($writer, $params);
    }

    public function getEvent(string $url, array $params = []) : ?ReaderEntityInterface
    {
        try {
            $response = $this->requestHandler->getEvent($url, $params);
            $event = ResponseHandler::toArray($response);
            if (!count($event)) {
                throw new ReadError('Could not find event', 404);
            }

            if (isset($params['skipOccurrences'], $event['Type'])
                && $event['Type'] == EventTypes::Occurrence) {
                return null;
            }

            if (isset($event['reason']) && $event['reason'] === static::EVENT_DELETED) {
                $this->deleteEventLocal($this->getReader()->deleted($event));
                return null;
            }

            $entity = $this->getEntity($event);
            $this->saveEventLocal($entity);
            return $entity;
        } catch (\Exception $e) {
            throw new ReadError($e->getMessage(), $e->getCode());
        }
    }

    public function getEventInstances(string $url, array $params = []) : void
    {
        try {
            $response = $this->requestHandler->getEvent($url, $params);
            $event = ResponseHandler::toArray($response);
            if (!count($event)) {
                throw new ReadError('Could not find event', 404);
            }

            foreach ($event['value'] as $event) {
                if (isset($params['skipOccurrences'], $event['Type'])
                    && $event['Type'] == EventTypes::Occurrence) {
                    continue;
                }

                if (isset($event['reason']) && $event['reason'] === static::EVENT_DELETED) {
                    $event = $this->getReader()->deleted($event);
                    $this->deleteEventLocal($event);
                    continue;
                }

                $entity = $this->getEntity($event);
                $this->saveEventLocal($entity);
            }
        } catch (\Exception $e) {
            throw new ReadError($e->getMessage(), $e->getCode());
        }
    }

    protected function batch(array $params = []) : void
    {
        $eventsToWrite = $this->getLocalEvents();
        $chunks = array_chunk($eventsToWrite, static::BATCH_BY);

        foreach ($chunks as $chunk) {
            $batch = [];

            /** @var WriterInterface $event */
            foreach ($chunk as $event) {
                if ($event instanceof WriterInterface
                    || $event instanceof DeleteInterface) {
                    $batch[] = $event;
                }
            }

            $responses = $this->requestHandler->batch($batch, $params);
            $this->handleBatchResponse($responses);
        }
    }

    protected function pull(array $params = []) : void
    {
        try {
            $url = $params['endPoint'];
            /** @var ResponseIteratorInterface $events */
            $this->requestHandler->getEvents($url, $params);
            foreach ($this->requestHandler->getResponseIterator()->each() as $event) {
                if (isset($params['skipOccurrences'], $event['Type'])
                    && $event['Type'] == EventTypes::Occurrence) {
                    continue;
                }

                if (isset($event['reason']) && $event['reason'] === static::EVENT_DELETED) {
                    $this->deleteEventLocal($this->getReader()->deleted($event));
                    continue;
                }

                $this->saveEventLocal($this->getEntity($event));
            }
        } catch (\Exception $e) {
            throw new ReadError($e->getMessage(), $e->getCode());
        }
    }

    protected function getEntity(array $event) : ReaderEntityInterface
    {
        if ($event['Type'] == EventTypes::Occurrence) {
            return $this->getOccurrenceReader()->hydrate($event);
        }

        return  $this->getReader()->hydrate($event);
    }

    private function setRequestHandler(?Request $requestHandler, array $connectionClientOptions = []): void
    {
        if ($requestHandler === null) {
            $token = $this->token;
            $logger = $this->logger;
            $requestHandler = new Request($token, [
                'requestOptions' => function(string $url, RequestType $methodType, array $args = []) {
                    return new RequestOptions($url, $methodType, $args);
                },
                'connection' => new Connection($logger, $connectionClientOptions),
                'batchConnectionHandler' => function() use ($logger, $connectionClientOptions) {
                    return new Batch($logger, $connectionClientOptions);
                }
            ]);
        }

        $this->requestHandler = $requestHandler;
    }

    protected function getReader(): ReaderEntityInterface
    {
        return new Reader;
    }

    protected function getOccurrenceReader(): ReaderEntityInterface
    {
        return new Occurrence;
    }

    protected function getExceptionReader(): ReaderEntityInterface
    {
        return new Reader;
    }

    public function isBatchRequest(): CalendarInterface
    {
        $this->batch = true;
        return $this;
    }
}

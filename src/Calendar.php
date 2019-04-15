<?php

declare(strict_types=1);

namespace Symplicity\Outlook;

use Psr\Log\LoggerInterface;
use Symplicity\Outlook\Entities\Occurrence;
use Symplicity\Outlook\Entities\Reader;
use Symplicity\Outlook\Exception\ReadError;
use Symplicity\Outlook\Http\Connection;
use Symplicity\Outlook\Http\Request;
use Symplicity\Outlook\Http\RequestOptions;
use Symplicity\Outlook\Interfaces\CalendarInterface;
use Symplicity\Outlook\Interfaces\Entity\DeleteInterface;
use Symplicity\Outlook\Interfaces\Entity\ReaderEntityInterface;
use Symplicity\Outlook\Interfaces\Entity\WriterInterface;
use Symplicity\Outlook\Utilities\EventTypes;
use Symplicity\Outlook\Utilities\RequestType;

abstract class Calendar implements CalendarInterface
{
    protected const EVENT_DELETED = 'deleted';
    protected const BATCH_BY = 20;

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
        $this->setRequestHandler($args['request']);
        $this->reader = $args['reader'] ?? null;
    }

    public function sync(array $params = []) : void
    {
        $this->push($params);
        $this->pull($params);
    }

    public function push(array $params = []) : void
    {
        if ($this->batch) {
            $this->batch($params);
        } else {
            $this->push($params);
        }
    }

    protected function batch(array $params = []) : void
    {
        $batch = [];
        $batchDelete = [];

        $eventsToWrite = $this->getLocalEvents();

        $chunks = array_chunk($eventsToWrite, static::BATCH_BY);

        foreach ($chunks as $chunk) {
            /** @var WriterInterface $event */
            foreach ($chunk as $event) {
                if ($event instanceof DeleteInterface) {
                    $batchDelete[] = $event;
                    continue;
                }

                if (!$event instanceof WriterInterface) {
                    continue;
                }

                $batch[] = $event;
            }

            if (count($batchDelete)) {
                $this->requestHandler->batchDelete($batchDelete, $params);
            }

            $this->requestHandler->batch($batch, $params);
            $this->handlePoolResponses($this->requestHandler->getResponseFromBatch());
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

    private function setRequestHandler(?Request $requestHandler): void
    {
        if ($requestHandler === null) {
            $requestHandler = new Request($this->token, [
                'requestOptions' => function(string $url, RequestType $methodType, array $args = []) {
                    return new RequestOptions($url, $methodType, $args);
                },
                'connection' => new Connection($this->logger)
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
        return new Occurrence();
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

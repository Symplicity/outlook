<?php

declare(strict_types=1);

namespace Symplicity\Outlook;

use Closure;
use GuzzleHttp\Client;
use Http\Promise\Promise;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Microsoft\Graph\BatchRequestBuilder;
use Microsoft\Graph\Core\Requests\BatchRequestContent;
use Microsoft\Graph\Core\Requests\BatchRequestItem;
use Microsoft\Graph\Core\Requests\BatchResponseContent;
use Microsoft\Graph\Core\Requests\BatchResponseItem;
use Microsoft\Graph\Generated\Models\Event as GraphEvent;
use Microsoft\Graph\Generated\Models\EventType;
use Microsoft\Graph\Generated\Models\ODataErrors\MainError;
use Microsoft\Graph\Generated\Models\ODataErrors\ODataError;
use Microsoft\Graph\Generated\Users\Item\CalendarView\Delta\DeltaGetResponse;
use Microsoft\Graph\Generated\Users\Item\Events\EventsRequestBuilderPostRequestConfiguration;
use Microsoft\Graph\Generated\Users\Item\Events\Item\EventItemRequestBuilderDeleteRequestConfiguration;
use Microsoft\Graph\Generated\Users\Item\Events\Item\EventItemRequestBuilderGetQueryParameters;
use Microsoft\Graph\Generated\Users\Item\Events\Item\EventItemRequestBuilderPatchRequestConfiguration;
use Microsoft\Graph\Generated\Users\Item\Events\Item\Instances\InstancesRequestBuilderGetQueryParameters;
use Microsoft\Kiota\Abstractions\RequestAdapter;
use Microsoft\Kiota\Serialization\Json\JsonParseNode;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symplicity\Outlook\Entities\Occurrence;
use Symplicity\Outlook\Entities\Reader;
use Symplicity\Outlook\Exception\ReadError;
use Symplicity\Outlook\Interfaces\CalendarInterface;
use Symplicity\Outlook\Interfaces\Entity\ReaderEntityInterface;
use Symplicity\Outlook\Interfaces\Utilities\CalendarView\CalendarViewParamsInterface;
use Symplicity\Outlook\Models\Event;
use Symplicity\Outlook\Utilities\CalendarView\Delta\DeltaRequestBuilderGetRequestConfiguration;
use Symplicity\Outlook\Utilities\CalendarView\GraphServiceCalendarView;
use Symplicity\Outlook\Utilities\CalendarView\PageIterator;
use Symplicity\Outlook\Utilities\EventView\GraphServiceEvent;

abstract class Calendar implements CalendarInterface
{
    use BearerAuthorizationTrait;
    use AuthorizationContextTrait;
    use RequestConfigurationTrait;

    // Maximum events allowed for graph batch api
    public const BATCH_BY = 19;

    public const DEFAULT_TIMEZONE = 'Eastern Standard Time';

    protected ?LoggerInterface $logger = null;
    protected GraphServiceEvent $graphService;

    /**
     * If you want to use kiota/ms-graph telemetry, extend this class to declare a tracer
     * @param array<string, mixed> $args
     */
    public function __construct(private readonly string $clientId, private readonly string $clientSecret, private readonly string $token, array $args = [])
    {
        $this->logger = $args['logger'] ?? null;
        $this->graphService = new GraphServiceEvent(
            $this->clientId,
            $this->clientSecret,
            $this->token
        );
    }

    /// MARK: Calendar Event Reads

    /** Pull all events from outlook in an iterative manner
     *  Set prefer headers like odata.maxpagesize and timezone to control the data received
     *  Calls saveEventLocal/deleteEventLocal methods
     * @param array<string, mixed> $args
     * @throws ReadError|\Throwable
     */
    public function pull(CalendarViewParamsInterface $params, ?Closure $deltaLinkStore = null, array $args = []): void
    {
        try {
            $this->logger?->info('Pulling events...', [
                'params' => http_build_query($params),
                'deltaToken' => $deltaLinkStore
            ]);

            $graphServiceClient = new GraphServiceCalendarView(
                $this->clientId,
                $this->clientSecret,
                $this->token
            );

            if (isset($args['client']) && $args['client'] instanceof Client) {
                $graphServiceClient->setHttpClient($args['client']);
            }

            $requestConfiguration = $this->getCalendarViewRequestConfiguration($params);

            $events = $graphServiceClient
                ->client($params)
                ->me()
                ->calendarView()
                ->delta()
                ->get($requestConfiguration)
                ->wait();

            if (empty($events) || empty($requestAdapter = $graphServiceClient->getRequestAdapter())) {
                return;
            }

            $this->iterateThrough(
                $events,
                $requestAdapter,
                $requestConfiguration,
                $deltaLinkStore
            );

            $this->logger?->info('All events received...', [
                'params' => http_build_query($params)
            ]);
        } catch (\Exception $e) {
            $this->convertToReadableError($e);
        }
    }

    /**
     * Get Event by event id (extract extension as well)
     * @param array<string, mixed> $args
     * @throws ReadError|\Throwable
     */
    public function getEventBy(string $id, ?EventItemRequestBuilderGetQueryParameters $params = null, ?Closure $beforeReturn = null, array $args = []): ?ReaderEntityInterface
    {
        try {
            $this->logger?->info('Getting event by id ...', [
                'id' => $id
            ]);

            $requestConfiguration = $this->getEventViewRequestConfiguration($params);

            $event = $this->graphService
                ->client($args)
                ->me()
                ->events()
                ->byEventId($id)
                ->get($requestConfiguration)
                ->wait();

            if (empty($event)) {
                return null;
            }

            $entity = $this->getEntity($event);
            $beforeReturn?->call($this, $entity, $event);
            $this->logger?->info('Getting event by id complete ...', [
                'id' => $id
            ]);

            return $entity;
        } catch (\Exception $e) {
            $this->logger?->info('Getting event by id failed ...', [
                'id' => $id,
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            throw new ReadError($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @param array<string, mixed> $args
     * @throws ReadError|\Throwable
     */
    public function getEventInstances(string $id, ?InstancesRequestBuilderGetQueryParameters $params = null, array $args = []): void
    {
        try {
            $this->logger?->info('Getting instances of recurring event ...', [
                'id' => $id
            ]);

            $requestConfiguration = $this->getInstancesViewRequestConfiguration($params);

            $events = $this->graphService
                ->client($args)
                ->me()
                ->events()
                ->byEventId($id)
                ->instances()
                ->get($requestConfiguration)
                ->wait();

            foreach ($events?->getValue() ?? [] as $event) {
                if (null === $event) {
                    continue;
                }

                $this->logger?->info('Receiving event instance ...', [
                    'id' => $id,
                    'event_id' => $event->getId(),
                    'event_name' => $event->getSubject(),
                    'cal_id' => $event->getICalUId(),
                    'type' => $event->getType()?->value()
                ]);

                $entity = $this->getEntity($event);
                $this->saveEventLocal($entity);
            }

            $this->logger?->info('Getting event instances complete ...', [
                'id' => $id
            ]);
        } catch (\Exception $e) {
            $this->convertToReadableError($e);
        }
    }

    // MARK: Event writes

    /**
     * @param array<string, string> $params
     * @param array<string, mixed> $args
     * @throws \JsonException
     * @throws \Exception|\Throwable
     */
    public function push(array $params = [], array $args = []): void
    {
        $this->logger?->info('Pushing batch events to outlook ...', [
            'params' => http_build_query($params)
        ]);

        $postRequestConfiguration = $this->getEventPostRequestConfiguration();
        $patchRequestConfiguration = $this->getEventPatchRequestConfiguration();
        $deleteRequestConfiguration = $this->getEventDeleteRequestConfiguration();

        $batchRequestConfiguration = $this->getEventPostBatchRequestConfiguration();

        $eventsToWrite = $this->getLocalEvents();
        $chunks = array_chunk($eventsToWrite, static::BATCH_BY);

        foreach ($chunks as $chunk) {
            $batch = [];
            $batchCorrelationIds = [];

            /** @var Event $event */
            foreach ($chunk as $event) {
                if ($event instanceof Event) {
                    $this->logger?->info('Preparing events for dispatch ...', [
                        'event_id' => $event->getId(),
                        'event_name' => $event->getSubject(),
                        'cal_id' => $event->getICalUId(),
                    ]);

                    $batch[] = $this->prepareBatchUpsert(
                        $event,
                        $postRequestConfiguration,
                        $patchRequestConfiguration,
                        $deleteRequestConfiguration,
                        $batchCorrelationIds,
                        $args
                    );
                }
            }

            $responses = null;
            if (\count($batch) && ($requestAdapter = $this->graphService->getRequestAdapter())) {
                $batchRequestContent = new BatchRequestContent($batch);
                $batchRequestBuilder = new BatchRequestBuilder($requestAdapter);
                $responses = $batchRequestBuilder
                    ->postAsync($batchRequestContent, $batchRequestConfiguration)
                    ->wait();
            }

            $newResponses = $this->prepareBatchResponse($responses, $batchCorrelationIds);
            $this->handleBatchResponse($newResponses);
        }
    }

    /**
     * @param array<string, mixed> $args
     * @throws \Exception|\Throwable
     */
    public function upsert(Event $event, array $args = []): ?GraphEvent
    {
        $postRequestConfiguration = $this->getEventPostRequestConfiguration();
        $patchRequestConfiguration = $this->getEventPatchRequestConfiguration();

        $eventUpsertRequest = $this->prepareUpsertAsync(
            $event,
            $postRequestConfiguration,
            $patchRequestConfiguration,
            $args
        );

        return $eventUpsertRequest->wait();
    }

    /**
     * @param array<string, mixed> $args
     * @throws \Exception|\Throwable
     */
    public function delete(string $id, array $args = []): void
    {
        $requestConfiguration = new EventItemRequestBuilderDeleteRequestConfiguration();
        $requestConfiguration->headers = $this->getAuthorizationHeaders($this->token);

        $this->graphService
            ->client($args)
            ->me()
            ->events()
            ->byEventId($id)
            ->delete($requestConfiguration)
            ->wait();
    }

    /**
     * @param array<string, ?string> $correlationIds
     */
    protected function prepareBatchResponse(?BatchResponseContent $response = null, array $correlationIds = []): \Generator
    {
        foreach ($response?->getResponses() ?? [] as $response) {
            if ($response instanceof BatchResponseItem) {
                if (in_array($response->getStatusCode(), [200, 201])) {
                    yield $this->createFromDiscriminatorValue($response, $correlationIds);
                } else {
                    $correlationId = $response->getId();
                    yield [
                        'event' => null,
                        'info' => [
                            'status' => $response->getStatusCode(),
                            'location' => $response->getHeaders()['Location'] ?? null,
                            'id' => $correlationId,
                            'guid' => $correlationIds[$correlationId] ?? null
                        ]
                    ];
                }
            }
        }
    }

    /**
     * @param array<string, ?string> $correlationIds
     * @return array<string, mixed> $args
     */
    protected function createFromDiscriminatorValue(BatchResponseItem $response, array $correlationIds = []): array
    {
        $item = [];
        $body = $response->getBody()?->getContents();
        $correlationId = $response->getId();
        if (!empty($body)) {
            $data = \json_decode($body, true);
            if (JSON_ERROR_NONE === json_last_error()) {
                try {
                    $parser = new JsonParseNode($data);
                    $event = $parser->getObjectValue([Event::class, 'createFromDiscriminatorValue']);
                    $item = [
                        'event' => $event,
                        'info' => [
                            'status' => $response->getStatusCode(),
                            'location' => $response->getHeaders()['Location'] ?? null,
                            'id' => $correlationId,
                            'guid' => $correlationIds[$correlationId] ?? $event?->getId() ?? null
                        ]
                    ];
                    // @codeCoverageIgnoreStart
                } catch (\Exception $error) {
                    $item = [
                        'event' => null,
                        'info' => [
                            'status' => $response->getStatusCode(),
                            'location' => $response->getHeaders()['Location'] ?? null,
                            'id' => $correlationId,
                            'guid' => $correlationIds[$correlationId] ?? null,
                            'error' => $error->getMessage()
                        ]
                    ];
                }
                // @codeCoverageIgnoreEnd
            }
        }

        return $item;
    }

    protected function getEntity(GraphEvent $event): ReaderEntityInterface
    {
        if ($event->getType()?->value() === EventType::OCCURRENCE) {
            return $this->getOccurrenceReader()->hydrate($event);
        }

        return $this->getReader()->hydrate($event);
    }

    /**
     * @param array<string, ?string> $batchCorrelationIds
     * @param array<string, mixed> $args
     */
    protected function prepareBatchUpsert(Event $event, EventsRequestBuilderPostRequestConfiguration $postRequestConfiguration, EventItemRequestBuilderPatchRequestConfiguration $patchRequestConfiguration, EventItemRequestBuilderDeleteRequestConfiguration $deleteRequestConfiguration, array &$batchCorrelationIds = [], array $args = []): BatchRequestItem
    {
        $me = $this->graphService
            ->client($args)
            ->me();

        if (!empty($eventId = $event->getId())) {
            if ($event->getIsDelete()) {
                $request = $me->events()
                    ->byEventId($eventId)
                    ->toDeleteRequestInformation($deleteRequestConfiguration);
            } else {
                $request = $me->events()
                    ->byEventId($eventId)
                    ->toPatchRequestInformation($event, $patchRequestConfiguration);
            }
        } else {
            $request = $me->events()
                ->toPostRequestInformation($event, $postRequestConfiguration);
        }

        $correlationId = Uuid::uuid4()->toString();
        $batchCorrelationIds[$correlationId] = $eventId ?? null;
        return new BatchRequestItem($request, $correlationId);
    }

    /**
     * @param array<string, mixed> $args
     * @throws \Exception
     */
    protected function prepareUpsertAsync(Event $event, EventsRequestBuilderPostRequestConfiguration $postRequestConfiguration, EventItemRequestBuilderPatchRequestConfiguration $patchRequestConfiguration, array $args = []): Promise
    {
        $me = $this->graphService
            ->client($args)
            ->me();

        if (!empty($eventId = $event->getId())) {
            $request = $me->events()
                ->byEventId($eventId)
                ->patch($event, $patchRequestConfiguration);
        } else {
            $request = $me->events()
                ->post($event, $postRequestConfiguration);
        }

        return $request;
    }

    /**
     * @throws \Exception
     */
    protected function iterateThrough(DeltaGetResponse $events, RequestAdapter $requestAdapter, DeltaRequestBuilderGetRequestConfiguration $requestConfiguration, ?Closure $deltaLinkStore = null): void
    {
        $iterator = new PageIterator(
            $events,
            $requestAdapter
        );

        $iterator->setHeaders($requestConfiguration->headers);

        $iterator->iterate(function (?GraphEvent $event) {
            if (null === $event) {
                return true;
            }

            $this->logger?->info('Received event...', [
                'event_id' => $event->getId(),
                'event_name' => $event->getSubject(),
                'cal_id' => $event->getICalUId(),
                'additional_data' => $event->getAdditionalData()
            ]);

            $additionalData = $event->getAdditionalData();
            if (isset($additionalData['@removed']['reason'])
                && $additionalData['@removed']['reason'] === 'deleted') {
                $this->deleteEventLocal($event->getId());
                return true;
            }

            $this->saveEventLocal($this->getEntity($event));

            $this->logger?->info('Completed event processing', [
                'event_id' => $event->getId(),
                'event_name' => $event->getSubject()
            ]);
            return true;
        });

        $deltaLinkStore?->call($this, $iterator->getDeltaLink());
    }

    protected function getReader(): ReaderEntityInterface
    {
        return new Reader();
    }

    protected function getOccurrenceReader(): ReaderEntityInterface
    {
        return new Occurrence();
    }

    /**
     * @throws ReadError
     */
    private function convertToReadableError(\Exception $e): never
    {
        $message = null;
        if ($e instanceof ODataError) {
            /** @var MainError $errorInfo */
            $errorInfo = $e->getBackingStore()->get('error');
            $code = 0;
            $localizedDescription = $errorInfo->getMessage() ?? '';
            $message = $errorInfo->getCode();
        } else {
            $code = $e->getCode();
            $localizedDescription = $e->getMessage();
        }

        $this->logger?->info('Received error...', [
            'code' => $code,
            'localizedDescription' => $localizedDescription,
            'message' => $message
        ]);

        $error = new ReadError($localizedDescription, $code);
        $error->setOdataErrorMessage($message);
        throw $error;
    }
}

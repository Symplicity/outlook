<?php

declare(strict_types=1);

namespace Symplicity\Outlook;

use Closure;
use Http\Promise\Promise;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Microsoft\Graph\BatchRequestBuilder;
use Microsoft\Graph\Core\Requests\BatchRequestContent;
use Microsoft\Graph\Generated\Models\Event as GraphEvent;
use Microsoft\Graph\Generated\Models\EventType;
use Microsoft\Graph\Generated\Models\ODataErrors\MainError;
use Microsoft\Graph\Generated\Models\ODataErrors\ODataError;
use Microsoft\Graph\Generated\Users\Item\CalendarView\Delta\DeltaGetResponse;
use Microsoft\Graph\Generated\Users\Item\CalendarView\Delta\DeltaRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\Generated\Users\Item\Events\EventsRequestBuilderPostRequestConfiguration;
use Microsoft\Graph\Generated\Users\Item\Events\Item\EventItemRequestBuilderDeleteRequestConfiguration;
use Microsoft\Graph\Generated\Users\Item\Events\Item\EventItemRequestBuilderGetQueryParameters;
use Microsoft\Graph\Generated\Users\Item\Events\Item\EventItemRequestBuilderPatchRequestConfiguration;
use Microsoft\Graph\Generated\Users\Item\Events\Item\Instances\InstancesRequestBuilderGetQueryParameters;
use Microsoft\Kiota\Abstractions\RequestInformation;
use OpenTelemetry\SDK\Trace\Tracer;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symplicity\Outlook\Entities\Occurrence;
use Symplicity\Outlook\Entities\Reader;
use Symplicity\Outlook\Exception\ReadError;
use Symplicity\Outlook\Interfaces\CalendarInterface;
use Symplicity\Outlook\Interfaces\Entity\ReaderEntityInterface;
use Symplicity\Outlook\Interfaces\Utilities\CalendarView\CalendarViewParamsInterface;
use Symplicity\Outlook\Models\Event;
use Symplicity\Outlook\Utilities\CalendarView\GraphServiceCalendarView;
use Symplicity\Outlook\Utilities\CalendarView\PageIterator;
use Symplicity\Outlook\Utilities\EventView\GraphServiceEvent;

/**
 * @property-read GraphServiceEvent $graphService
 */
abstract class Calendar implements CalendarInterface
{
    use BearerAuthorizationTrait;
    use AuthorizationContextTrait;
    use RequestConfigurationTrait;

    // Maximum events allowed for graph batch api
    public const BATCH_BY = 20;

    protected LoggerInterface | null $logger;

    // If you want to use kiota/ms-graph telemetry, extend this class to declare a tracer
    public function __construct(private readonly string $clientId, private readonly string $clientSecret, private readonly string $token, array $args = [])
    {
        $this->logger = $args['logger'] ?? null;
    }

    public function __get(string $property)
    {
        if ($property === 'graphService') {
            $this->graphService = new GraphServiceEvent(
                $this->clientId,
                $this->clientSecret,
                $this->token
            );

            return $this->graphService;
        }

        return null;
    }

    /// MARK: Calendar Event Reads

    /** Pull all events from outlook in an iterative manner
     *  Set prefer headers like odata.maxpagesize and timezone to control the data received
     *  Calls saveEventLocal/deleteEventLocal methods
     * @throws ReadError
     */
    public function pull(CalendarViewParamsInterface $params, ?Closure $deltaLinkStore = null): void
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

            $requestConfiguration = $this->getCalendarViewRequestConfiguration($params);

            $events = $graphServiceClient
                ->client($params)
                ->me()
                ->calendarView()
                ->delta()
                ->get($requestConfiguration)
                ->wait();

            $this->iterateThrough(
                $events,
                $graphServiceClient,
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
     * @throws ReadError
     */
    public function getEventBy(string $id, EventItemRequestBuilderGetQueryParameters $params = null, ?Closure $beforeReturn = null): ?ReaderEntityInterface
    {
        try {
            $this->logger?->info('Getting event by id ...', [
                'params' => http_build_query($params)
            ]);

            $requestConfiguration = $this->getEventViewRequestConfiguration($params);

            $event = $this->graphService
                ->client($params)
                ->me()
                ->events()
                ->byEventId($id)
                ->get($requestConfiguration)
                ->wait();

            $entity = $this->getEntity($event);
            $beforeReturn?->call($this, $entity, $event);
            $this->logger?->info('Getting event by id complete ...', [
                'params' => http_build_query($params)
            ]);
            return $entity;
        } catch (\Exception $e) {
            $this->logger?->info('Getting event by id failed ...', [
                'params' => http_build_query($params),
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            throw new ReadError($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @throws ReadError
     */
    public function getEventInstances(string $id, InstancesRequestBuilderGetQueryParameters $params): void
    {
        try {
            $this->logger?->info('Getting instances of recurring event ...', [
                'params' => http_build_query($params)
            ]);

            $requestConfiguration = $this->getInstancesViewRequestConfiguration($params);

            $events = $this->graphService
                ->client()
                ->me()
                ->events()
                ->byEventId($id)
                ->instances()
                ->get($requestConfiguration)
                ->wait();

            foreach ($events->getValue() ?? [] as $event) {
                $this->logger?->info('Receiving event instance ...', [
                    'event_id' => $event->getId(),
                    'event_name' => $event->getSubject(),
                    'cal_id' => $event->getICalUId(),
                    'type' => $event->getType()?->value()
                ]);

                $entity = $this->getEntity($event);
                $this->saveEventLocal($entity);
            }

            $this->logger?->info('Getting event instances complete ...', [
                'params' => http_build_query($params)
            ]);
        } catch (\Exception $e) {
            $this->convertToReadableError($e);
        }
    }

    // MARK: Event writes

    /**
     * @throws \JsonException
     * @throws \Exception
     */
    public function push(array $params = []): void
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
        $batch = [];

        foreach ($chunks as $chunk) {
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
                        $deleteRequestConfiguration
                    );
                }
            }

            $responses = null;
            if (\count($batch)) {
                $batchRequestContent = new BatchRequestContent($batch);
                $batchRequestBuilder = new BatchRequestBuilder($this->graphService->getRequestAdapter());
                $responses = $batchRequestBuilder
                    ->postAsync($batchRequestContent, $batchRequestConfiguration)
                    ->wait();
            }

            $this->handleBatchResponse($responses);
        }
    }

    /**
     * @throws \Exception
     */
    public function upsert(Event $event): ?GraphEvent
    {
        $postRequestConfiguration = $this->getEventPostRequestConfiguration();
        $patchRequestConfiguration = $this->getEventPatchRequestConfiguration();

        $eventUpsertRequest = $this->prepareUpsertAsync(
            $event,
            $postRequestConfiguration,
            $patchRequestConfiguration
        );

        return $eventUpsertRequest->wait();
    }

    /**
     * @throws \Exception
     */
    public function delete(string $id): ResponseInterface
    {
        $requestConfiguration = new EventItemRequestBuilderDeleteRequestConfiguration();
        $requestConfiguration->headers = $this->getAuthorizationHeaders($this->token);

        return $this->graphService
            ->client()
            ->me()
            ->events()
            ->byEventId($id)
            ->delete($requestConfiguration)
            ->wait();
    }

    protected function getEntity(GraphEvent $event): ReaderEntityInterface
    {
        if ($event->getType()->value() === EventType::OCCURRENCE) {
            return $this->getOccurrenceReader()->hydrate($event);
        }

        return  $this->getReader()->hydrate($event);
    }

    protected function prepareBatchUpsert(Event $event, EventsRequestBuilderPostRequestConfiguration $postRequestConfiguration, EventItemRequestBuilderPatchRequestConfiguration $patchRequestConfiguration, EventItemRequestBuilderDeleteRequestConfiguration $deleteRequestConfiguration): RequestInformation
    {
        $me = $this->graphService
            ->client()
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

        return $request;
    }

    /**
     * @throws \Exception
     */
    protected function prepareUpsertAsync(Event $event, EventsRequestBuilderPostRequestConfiguration $postRequestConfiguration, EventItemRequestBuilderPatchRequestConfiguration $patchRequestConfiguration): Promise
    {
        $me = $this->graphService
            ->client()
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
    protected function iterateThrough(DeltaGetResponse $events, GraphServiceCalendarView $graphServiceClient, DeltaRequestBuilderGetRequestConfiguration $requestConfiguration, ?Closure $deltaLinkStore = null): void
    {
        $iterator = new PageIterator(
            $events,
            $graphServiceClient->getRequestAdapter()
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
    private function convertToReadableError(\Exception $e)
    {
        $message = null;
        if ($e instanceof ODataError) {
            /** @var MainError $errorInfo */
            $errorInfo = $e->getBackingStore()->get('error');
            $code = 0;
            $localizedDescription = $errorInfo->getMessage();
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

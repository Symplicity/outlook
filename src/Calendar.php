<?php

declare(strict_types=1);

namespace Symplicity\Outlook;

use Closure;
use Http\Promise\Promise;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Microsoft\Graph\BatchRequestBuilder;
use Microsoft\Graph\Core\Requests\BatchRequestContent;
use Microsoft\Graph\Generated\Groups\Item\Events\EventsRequestBuilderGetQueryParameters;
use Microsoft\Graph\Generated\Models\Event as GraphEvent;
use Microsoft\Graph\Generated\Models\EventType;
use Microsoft\Graph\Generated\Users\Item\CalendarView\Delta\DeltaGetResponse;
use Microsoft\Graph\Generated\Users\Item\CalendarView\Delta\DeltaRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\Generated\Users\Item\Events\EventsRequestBuilderPostRequestConfiguration;
use Microsoft\Graph\Generated\Users\Item\Events\Item\EventItemRequestBuilderDeleteRequestConfiguration;
use Microsoft\Graph\Generated\Users\Item\Events\Item\EventItemRequestBuilderPatchRequestConfiguration;
use Microsoft\Graph\Generated\Users\Item\Events\Item\Instances\InstancesRequestBuilderGetQueryParameters;
use Microsoft\Kiota\Abstractions\RequestInformation;
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

    public function pull(CalendarViewParamsInterface $params, ?Closure $deltaLinkStore = null): void
    {
        try {
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
        } catch (\Exception $e) {
            throw new ReadError($e->getMessage(), $e->getCode());
        }
    }

    /**
     *
     * @throws ReadError
     */
    public function getEventBy(string $id, ?EventsRequestBuilderGetQueryParameters $params = null, ?Closure $beforeReturn = null): ?ReaderEntityInterface
    {
        try {
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
            return $entity;
        } catch (\Exception $e) {
            throw new ReadError($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @throws ReadError
     */
    public function getEventInstances(string $id, InstancesRequestBuilderGetQueryParameters $params): void
    {
        try {
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
                $entity = $this->getEntity($event);
                $this->saveEventLocal($entity);
            }
        } catch (\Exception $e) {
            throw new ReadError($e->getMessage(), $e->getCode());
        }
    }

    // MARK: Event writes

    /**
     * @throws \JsonException
     * @throws \Exception
     */
    public function push(array $params = []): void
    {
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
    public function upsert(Event $event): ?Event
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
                $request = $me->events()->byEventId($eventId)->toDeleteRequestInformation($deleteRequestConfiguration);
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

            $additionalData = $event->getAdditionalData();
            if (isset($additionalData['@removed']['reason'])
                && $additionalData['@removed']['reason'] === 'deleted') {
                $this->deleteEventLocal($event->getId());
                return true;
            }

            $this->saveEventLocal($this->getEntity($event));
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
}

<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Interfaces;

use Microsoft\Graph\Core\Requests\BatchResponseContent;
use Microsoft\Graph\Generated\Groups\Item\Events\EventsRequestBuilderGetQueryParameters;
use Microsoft\Graph\Generated\Users\Item\Events\Item\Instances\InstancesRequestBuilderGetQueryParameters;
use Symplicity\Outlook\Exception\ReadError;
use Symplicity\Outlook\Interfaces\Entity\ReaderEntityInterface;
use Symplicity\Outlook\Interfaces\Utilities\CalendarView\CalendarViewParamsInterface;
use Symplicity\Outlook\Models\Event;

interface CalendarInterface
{
    /**
     * Once event has been received from outlook, this method will be called so that it can be saved to a persistant storage.
     * @param ReaderEntityInterface $reader
     * @return void
     */
    public function saveEventLocal(ReaderEntityInterface $reader): void;

    /**
     * When event is deleted, this method will be called.
     * @param string $eventId
     * @return void
     */
    public function deleteEventLocal(string $eventId): void;

    /**
     * Gets all the events that needs to go to Outlook
     * @return array<Event>
     */
    public function getLocalEvents(): array;

    /**
     * Passed by handler fulfillment on batch response
     * @param BatchResponseContent|null $responses
     */
    public function handleBatchResponse(?BatchResponseContent $responses): void;

    /**
     * Passed by Guzzle single async requestor
     * @param $failedToWrite
     */
    public function handleResponse(array $failedToWrite = []): void;

    /**
     * Method to get & process a single event
     * @param string $id
     * @param ?EventsRequestBuilderGetQueryParameters $params
     * @return ReaderEntityInterface | null
     * @throws ReadError
     */
    public function getEventBy(string $id, ?EventsRequestBuilderGetQueryParameters $params = null): ?ReaderEntityInterface;

    /**
     * Method to get all instances of a series master
     * @param string $id
     * @param InstancesRequestBuilderGetQueryParameters $params
     */
    public function getEventInstances(string $id, InstancesRequestBuilderGetQueryParameters $params): void;

    /**
     * Individual push event handler method, use this if you dont want to use sync
     * @param array $params
     */
    public function push(array $params = []): void;

    /**
     * Individual pull event handler method, use this if you dont want to use sync
     * @param CalendarViewParamsInterface $params
     * @throws ReadError
     */
    public function pull(CalendarViewParamsInterface $params): void;
}

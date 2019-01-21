<?php

namespace Symplicity\Outlook\Interfaces;

use Symplicity\Outlook\Interfaces\Entity\ReaderEntityInterface;

interface CalendarInterface
{
    public function isBatchRequest(): CalendarInterface;

    /**
     * Once event has been accessed from outlook, use the method to save event to your db
     * @param ReaderEntityInterface $reader
     * @return void
     */
    public function saveEventLocal(ReaderEntityInterface $reader) : void;

    /**
     * When event is deleted, this method will be called.
     * @param ReaderEntityInterface $event
     * @return void
     */
    public function deleteEventLocal(ReaderEntityInterface $event) : void;

    /**
     * Gets all the events to be sent to outlook
     * @return array Return array of Write Entities, example [Write(....), Write(....)]
     */
    public function getLocalEvents() : array;

    /**
     * Passed by handler fulfillment
     * @param array $responses
     */
    public function handlePoolResponses(array $responses = []) : void;

    /**
     * Passed by Guzzle single async requestor
     * @param $failedToWrite
     */
    public function handleResponse(array $failedToWrite = []) : void;
}

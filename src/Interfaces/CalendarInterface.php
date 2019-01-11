<?php

namespace Symplicity\Outlook\Interfaces;

use Symplicity\Outlook\Interfaces\Entity\ReaderEntityInterface;

interface CalendarInterface
{
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
    public function getEventsLocal() : array;

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

    /**
     * Get user associated with the sync
     * @return string
     */
    public function getUserId() : string;
}

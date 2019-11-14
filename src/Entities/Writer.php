<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Entities;

use Symplicity\Outlook\Interfaces\Entity\LocationInterface;
use Symplicity\Outlook\Interfaces\Entity\ODateTimeInterface;
use Symplicity\Outlook\Interfaces\Entity\ResponseBodyInterface;
use Symplicity\Outlook\Interfaces\Entity\WriterInterface;
use Symplicity\Outlook\Utilities\RequestType;
use Symplicity\Outlook\Utilities\SensitivityType;

/**
 * Class Writer
 * Implementation of class to handle writes to outlook calendar
 * @package Symplicity\Outlook\Entities
 */
class Writer implements WriterInterface, \JsonSerializable
{
    protected const DefaultPostRequest = '/Me/events';

    protected $method;
    protected $guid;
    protected $id;
    protected $subject;
    protected $isCancelled;
    protected $isAllDay = false;
    protected $url;
    protected $internalEventType;
    protected $sensitivity;

    /** @var ResponseBodyInterface */
    protected $body;

    /** @var ODateTimeInterface */
    protected $startDate;

    /** @var ODateTimeInterface */
    protected $endDate;

    /** @var array */
    protected $recurrence;

    /** @var LocationInterface */
    protected $location;

    public function jsonSerialize() : array
    {
        return [
            'Subject' => $this->subject,
            'Body' => [
                'ContentType' => $this->body->getContentType(),
                'Content' => $this->body->getContent()
            ],
            'Start' => $this->startDate->toArray(),
            'End' => $this->endDate->toArray(),
            'Location' => [
                'DisplayName' => $this->location instanceof LocationInterface ? $this->location->getLocationDisplayName() : null
            ],
            'Sensitivity' => $this->getSensitivity(),
            'Recurrence' => $this->recurrence,
            'IsAllDay' => $this->isAllDay
        ];
    }

    // Accessors
    public function getMethod() : string
    {
        return $this->method instanceof RequestType ? $this->method->getValue() : RequestType::Get;
    }

    public function getUrl() : string
    {
        $this->url = static::DefaultPostRequest;
        if (in_array($this->method, [RequestType::Patch, RequestType::Put, RequestType::Delete])) {
            $this->url = $this->url . '/' . $this->guid;
        }

        return $this->url;
    }

    public function __toString() : string
    {
        return $this->guid ?? $this->id;
    }

    // Fluent Mutator
    public function setGuid(?string $guid): WriterInterface
    {
        $this->guid = $guid;
        $this->method(new RequestType(RequestType::Patch));
        return $this;
    }

    /**
     * Internal id
     * @param null|string $id
     * @return WriterInterface
     */
    public function setId(?string $id): WriterInterface
    {
        $this->id = $id;
        return $this;
    }

    public function setSubject(string $subject): WriterInterface
    {
        $this->subject = $subject;
        return $this;
    }

    public function setBody(ResponseBodyInterface $body): WriterInterface
    {
        $this->body = $body;
        return $this;
    }

    public function setStartDate(ODateTimeInterface $startDate): WriterInterface
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function setEndDate(ODateTimeInterface $endDate): WriterInterface
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function cancel(): WriterInterface
    {
        $this->isCancelled = true;
        return $this;
    }

    public function setIsAllDay(bool $isAllDay): WriterInterface
    {
        $this->isAllDay = $isAllDay;
        return $this;
    }

    public function setRecurrence(array $recurrence): WriterInterface
    {
        $this->recurrence = $recurrence;
        return $this;
    }

    public function setLocation(LocationInterface $location): WriterInterface
    {
        $this->location = $location;
        return $this;
    }

    public function setInternalEventType(string $eventType): WriterInterface
    {
        $this->internalEventType = $eventType;
        return $this;
    }

    public function setSensitivity(string $sensitivity): WriterInterface
    {
        $this->sensitivity = $sensitivity;
        return $this;
    }

    public function method(RequestType $requestType) : WriterInterface
    {
        $this->method = $requestType;
        return $this;
    }

    public function getId() : ?string
    {
        return $this->id;
    }

    public function getInternalEventType() : ?string
    {
        return $this->internalEventType;
    }

    public function getSensitivity() : string
    {
        return $this->sensitivity ?? SensitivityType::Personal;
    }

    public function isCancelled() : bool
    {
        return $this->isCancelled;
    }

    public function hasOutlookId() : bool
    {
        return isset($this->guid);
    }
}

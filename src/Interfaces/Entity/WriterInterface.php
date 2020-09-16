<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Interfaces\Entity;

use JsonSerializable;
use Symplicity\Outlook\Utilities\RequestType;

interface WriterInterface extends JsonSerializable
{
    // Fluent Mutator
    public function setGuid(?string $guid): WriterInterface;
    public function setId(?string $id): WriterInterface;
    public function setSubject(string $subject): WriterInterface;
    public function setBody(ResponseBodyInterface $body): WriterInterface;
    public function setStartDate(ODateTimeInterface $startDate): WriterInterface;
    public function setEndDate(ODateTimeInterface $endDate): WriterInterface;
    public function cancel(): WriterInterface;
    public function setIsAllDay(bool $isAllDay): WriterInterface;
    public function setRecurrence(array $recurrence): WriterInterface;
    public function setLocation(LocationInterface $location): WriterInterface;
    public function setInternalEventType(string $eventType): WriterInterface;
    public function setSensitivity(string $sensitivity): WriterInterface;
    public function method(RequestType $requestType) : WriterInterface;
    public function setExtensions(ExtensionWriterInterface $extensions);

    // Accessor
    public function getMethod() : string;
    public function getUrl() : string;
    public function getInternalEventType() : ?string;
    public function getSensitivity() : string;
    public function isCancelled() : bool;
    public function hasOutlookId() : bool;
    public function getRequestType(): RequestType;
}

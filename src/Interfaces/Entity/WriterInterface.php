<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Interfaces\Entity;

use Symplicity\Outlook\Utilities\RequestType;

interface WriterInterface
{
    // Fluent Mutator
    public function setGuid(?string $guid): WriterInterface;
    public function setId(?string $id): WriterInterface;
    public function setSubject(string $subject): WriterInterface;
    public function setBody(ResponseBodyInterface $body): WriterInterface;
    public function setStartDate(ODateTimeInterface $startDate): WriterInterface;
    public function setEndDate(ODateTimeInterface $endDate): WriterInterface;
    public function setCancelled(bool $cancelled): WriterInterface;
    public function setIsAllDay(bool $isAllDay): WriterInterface;
    public function setRecurrence(RecurrenceEntityInterface $recurrence): WriterInterface;
    public function setLocation(LocationInterface $location): WriterInterface;
    public function method(RequestType $requestType) : WriterInterface;

    // Accessor
    public function getMethod() : ?RequestType;
    public function url() : string;
}

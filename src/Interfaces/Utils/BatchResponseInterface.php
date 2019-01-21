<?php

interface BatchResponseInterface
{
    public function getStatusCode(): int;
    public function getStatus() : ?string;
    public function getReason() : ?string;
    public function getResponse() : ?ReaderEntityInterface;
}

<?php

namespace Symplicity\Outlook\Interfaces\Entity;

interface TokenInterface
{
    public function getEmailAddress() : string;
    public function getDisplayName() : string;
    public function getType() : string;
    public function getAccessToken() : string;
    public function getRefreshToken() : string;
    public function getExpiresIn() : int;
    public function getIdToken() : string;
    public function tokenReceivedOn() : \DateTimeInterface;
}

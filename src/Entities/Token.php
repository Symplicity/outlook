<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Entities;

use Symplicity\Outlook\Interfaces\Entity\TokenInterface;

class Token implements TokenInterface
{
    protected $emailAddress;
    protected $displayName;
    protected $type;
    protected $accessToken;
    protected $refreshToken;
    protected $expiresIn;
    protected $idToken;
    protected $tokenReceivedOn;

    public function __construct(array $data)
    {
        if (isset($data['userInfo'])) {
            $this->setEmailAddress($data['userInfo']['EmailAddress']);
            $this->setDisplayName($data['userInfo']['DisplayName']);
        }
        $this->setType($data['token_type']);
        $this->setAccessToken($data['access_token']);
        $this->setRefreshToken($data['refresh_token']);
        $this->setExpiresIn($data['expires_in']);
        $this->setIdToken($data['id_token']);
        $this->setTokenReceivedOn();
    }

    // Mutator
    private function setEmailAddress(?string $emailAddress) : void
    {
        $this->emailAddress = $emailAddress;
    }

    private function setDisplayName(?string $displayName) : void
    {
        $this->displayName = $displayName;
    }

    private function setType(string $type) : void
    {
        $this->type = $type;
    }

    private function setAccessToken(string $accessToken) : void
    {
        $this->accessToken = $accessToken;
    }

    private function setRefreshToken(string $refreshToken) : void
    {
        $this->refreshToken = $refreshToken;
    }

    private function setExpiresIn(int $expiresIn) : void
    {
        $this->expiresIn = $expiresIn;
    }

    public function setIdToken(string $idToken): void
    {
        $this->idToken = $idToken;
    }

    public function setTokenReceivedOn(): void
    {
        $this->tokenReceivedOn = new \DateTimeImmutable('now');
    }

    // Mark Accessors
    public function getEmailAddress() : ?string
    {
        return $this->emailAddress;
    }

    public function getDisplayName() : ?string
    {
        return $this->displayName;
    }

    public function getType() : string
    {
        return $this->type;
    }

    public function getAccessToken() : string
    {
        return $this->accessToken;
    }

    public function getRefreshToken() : string
    {
        return $this->refreshToken;
    }

    public function getExpiresIn() : int
    {
        return $this->expiresIn;
    }

    public function getIdToken() : string
    {
        return $this->idToken;
    }

    public function tokenReceivedOn() : \DateTimeInterface
    {
        return $this->tokenReceivedOn;
    }

    public function __toString() : string
    {
        return $this->getDisplayName();
    }
}

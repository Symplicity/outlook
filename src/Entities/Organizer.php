<?php

declare(strict_types=1);

namespace Symplicity\Outlook\Entities;

class Organizer
{
    public $name;
    public $email;

    public function __construct(array $data)
    {
        $this->name = $data['EmailAddress']['Name'];
        $this->email = $data['EmailAddress']['Address'];
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function getEmail() : string
    {
        return $this->email;
    }
}

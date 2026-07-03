<?php

namespace App\Domain\CRM\Actions;

use App\Domain\CRM\Models\Contact;
use App\Domain\CRM\Services\ContactService;

class CreateContact
{
    protected ContactService $service;

    public function __construct(ContactService $service)
    {
        $this->service = $service;
    }

    public function execute(array $data): Contact
    {
        return $this->service->createContact($data);
    }
}

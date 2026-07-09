<?php

namespace App\Domain\Marketplace\Services;

class MpesaGateway
{
    public function initiateStkPush(array $data)
    {
        // Production implementation: Call Mpesa API
        return ['status' => 'success', 'transaction_id' => uniqid()];
    }

    public function verifyTransaction(string $transactionId)
    {
        // Production implementation: Verify with Mpesa
        return ['status' => 'paid'];
    }
}

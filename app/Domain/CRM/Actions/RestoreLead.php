<?php

namespace App\Domain\CRM\Actions;

use App\Domain\CRM\Models\Lead;

class RestoreLead
{
    public function execute(string $id): bool
    {
        $lead = Lead::onlyTrashed()->find($id);
        if ($lead) {
            return (bool) $lead->restore();
        }
        return false;
    }
}

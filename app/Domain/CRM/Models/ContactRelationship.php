<?php

namespace App\Domain\CRM\Models;

use App\Traits\HasUuidV7;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactRelationship extends Model
{
    use HasUuidV7, SoftDeletes;

    protected $table = 'crm_contact_relationships';

    protected $fillable = [
        'organization_id',
        'contact_id',
        'related_contact_id',
        'type', // manager, assistant, colleague, executive, decision_maker, influencer, technical_contact, legal_contact, finance_contact, emergency_contact
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function relatedContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'related_contact_id');
    }

    /**
     * Detects if creating a relationship from $contactId to $relatedContactId
     * would introduce a circular reference.
     */
    public static function wouldCreateCircularGraph(string $contactId, string $relatedContactId, array $visited = []): bool
    {
        if ($contactId === $relatedContactId) {
            return true;
        }

        if (in_array($relatedContactId, $visited)) {
            return false;
        }

        $visited[] = $relatedContactId;

        // Get direct relationships where $relatedContactId is the source
        $children = self::where('contact_id', $relatedContactId)->pluck('related_contact_id')->toArray();

        foreach ($children as $childId) {
            if ($childId === $contactId) {
                return true;
            }
            if (self::wouldCreateCircularGraph($contactId, $childId, $visited)) {
                return true;
            }
        }

        return false;
    }
}

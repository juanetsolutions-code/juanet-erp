<?php

namespace App\Repositories\Eloquent;

use App\Models\Setting;
use App\Models\FeatureFlag;
use App\Models\BetaEnrollment;
use App\Repositories\SettingsRepositoryInterface;
use Illuminate\Support\Collection;

class SettingsRepository implements SettingsRepositoryInterface
{
    /**
     * Retrieve a setting by its group, owner ID, and key.
     */
    public function get(string $group, ?string $ownerId, string $key): ?Setting
    {
        return Setting::where('group', $group)
            ->where('owner_id', $ownerId)
            ->where('key', $key)
            ->first();
    }

    /**
     * Create or update a setting.
     */
    public function set(string $group, ?string $ownerId, string $key, mixed $value, string $type = 'string', bool $encrypt = false): Setting
    {
        $setting = Setting::firstOrNew([
            'group' => $group,
            'owner_id' => $ownerId,
            'key' => $key,
        ]);

        $setting->setCastValue($value, $type, $encrypt);
        $setting->save();

        return $setting;
    }

    /**
     * Delete a setting.
     */
    public function delete(string $group, ?string $ownerId, string $key): bool
    {
        return (bool) Setting::where('group', $group)
            ->where('owner_id', $ownerId)
            ->where('key', $key)
            ->delete();
    }

    /**
     * List settings for a given group and owner.
     */
    public function listByGroup(string $group, ?string $ownerId): Collection
    {
        return Setting::where('group', $group)
            ->where('owner_id', $ownerId)
            ->get();
    }

    /**
     * Retrieve a feature flag by key.
     */
    public function getFeatureFlag(string $key): ?FeatureFlag
    {
        return FeatureFlag::where('key', $key)->first();
    }

    /**
     * Create or update a feature flag.
     */
    public function setFeatureFlag(string $key, bool $isEnabled, array $rules = [], bool $isBeta = false, ?string $description = null): FeatureFlag
    {
        $flag = FeatureFlag::firstOrNew(['key' => $key]);
        $flag->is_enabled = $isEnabled;
        $flag->rules = $rules;
        $flag->is_beta = $isBeta;
        if ($description !== null) {
            $flag->description = $description;
        }
        $flag->save();

        return $flag;
    }

    /**
     * Delete a feature flag.
     */
    public function deleteFeatureFlag(string $key): bool
    {
        return (bool) FeatureFlag::where('key', $key)->delete();
    }

    /**
     * List all feature flags.
     */
    public function listFeatureFlags(): Collection
    {
        return FeatureFlag::all();
    }

    /**
     * Enroll a tenant/user into a beta feature flag.
     */
    public function enrollInBeta(string $featureKey, ?string $orgId, ?string $userId): BetaEnrollment
    {
        return BetaEnrollment::firstOrCreate([
            'feature_flag_key' => $featureKey,
            'organization_id' => $orgId,
            'user_id' => $userId,
        ]);
    }

    /**
     * Unenroll a tenant/user from a beta feature flag.
     */
    public function unenrollFromBeta(string $featureKey, ?string $orgId, ?string $userId): bool
    {
        return (bool) BetaEnrollment::where('feature_flag_key', $featureKey)
            ->where('organization_id', $orgId)
            ->where('user_id', $userId)
            ->delete();
    }

    /**
     * Check if enrollment exists.
     */
    public function isEnrolledInBeta(string $featureKey, ?string $orgId, ?string $userId): bool
    {
        $query = BetaEnrollment::where('feature_flag_key', $featureKey);

        if ($orgId && $userId) {
            $query->where(function ($q) use ($orgId, $userId) {
                $q->where('organization_id', $orgId)
                  ->orWhere('user_id', $userId);
            });
        } elseif ($orgId) {
            $query->where('organization_id', $orgId);
        } elseif ($userId) {
            $query->where('user_id', $userId);
        } else {
            return false;
        }

        return $query->exists();
    }
}

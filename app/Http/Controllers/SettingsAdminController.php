<?php

namespace App\Http\Controllers;

use App\Repositories\SettingsRepositoryInterface;
use App\Services\Configuration\ConfigurationServiceInterface;
use App\Services\TenantContext;
use App\Models\Organization;
use App\Models\User;
use App\Models\Setting;
use App\Models\FeatureFlag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingsAdminController extends Controller
{
    protected ConfigurationServiceInterface $configService;
    protected SettingsRepositoryInterface $repository;
    protected TenantContext $tenantContext;

    public function __construct(
        ConfigurationServiceInterface $configService,
        SettingsRepositoryInterface $repository,
        TenantContext $tenantContext
    ) {
        $this->configService = $configService;
        $this->repository = $repository;
        $this->tenantContext = $tenantContext;
    }

    /**
     * Show the configuration settings & feature flag console.
     */
    public function index()
    {
        $user = Auth::user();
        $org = $this->tenantContext->getTenant();
        $orgId = $org ? $org->id : null;

        // Fetch settings by group
        $platformSettings = $this->repository->listByGroup('platform', null);
        $orgSettings = $orgId ? $this->repository->listByGroup('organization', $orgId) : collect();
        $userSettings = $this->repository->listByGroup('user', $user->id);

        // Fetch feature flags
        $featureFlags = $this->repository->listFeatureFlags();

        // Get lists of Orgs and Users for targeting/beta enrollment select boxes
        $organizations = Organization::all();
        $users = User::all();

        return view('settings.index', [
            'platformSettings' => $platformSettings,
            'orgSettings' => $orgSettings,
            'userSettings' => $userSettings,
            'featureFlags' => $featureFlags,
            'organizations' => $organizations,
            'users' => $users,
            'currentOrg' => $org,
        ]);
    }

    /**
     * Store or update a setting.
     */
    public function updateSetting(Request $request)
    {
        $request->validate([
            'key' => 'required|string|max:255',
            'value' => 'nullable|string',
            'type' => 'required|string|in:string,boolean,json,integer,float',
            'group' => 'required|string|in:platform,organization,user',
            'is_encrypted' => 'nullable|boolean',
        ]);

        $group = $request->input('group');
        $key = $request->input('key');
        $type = $request->input('type');
        $encrypt = $request->has('is_encrypted');

        // Resolve value according to type
        $rawValue = $request->input('value');
        $value = $rawValue;
        if ($type === 'boolean') {
            $value = filter_var($rawValue, FILTER_VALIDATE_BOOLEAN);
        } elseif ($type === 'json') {
            $value = json_decode($rawValue, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return redirect()->back()
                    ->withInput()
                    ->with('toast_message', 'Invalid JSON syntax provided.')
                    ->with('toast_type', 'error');
            }
        } elseif ($type === 'integer') {
            $value = (int) $rawValue;
        } elseif ($type === 'float') {
            $value = (float) $rawValue;
        }

        // Determine owner ID based on group
        $ownerId = null;
        if ($group === 'organization') {
            $org = $this->tenantContext->getTenant();
            if (!$org) {
                return redirect()->back()
                    ->with('toast_message', 'No active organization context found.')
                    ->with('toast_type', 'error');
            }
            $ownerId = $org->id;
        } elseif ($group === 'user') {
            $ownerId = Auth::id();
        }

        $this->configService->set($group, $ownerId, $key, $value, $type, $encrypt);

        return redirect()->back()
            ->with('toast_message', "Successfully saved setting '{$key}' in group '{$group}'.")
            ->with('toast_type', 'success');
    }

    /**
     * Delete a setting.
     */
    public function deleteSetting(Request $request)
    {
        $request->validate([
            'key' => 'required|string',
            'group' => 'required|string|in:platform,organization,user',
        ]);

        $group = $request->input('group');
        $key = $request->input('key');

        $ownerId = null;
        if ($group === 'organization') {
            $org = $this->tenantContext->getTenant();
            $ownerId = $org ? $org->id : null;
        } elseif ($group === 'user') {
            $ownerId = Auth::id();
        }

        $this->configService->delete($group, $ownerId, $key);

        return redirect()->back()
            ->with('toast_message', "Deleted setting '{$key}'.")
            ->with('toast_type', 'success');
    }

    /**
     * Create or update a feature flag.
     */
    public function updateFeatureFlag(Request $request)
    {
        $request->validate([
            'key' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_enabled' => 'nullable|boolean',
            'is_beta' => 'nullable|boolean',
            'rollout' => 'nullable|integer|between:0,100',
            'users_list' => 'nullable|array',
            'orgs_list' => 'nullable|array',
        ]);

        $key = $request->input('key');
        $isEnabled = $request->has('is_enabled');
        $isBeta = $request->has('is_beta');
        $description = $request->input('description');

        $rules = [];
        if ($request->filled('rollout')) {
            $rules['rollout'] = (int) $request->input('rollout');
        }
        if ($request->has('users_list') && !empty($request->input('users_list'))) {
            $rules['users'] = $request->input('users_list');
        }
        if ($request->has('orgs_list') && !empty($request->input('orgs_list'))) {
            $rules['organizations'] = $request->input('orgs_list');
        }

        $this->configService->setFeatureFlag($key, $isEnabled, $rules, $isBeta, $description);

        return redirect()->back()
            ->with('toast_message', "Successfully updated feature flag '{$key}'.")
            ->with('toast_type', 'success');
    }

    /**
     * Delete a feature flag.
     */
    public function deleteFeatureFlag(Request $request)
    {
        $request->validate(['key' => 'required|string']);
        $key = $request->input('key');

        $this->repository->deleteFeatureFlag($key);
        $this->configService->delete('platform', null, "feature:{$key}"); // bust cache if any

        return redirect()->back()
            ->with('toast_message', "Deleted feature flag '{$key}'.")
            ->with('toast_type', 'success');
    }

    /**
     * Enroll a participant in a Beta Feature.
     */
    public function enrollBeta(Request $request)
    {
        $request->validate([
            'feature_flag_key' => 'required|string',
            'target_type' => 'required|string|in:organization,user',
            'target_id' => 'required|string',
        ]);

        $key = $request->input('feature_flag_key');
        $type = $request->input('target_type');
        $targetId = $request->input('target_id');

        $orgId = ($type === 'organization') ? $targetId : null;
        $userId = ($type === 'user') ? $targetId : null;

        $this->configService->enrollInBeta($key, $orgId, $userId);

        return redirect()->back()
            ->with('toast_message', "Enrolled target in beta flag '{$key}'.")
            ->with('toast_type', 'success');
    }

    /**
     * Unenroll a participant from a Beta Feature.
     */
    public function unenrollBeta(Request $request)
    {
        $request->validate([
            'feature_flag_key' => 'required|string',
            'target_type' => 'required|string|in:organization,user',
            'target_id' => 'required|string',
        ]);

        $key = $request->input('feature_flag_key');
        $type = $request->input('target_type');
        $targetId = $request->input('target_id');

        $orgId = ($type === 'organization') ? $targetId : null;
        $userId = ($type === 'user') ? $targetId : null;

        $this->configService->unenrollFromBeta($key, $orgId, $userId);

        return redirect()->back()
            ->with('toast_message', "Unenrolled target from beta flag '{$key}'.")
            ->with('toast_type', 'success');
    }
}

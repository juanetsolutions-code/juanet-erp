<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\FeatureFlag;
use App\Models\BetaEnrollment;
use App\Models\User;
use App\Models\Organization;
use App\Repositories\SettingsRepositoryInterface;
use App\Services\Configuration\ConfigurationServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support5\Facades\Cache;

uses(RefreshDatabase::class);

test('settings repository can store, retrieve, and delete values', function () {
    $repo = app(SettingsRepositoryInterface::class);

    // 1. Plaintext String
    $setting = $repo->set('platform', null, 'app.name', 'JUANET Enterprise', 'string');
    expect($setting->getCastValue())->toBe('JUANET Enterprise');

    $retrieved = $repo->get('platform', null, 'app.name');
    expect($retrieved)->not->toBeNull();
    expect($retrieved->getCastValue())->toBe('JUANET Enterprise');

    // 2. Encrypted Integer
    $settingSec = $repo->set('organization', 'org_123', 'mail.port', 465, 'integer', true);
    expect($settingSec->is_encrypted)->toBeTrue();
    expect($settingSec->getCastValue())->toBe(465);

    // Assert actual value in DB is indeed encrypted
    $dbRecord = Setting::where('key', 'mail.port')->first();
    expect($dbRecord->value)->not->toBe('465'); // Must be ciphertext
    expect(Crypt::decryptString($dbRecord->value))->toBe('465');

    // 3. JSON Array
    $settingJson = $repo->set('user', 'user_abc', 'notifications.channels', ['email', 'sms'], 'json');
    expect($settingJson->getCastValue())->toBe(['email', 'sms']);

    // 4. Delete
    $repo->delete('user', 'user_abc', 'notifications.channels');
    expect($repo->get('user', 'user_abc', 'notifications.channels'))->toBeNull();
});

test('configuration service resolves inheritance chain correctly', function () {
    $service = app(ConfigurationServiceInterface::class);

    // Scenario setup:
    // Platform Default: max_upload_size = 50
    // Org Override (for org_1): max_upload_size = 100
    // User Override (for user_1): max_upload_size = 200

    $service->set('platform', null, 'max_upload_size', 50, 'integer');
    $service->set('organization', 'org_1', 'max_upload_size', 100, 'integer');
    $service->set('user', 'user_1', 'max_upload_size', 200, 'integer');

    // 1. Guest / fallback context
    expect($service->get('max_upload_size', 10))->toBe(50);

    // 2. Org context only
    expect($service->get('max_upload_size', 10, 'org_1'))->toBe(100);

    // 3. User & Org context combined (User overrides Org)
    expect($service->get('max_upload_size', 10, 'org_1', 'user_1'))->toBe(200);

    // 4. Fallback when not found anywhere
    expect($service->get('non_existent_key', 99))->toBe(99);
});

test('configuration service respects environment overrides', function () {
    $service = app(ConfigurationServiceInterface::class);

    // Platform Default
    $service->set('platform', null, 'smtp.host', 'default.smtp.com', 'string');

    // Assert default is returned first
    expect($service->get('smtp.host'))->toBe('default.smtp.com');

    // Set environment variable override
    putenv('SETTING_SMTP_HOST=env.smtp.org');

    // Assert environment variable takes highest precedence
    expect($service->get('smtp.host'))->toBe('env.smtp.org');

    // Clean up
    putenv('SETTING_SMTP_HOST');
});

test('feature flags can be created and evaluated with complex targeting rules', function () {
    $service = app(ConfigurationServiceInterface::class);

    // Create a feature flag with targeting rules
    $service->setFeatureFlag('billing.m_pesa_gateway', true, [
        'users' => ['user_premium_1', 'user_premium_2'],
        'organizations' => ['org_beta'],
    ]);

    // 1. Enabled globally but requires targeting rules
    // Should be false if user & org don't match
    expect($service->isEnabled('billing.m_pesa_gateway', 'org_standard', 'user_standard'))->toBeFalse();

    // Should be true if matching user ID
    expect($service->isEnabled('billing.m_pesa_gateway', 'org_standard', 'user_premium_1'))->toBeTrue();

    // Should be true if matching organization ID
    expect($service->isEnabled('billing.m_pesa_gateway', 'org_beta', 'user_standard'))->toBeTrue();
});

test('feature flags support deterministic rollout percentages', function () {
    $service = app(ConfigurationServiceInterface::class);

    // Feature with 50% rollout
    $service->setFeatureFlag('ui.new_theme_v2', true, [
        'rollout' => 50,
    ]);

    // Evaluate for multiple seeds. Since it is deterministic (CRC32), some will be true, some false.
    // Ensure we don't crash, and outcomes are persistent per seed.
    $outcome1_first = $service->isEnabled('ui.new_theme_v2', 'tenant_x');
    $outcome1_second = $service->isEnabled('ui.new_theme_v2', 'tenant_x');

    expect($outcome1_first)->toBe($outcome1_second); // Must be deterministic

    $outcome2_first = $service->isEnabled('ui.new_theme_v2', 'tenant_y');
    $outcome2_second = $service->isEnabled('ui.new_theme_v2', 'tenant_y');

    expect($outcome2_first)->toBe($outcome2_second); // Must be deterministic
});

test('feature flags support beta gating and participant enrollment', function () {
    $service = app(ConfigurationServiceInterface::class);

    // Set a beta feature flag (is_beta = true)
    $service->setFeatureFlag('ai.copilot_integration', true, [], true);

    // Unenrolled participants should be denied access
    expect($service->isEnabled('ai.copilot_integration', 'org_alpha', 'user_alpha'))->toBeFalse();

    // Enroll organization
    $service->enrollInBeta('ai.copilot_integration', 'org_alpha', null);

    // Enrolled org participant gets access
    expect($service->isEnabled('ai.copilot_integration', 'org_alpha', 'user_alpha'))->toBeTrue();

    // Unenrolled user still denied if no org context matching
    expect($service->isEnabled('ai.copilot_integration', 'org_beta', 'user_beta'))->toBeFalse();

    // Enroll specific user
    $service->enrollInBeta('ai.copilot_integration', null, 'user_beta');

    // User gets access
    expect($service->isEnabled('ai.copilot_integration', 'org_beta', 'user_beta'))->toBeTrue();

    // Unenroll org
    $service->unenrollFromBeta('ai.copilot_integration', 'org_alpha', null);
    expect($service->isEnabled('ai.copilot_integration', 'org_alpha', 'user_alpha'))->toBeFalse();
});

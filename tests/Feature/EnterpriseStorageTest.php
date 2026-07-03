<?php

use App\Models\User;
use App\Models\Organization;
use App\Models\StoredFile;
use App\Services\UploadServiceInterface;
use App\Services\DownloadServiceInterface;
use App\Services\SignedUrlServiceInterface;
use App\Services\FileValidatorInterface;
use App\Services\VirusScannerInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('file validator correctly classifies mimes and categorizes them', function () {
    $validator = app(FileValidatorInterface::class);

    expect($validator->determineCategory('image/png'))->toBe('image');
    expect($validator->determineCategory('image/jpeg'))->toBe('image');
    expect($validator->determineCategory('application/pdf'))->toBe('document');
    expect($validator->determineCategory('video/mp4'))->toBe('video');
    expect($validator->determineCategory('audio/mpeg'))->toBe('audio');
    expect($validator->determineCategory('application/zip'))->toBe('archive');
});

test('upload service handles valid upload, runs scanner and optimizer', function () {
    Storage::fake('local');

    $user = User::create([
        'name' => 'John Storage',
        'email' => 'john.storage@example.com',
        'password' => 'password123',
    ]);

    $org = Organization::create([
        'name' => 'Cloud Inc',
        'subdomain' => 'cloudinc',
        'owner_id' => $user->id,
    ]);

    $service = app(UploadServiceInterface::class);
    
    // Create fake image file
    $file = UploadedFile::fake()->image('avatar.jpg');

    $storedFile = $service->upload(
        $file,
        $user->id,
        $org->id,
        'private'
    );

    expect($storedFile)->not->toBeNull();
    expect($storedFile->name)->toBe('avatar.jpg');
    expect($storedFile->category)->toBe('image');
    expect($storedFile->disk)->toBe('local');
    expect($storedFile->organization_id)->toBe($org->id);
    expect($storedFile->virus_scan_status)->toBe('clean');

    // Verify file physical storage exists
    Storage::disk('local')->assertExists($storedFile->path);
});

test('virus scanner quarantines files containing eicar or infected string', function () {
    Storage::fake('local');

    $user = User::create([
        'name' => 'Bio hazard Owner',
        'email' => 'infected@example.com',
        'password' => 'password123',
    ]);

    $service = app(UploadServiceInterface::class);
    
    // Create an infected test file (using infected string)
    $file = UploadedFile::fake()->create('infected_payload.txt', 10, 'text/plain');

    $storedFile = $service->upload(
        $file,
        $user->id,
        null,
        'private'
    );

    expect($storedFile->virus_scan_status)->toBe('infected');
    expect($storedFile->virus_scan_result)->toContain('EICAR');
});

test('download service respects tenant and owner permissions', function () {
    Storage::fake('local');

    $owner = User::create([
        'name' => 'Owner User',
        'email' => 'owner@example.com',
        'password' => 'password123',
    ]);

    $intruder = User::create([
        'name' => 'Intruder User',
        'email' => 'intruder@example.com',
        'password' => 'password123',
    ]);

    $service = app(UploadServiceInterface::class);
    $downloadService = app(DownloadServiceInterface::class);

    $file = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');

    // Private system-level file
    $storedFile = $service->upload($file, $owner->id, null, 'private');

    // Owner should be allowed to download
    $response = $downloadService->download($storedFile, $owner->id);
    expect($response)->not->toBeNull();

    // Intruder download should throw AccessDeniedHttpException
    $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);
    $downloadService->download($storedFile, $intruder->id);
});

test('signed url service generates and verifies temporal link payload', function () {
    Storage::fake('local');

    $user = User::create([
        'name' => 'Link Generator',
        'email' => 'link@example.com',
        'password' => 'password123',
    ]);

    $service = app(UploadServiceInterface::class);
    $signedUrlService = app(SignedUrlServiceInterface::class);

    $file = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');
    $storedFile = $service->upload($file, $user->id, null, 'private');

    // Generate signed URL
    $expires = time() + 3600;
    $appKey = config('app.key');
    $signature = hash_hmac('sha256', $storedFile->id . '|' . $expires, $appKey);

    $verified = $signedUrlService->verify($storedFile->id, $signature, $expires);
    expect($verified)->toBeTrue();

    // Bad signature should fail verification
    $verifiedBad = $signedUrlService->verify($storedFile->id, 'corrupted_sig', $expires);
    expect($verifiedBad)->toBeFalse();

    // Expired verification should fail
    $verifiedExpired = $signedUrlService->verify($storedFile->id, $signature, time() - 1);
    expect($verifiedExpired)->toBeFalse();
});

test('api endpoints permit index list, uploading and deletion', function () {
    Storage::fake('local');

    $user = User::create([
        'name' => 'Storage API Tester',
        'email' => 'storage.api@example.com',
        'password' => 'password123',
    ]);

    $file = UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf');

    // Assert uploading via API
    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/files', [
            'file' => $file,
            'visibility' => 'private',
        ]);

    $response->assertStatus(200);
    $json = $response->json();
    expect($json['status'])->toBe('success');
    expect($json['data']['name'])->toBe('doc.pdf');

    $fileId = $json['data']['id'];

    // Assert files listing
    $this->actingAs($user, 'sanctum')
        ->getJson('/api/files')
        ->assertStatus(200)
        ->assertJsonFragment(['name' => 'doc.pdf']);

    // Assert deletion
    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/files/{$fileId}")
        ->assertStatus(200);

    $this->assertDatabaseMissing('stored_files', ['id' => $fileId]);
});

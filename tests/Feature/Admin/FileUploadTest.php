<?php

use App\Models\MediaStorage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('uploads a file through the admin filepond endpoint', function () {
    Storage::fake('public_uploads');

    $admin = User::factory()->create([
        'user_type' => 'admin',
        'username' => 'file-upload-admin',
    ]);

    $file = UploadedFile::fake()->image('avatar.png', 120, 120);

    $response = $this->actingAs($admin)->post(route('admin::file-upload'), [
        'file' => $file,
        'folder' => 'avatars',
    ], [
        'Accept' => 'application/json',
        'folder' => 'avatars',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => ['id', 'url', 'path', 'public_url'],
        ]);

    $media = MediaStorage::query()->findOrFail($response->json('data.id'));

    expect($media->disk)->toBe('public_uploads')
        ->and($media->user_id)->toBe($admin->id)
        ->and($media->url)->toStartWith('uploads/avatars/')
        ->and(Storage::disk('public_uploads')->exists($media->path))->toBeTrue();
});

it('rejects disallowed mime types for image folders', function () {
    Storage::fake('public_uploads');

    $admin = User::factory()->create([
        'user_type' => 'admin',
        'username' => 'file-upload-mime',
    ]);

    $response = $this->actingAs($admin)->postJson(route('admin::file-upload'), [
        'file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        'folder' => 'avatars',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('reverts an uploaded file by media id', function () {
    Storage::fake('public_uploads');

    $admin = User::factory()->create([
        'user_type' => 'admin',
        'username' => 'file-upload-revert',
    ]);

    $upload = $this->actingAs($admin)->post(route('admin::file-upload'), [
        'file' => UploadedFile::fake()->image('logo.jpg'),
        'folder' => 'logos',
    ]);

    $id = (string) $upload->json('data.id');
    $path = $upload->json('data.path');

    $response = $this->actingAs($admin)->call(
        'DELETE',
        route('admin::file-upload.revert'),
        [],
        [],
        [],
        [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'text/plain',
        ],
        $id,
    );

    $response->assertOk()->assertJsonPath('success', true);

    expect(MediaStorage::query()->find($id))->toBeNull()
        ->and(Storage::disk('public_uploads')->exists($path))->toBeFalse();
});

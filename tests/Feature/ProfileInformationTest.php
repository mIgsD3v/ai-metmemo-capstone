<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileInformationTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_information_can_be_updated(): void
    {
        $this->actingAs($user = User::factory()->create());

        $this->put('/user/profile-information', [
            'name' => 'Test Name',
            'email' => 'test@example.com',
        ]);

        $this->assertEquals('Test Name', $user->fresh()->name);
        $this->assertEquals('test@example.com', $user->fresh()->email);
    }

    public function test_profile_photo_can_be_uploaded(): void
    {
        Storage::fake('public');

        $this->actingAs($user = User::factory()->create());

        $this->put('/user/profile-information', [
            'name' => $user->name,
            'email' => $user->email,
            'photo' => UploadedFile::fake()->image('me.jpg', 512, 512),
        ])->assertSessionHasNoErrors();

        $path = $user->fresh()->profile_photo_path;

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_profile_photo_must_be_an_image(): void
    {
        $this->actingAs($user = User::factory()->create());

        $this->put('/user/profile-information', [
            'name' => $user->name,
            'email' => $user->email,
            'photo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrorsIn('updateProfileInformation', 'photo');

        $this->assertNull($user->fresh()->profile_photo_path);
    }
}

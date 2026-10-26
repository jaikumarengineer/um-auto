<?php

use App\Models\User;
use Livewire\Volt\Volt;
use Modules\Quality\Models\ApprovedVendor;

it('fills the next vendor code and rejects a bad or duplicate code', function () {
    $this->actingAs(User::factory()->create([
        'user_type' => 'incoming',
        'username' => 'incoming-vendor-codes',
    ]));

    ApprovedVendor::query()->create([
        'code' => 'V-1310',
        'name' => 'Bright Polymers',
        'status' => 'pending',
    ]);

    Volt::test('admin.quality.incoming.vendors-page')
        ->call('createVendor')
        ->assertSet('code', 'V-1311')
        ->set('name', 'River Metals')
        ->call('saveVendor')
        ->assertHasNoErrors();

    expect(ApprovedVendor::query()->where('name', 'River Metals')->value('code'))->toBe('V-1311');

    Volt::test('admin.quality.incoming.vendors-page')
        ->call('createVendor')
        ->assertSet('code', 'V-1312')
        ->set('code', 'not-a-code')
        ->set('name', 'Bad Code Metals')
        ->call('saveVendor')
        ->assertHasErrors(['code' => 'regex']);

    Volt::test('admin.quality.incoming.vendors-page')
        ->call('createVendor')
        ->set('code', 'V-1310')
        ->set('name', 'Duplicate Metals')
        ->call('saveVendor')
        ->assertHasErrors(['code' => 'unique']);

    expect(ApprovedVendor::query()->where('name', 'Duplicate Metals')->exists())->toBeFalse();
});

it('starts vendor codes at V-1001 when none exist', function () {
    expect(ApprovedVendor::nextCode())->toBe('V-1001');
});

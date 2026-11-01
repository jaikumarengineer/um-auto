<?php

use App\Models\User;
use Livewire\Volt\Volt;
use Modules\Quality\Models\SamplingPlan;

it('shows the ML-08 sheet as one matrix and saves sample sizes', function () {
    $this->actingAs(User::factory()->create([
        'user_type' => 'admin',
        'username' => 'sampling-admin',
    ]));

    $this->get(route('admin::quality.incoming.sampling'))
        ->assertOk()
        ->assertSee('2 to 8')
        ->assertSee('Critical');

    $component = Volt::test('admin.quality.incoming.sampling-plans-page')
        ->assertSee('2 to 8')
        ->assertSee('91 to 150')
        ->assertSee('500001 and over')
        ->assertSee('Critical')
        ->assertSee('Major')
        ->assertSee('Others')
        ->assertSee('1.0')
        ->assertSee('4.0')
        ->assertSee('10.0')
        ->assertDontSee('Add band');

    $critical = SamplingPlan::query()->where('code', 'ML08-1-91-150')->firstOrFail();
    $star = SamplingPlan::query()->where('code', 'ML08-1-2-8')->firstOrFail();

    expect($critical->sample_size)->toBe(13)
        ->and($star->sample_size)->toBe(0)
        ->and($component->get('grid')['c'.$critical->id])->toBe('13')
        ->and($component->get('grid')['c'.$star->id])->toBe('*');

    $component
        ->set('grid.c'.$critical->id, '20')
        ->call('saveGrid')
        ->assertHasNoErrors();

    expect($critical->fresh()->sample_size)->toBe(20)
        ->and($critical->fresh()->accept_number)->toBe(0)
        ->and($critical->fresh()->reject_number)->toBe(1);

    $component
        ->set('grid.c'.$star->id, '4')
        ->call('saveGrid')
        ->assertHasNoErrors();

    expect($star->fresh()->sample_size)->toBe(4)
        ->and($star->fresh()->notes)->toBeNull();

    $component
        ->set('grid.c'.$critical->id, '*')
        ->call('saveGrid')
        ->assertHasNoErrors();

    expect($critical->fresh()->sample_size)->toBe(0)
        ->and($critical->fresh()->notes)->toBe('Entire lot must be inspected.');

    $component
        ->set('grid.c'.$critical->id, 'nope')
        ->call('saveGrid')
        ->assertHasErrors(['grid.c'.$critical->id]);

    expect($critical->fresh()->sample_size)->toBe(0);
});

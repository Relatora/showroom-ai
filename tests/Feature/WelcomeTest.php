<?php

use App\Enums\LeadStage;
use App\Models\Chunk;
use App\Models\Lead;
use App\Models\Vehicle;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the landing page to guests with live totals', function () {
    Vehicle::factory()->count(2)->create();
    Vehicle::factory()->sold()->create();
    Lead::factory()->create();
    Lead::factory()->inStage(LeadStage::Lost)->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('stats.vehicles', Vehicle::available()->count())
            ->where('stats.openLeads', 1)
            ->where('stats.chunks', Chunk::count())
            ->where('repository', config('showroom.repository')));
});

it('only shares totals, never anything about a customer', function () {
    $lead = Lead::factory()->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee($lead->customer->last_name)
        ->assertDontSee($lead->customer->email);
});

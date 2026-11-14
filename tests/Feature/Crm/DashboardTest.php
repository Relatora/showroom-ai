<?php

use App\Enums\LeadStage;
use App\Models\Lead;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows a rep their own day and no leaderboard', function () {
    $rep = User::factory()->create();
    Lead::factory()->assignedTo($rep)->stale()->create();
    Lead::factory()->stale()->create(); // someone else's

    $this->actingAs($rep)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('today.stale', 1)
            ->has('callFirst', 1)
            ->where('leaderboard', null)
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('pipeline.1.stage', LeadStage::Contacted->value)
                ->where('pipeline.1.count', 1)));
});

it('gives managers the whole floor and a leaderboard', function () {
    Lead::factory()->count(2)->stale()->create();

    $this->actingAs(User::factory()->manager()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('today.stale', 2)
            ->loadDeferredProps(fn (Assert $reload) => $reload->has('leaderboard', 2)));
});

<?php

use App\Enums\LeadStage;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;

/*
 * Who sees which leads is the rule the rest of the app leans on: lists, the dashboard, the
 * assistant's retrieval and the MCP tools all go through Lead::visibleTo(). If this file is
 * green, a rep can't see another rep's customers anywhere.
 */

beforeEach(function () {
    $this->marcus = User::factory()->create(['name' => 'Marcus']);
    $this->priya = User::factory()->create(['name' => 'Priya']);

    $this->marcusLead = Lead::factory()->assignedTo($this->marcus)->create();
    $this->priyaLead = Lead::factory()->assignedTo($this->priya)->create();
});

it('limits reps to their own leads', function () {
    expect(Lead::visibleTo($this->marcus)->pluck('id')->all())
        ->toBe([$this->marcusLead->id]);
});

it('shows the whole pipeline to managers and the BDC', function (string $role) {
    $user = User::factory()->{$role}()->create();

    expect(Lead::visibleTo($user)->count())->toBe(2);
})->with(['manager', 'bdc']);

it('keeps the policy and the query scope in agreement', function () {
    expect($this->marcus->can('view', $this->marcusLead))->toBeTrue()
        ->and($this->marcus->can('view', $this->priyaLead))->toBeFalse();
});

it('only lets managers reassign or delete leads', function () {
    $manager = User::factory()->manager()->create();

    expect($manager->can('reassign', $this->marcusLead))->toBeTrue()
        ->and($this->marcus->can('reassign', $this->marcusLead))->toBeFalse()
        ->and($this->marcus->can('delete', $this->marcusLead))->toBeFalse();
});

it('scopes customers and appointments through their leads', function () {
    Appointment::factory()->for($this->marcusLead)->create(['user_id' => $this->marcus->id]);
    Appointment::factory()->for($this->priyaLead)->create(['user_id' => $this->priya->id]);

    expect(Customer::visibleTo($this->marcus)->pluck('id')->all())->toBe([$this->marcusLead->customer_id])
        ->and(Appointment::visibleTo($this->marcus)->count())->toBe(1);
});

it('treats open leads with no recent contact as stale', function () {
    $stale = Lead::factory()->assignedTo($this->marcus)->stale()->create();
    Lead::factory()->assignedTo($this->marcus)->stale()->inStage(LeadStage::Won)->create();

    expect(Lead::stale()->pluck('id')->all())->toBe([$stale->id])
        ->and($stale->isStale())->toBeTrue()
        ->and($this->marcusLead->isStale())->toBeFalse();
});

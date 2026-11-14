<?php

use App\Enums\AppointmentStatus;
use App\Enums\Powertrain;
use App\Models\Appointment;
use App\Models\Lead;
use App\Models\User;
use App\Models\Vehicle;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->rep = User::factory()->create();
});

it('hides sold stock unless asked for it', function () {
    Vehicle::factory()->create();
    Vehicle::factory()->sold()->create();

    $this->actingAs($this->rep)
        ->get(route('inventory.index'))
        ->assertInertia(fn (Assert $page) => $page->component('inventory/Index')->has('vehicles.data', 1));

    $this->actingAs($this->rep)
        ->get(route('inventory.index', ['status' => 'sold']))
        ->assertInertia(fn (Assert $page) => $page->has('vehicles.data', 1));
});

it('filters by what the customer needs', function () {
    Vehicle::factory()->create(['seats' => 8, 'towing_lb' => 5000, 'price' => 52000]);
    Vehicle::factory()->create(['seats' => 5, 'towing_lb' => 1500, 'price' => 32000]);
    Vehicle::factory()->create(['seats' => 5, 'powertrain' => Powertrain::Ev, 'towing_lb' => null]);

    $count = fn (array $query) => $this->actingAs($this->rep)
        ->get(route('inventory.index', $query))
        ->viewData('page')['props']['vehicles']['meta']['total'];

    expect($count(['min_seats' => 7]))->toBe(1)
        ->and($count(['min_towing' => 3500]))->toBe(1)
        ->and($count(['powertrain' => 'ev']))->toBe(1)
        ->and($count(['max_price' => 40000]))->toBeGreaterThanOrEqual(1);
});

it('finds a vehicle by a pasted VIN', function () {
    $vehicle = Vehicle::factory()->create(['vin' => 'JTMABCDE1RJ123456']);
    Vehicle::factory()->create();

    $this->actingAs($this->rep)
        ->get(route('inventory.index', ['q' => 'jtmabcde1rj123456']))
        ->assertInertia(fn (Assert $page) => $page->where('vehicles.data.0.id', $vehicle->id)->has('vehicles.data', 1));
});

it('only lists interested leads the viewer is allowed to see', function () {
    $vehicle = Vehicle::factory()->create();
    Lead::factory()->assignedTo($this->rep)->create(['vehicle_id' => $vehicle->id]);
    Lead::factory()->create(['vehicle_id' => $vehicle->id]); // another rep's customer

    $this->actingAs($this->rep)
        ->get(route('inventory.show', $vehicle))
        ->assertInertia(fn (Assert $page) => $page->component('inventory/Show')->has('leads', 1));
});

it('shows the week of appointments for the viewer\'s own leads', function () {
    $mine = Lead::factory()->assignedTo($this->rep)->create();
    Appointment::factory()->for($mine)->create(['user_id' => $this->rep->id, 'starts_at' => now()->startOfWeek()->addDay()->setTime(10, 0)]);
    Appointment::factory()->create(['starts_at' => now()->startOfWeek()->addDay()->setTime(11, 0)]);

    $this->actingAs($this->rep)
        ->get(route('appointments.index'))
        ->assertInertia(fn (Assert $page) => $page->component('appointments/Index')->has('appointments', 1));
});

it('lets the rep mark an appointment done, but not someone else\'s', function () {
    $mine = Appointment::factory()->for(Lead::factory()->assignedTo($this->rep))->create(['user_id' => $this->rep->id]);
    $theirs = Appointment::factory()->create();

    $this->actingAs($this->rep)->patch(route('appointments.update', $mine), ['status' => 'completed'])->assertRedirect();
    $this->actingAs($this->rep)->patch(route('appointments.update', $theirs), ['status' => 'completed'])->assertForbidden();

    expect($mine->refresh()->status)->toBe(AppointmentStatus::Completed);
});

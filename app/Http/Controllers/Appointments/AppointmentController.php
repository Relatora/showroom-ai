<?php

namespace App\Http\Controllers\Appointments;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The week on the showroom floor: test drives, appraisals and deliveries.
 */
class AppointmentController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate(['week' => ['nullable', 'date_format:Y-m-d']]);

        $start = CarbonImmutable::parse($request->query('week', now()->toDateString()))->startOfWeek();
        $end = $start->endOfWeek();

        $appointments = Appointment::visibleTo($request->user())
            ->whereBetween('starts_at', [$start, $end])
            ->with(['lead.customer', 'user', 'vehicle'])
            ->orderBy('starts_at')
            ->get();

        return Inertia::render('appointments/Index', [
            'week' => [
                'start' => $start->toDateString(),
                'previous' => $start->subWeek()->toDateString(),
                'next' => $start->addWeek()->toDateString(),
                'isCurrent' => $start->isSameDay(now()->startOfWeek()),
            ],
            'appointments' => AppointmentResource::collection($appointments),
        ]);
    }

    /**
     * Marking how it went: completed, no-show or cancelled.
     */
    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        Gate::authorize('update', $appointment);

        $appointment->update($request->validate([
            'status' => ['required', Rule::enum(AppointmentStatus::class)],
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Appointment updated.']);

        return back();
    }
}

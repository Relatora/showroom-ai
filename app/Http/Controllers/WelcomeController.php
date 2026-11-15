<?php

namespace App\Http\Controllers;

use App\Models\Chunk;
use App\Models\Document;
use App\Models\Lead;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public front page.
 *
 * The numbers in the "this install" strip come from the database rather than marketing copy, so
 * what a visitor reads matches what they'll find after logging in. They're only totals (no names
 * or contact details), and they're cached for a few minutes because nobody needs them to the
 * second.
 */
class WelcomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Welcome', [
            'stats' => Cache::remember('welcome:stats', now()->addMinutes(5), fn () => [
                'vehicles' => Vehicle::available()->count(),
                'openLeads' => Lead::open()->count(),
                'documents' => Document::count(),
                'chunks' => Chunk::count(),
            ]),
            'repository' => config('showroom.repository'),
        ]);
    }
}

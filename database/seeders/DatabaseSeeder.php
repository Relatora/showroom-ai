<?php

namespace Database\Seeders;

use Database\Seeders\Demo\DemoDealershipSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Model events stay off while seeding. Indexing for the assistant is kicked off explicitly
    // afterwards (showroom:index), rather than queuing hundreds of jobs from observers mid-seed.
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(DemoDealershipSeeder::class);
    }
}

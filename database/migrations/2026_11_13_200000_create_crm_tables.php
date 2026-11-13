<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The core CRM schema: who works here, who's buying, what's on the lot, and the pipeline
 * that connects them. One migration because these tables only make sense together.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('rep')->after('email')->index();
            $table->string('title')->nullable()->after('role');
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->string('province', 2)->nullable();
            $table->string('preferred_contact')->default('email');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['last_name', 'first_name']);
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('stock_number')->unique();
            $table->string('vin', 17)->unique();
            $table->unsignedSmallInteger('year');
            $table->string('make');
            $table->string('model');
            $table->string('trim')->nullable();
            $table->string('body_style')->index();
            $table->string('powertrain')->index();
            $table->string('drivetrain');
            $table->unsignedTinyInteger('seats');
            $table->string('exterior_color');
            $table->string('exterior_hex', 7);
            $table->string('interior_color');
            $table->unsignedInteger('km')->default(0);
            // Whole dollars. Nobody negotiates a car down to the cent, and integers keep the maths honest.
            $table->unsignedInteger('price')->index();
            $table->string('condition')->index();
            $table->string('status')->default('available')->index();
            $table->unsignedSmallInteger('towing_lb')->nullable();
            $table->unsignedSmallInteger('range_km')->nullable();
            $table->decimal('fuel_l_per_100km', 4, 1)->nullable();
            $table->json('features');
            $table->text('description');
            $table->date('arrived_on');
            $table->timestamps();

            $table->index(['make', 'model']);
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source');
            $table->string('stage')->default('new');
            $table->string('temperature')->default('warm');
            $table->unsignedInteger('budget_max')->nullable();
            // Roughly in the customer's own words: "3-row hybrid, tows a small trailer, under $45k".
            $table->text('interest');
            $table->string('trade_in')->nullable();
            // Order on the kanban board, within a stage.
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('lost_reason')->nullable();
            $table->timestamps();

            $table->index(['stage', 'position']);
            $table->index(['assigned_user_id', 'stage']);
            $table->index('last_contacted_at');
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            // Null when the system logged it rather than a person.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->text('body');
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['lead_id', 'occurred_at']);
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('status')->default('scheduled');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['starts_at', 'status']);
            $table->index(['user_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('customers');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'title']);
        });
    }
};

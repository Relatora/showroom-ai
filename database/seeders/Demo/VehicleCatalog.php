<?php

namespace Database\Seeders\Demo;

/**
 * A believable mix of what a Canadian multi-brand dealer might have on the lot.
 *
 * The specs are realistic rather than exact. They're here so the assistant has real things to
 * reason about: towing capacity, seating, EV range, fuel economy. Prices are MSRP-ish for new
 * stock and get depreciated for used units in the seeder.
 */
final class VehicleCatalog
{
    /**
     * @return list<array{make: string, model: string, body: string, powertrain: string, drivetrain: string, seats: int, trims: array<string, int>, towing_lb?: int, range_km?: int, fuel?: float, pitch: string}>
     */
    public static function models(): array
    {
        return [
            ['make' => 'Toyota', 'model' => 'Corolla', 'body' => 'sedan', 'powertrain' => 'gas', 'drivetrain' => 'fwd', 'seats' => 5, 'trims' => ['LE' => 24990, 'SE' => 27490, 'XSE' => 30990], 'fuel' => 7.0, 'pitch' => 'The default answer for a reliable, cheap-to-run first car or commuter.'],
            ['make' => 'Toyota', 'model' => 'RAV4 Hybrid', 'body' => 'suv', 'powertrain' => 'hybrid', 'drivetrain' => 'awd', 'seats' => 5, 'trims' => ['LE' => 37990, 'XLE' => 41490, 'Limited' => 47990], 'towing_lb' => 1750, 'fuel' => 6.0, 'pitch' => 'Compact SUV with standard AWD and hybrid fuel economy. Great in snow, easy to park.'],
            ['make' => 'Toyota', 'model' => 'Highlander Hybrid', 'body' => 'suv', 'powertrain' => 'hybrid', 'drivetrain' => 'awd', 'seats' => 8, 'trims' => ['XLE' => 52990, 'Limited' => 59990], 'towing_lb' => 3500, 'fuel' => 6.8, 'pitch' => 'Three rows and hybrid economy. A family hauler that does not drink fuel.'],
            ['make' => 'Toyota', 'model' => 'Sienna', 'body' => 'minivan', 'powertrain' => 'hybrid', 'drivetrain' => 'awd', 'seats' => 8, 'trims' => ['LE' => 48990, 'XSE' => 55990, 'Limited' => 63990], 'towing_lb' => 3500, 'fuel' => 6.6, 'pitch' => 'Hybrid-only minivan with available AWD. Sliding doors and car seats are a breeze.'],
            ['make' => 'Toyota', 'model' => 'Tacoma', 'body' => 'truck', 'powertrain' => 'gas', 'drivetrain' => '4wd', 'seats' => 5, 'trims' => ['SR5' => 47990, 'TRD Off-Road' => 54990], 'towing_lb' => 6500, 'fuel' => 11.2, 'pitch' => 'Mid-size pickup with legendary resale value and real off-road ability.'],
            ['make' => 'Honda', 'model' => 'Civic', 'body' => 'sedan', 'powertrain' => 'gas', 'drivetrain' => 'fwd', 'seats' => 5, 'trims' => ['LX' => 28990, 'Sport' => 31490, 'Touring' => 35490], 'fuel' => 6.9, 'pitch' => 'Fun to drive, efficient, and holds its value. Popular with first-time buyers.'],
            ['make' => 'Honda', 'model' => 'CR-V Hybrid', 'body' => 'suv', 'powertrain' => 'hybrid', 'drivetrain' => 'awd', 'seats' => 5, 'trims' => ['Sport' => 41990, 'EX-L' => 44990, 'Touring' => 48990], 'towing_lb' => 1000, 'fuel' => 6.1, 'pitch' => 'Roomy compact SUV with a huge cargo area and a smooth hybrid system.'],
            ['make' => 'Honda', 'model' => 'Pilot', 'body' => 'suv', 'powertrain' => 'gas', 'drivetrain' => 'awd', 'seats' => 8, 'trims' => ['EX-L' => 54990, 'TrailSport' => 59990, 'Black Edition' => 64990], 'towing_lb' => 5000, 'fuel' => 11.5, 'pitch' => 'Eight seats and a V6 that tows 5,000 lb. Adults fit in the third row.'],
            ['make' => 'Honda', 'model' => 'Odyssey', 'body' => 'minivan', 'powertrain' => 'gas', 'drivetrain' => 'fwd', 'seats' => 8, 'trims' => ['EX-L' => 50990, 'Touring' => 54990], 'towing_lb' => 3000, 'fuel' => 10.5, 'pitch' => 'Magic Slide second row and a cabin camera for keeping an eye on the kids.'],
            ['make' => 'Ford', 'model' => 'F-150', 'body' => 'truck', 'powertrain' => 'gas', 'drivetrain' => '4wd', 'seats' => 6, 'trims' => ['XLT' => 62990, 'Lariat' => 74990], 'towing_lb' => 11300, 'fuel' => 12.4, 'pitch' => 'Full-size workhorse. Tows a camper, a boat or a work trailer without breaking a sweat.'],
            ['make' => 'Ford', 'model' => 'F-150 Lightning', 'body' => 'truck', 'powertrain' => 'ev', 'drivetrain' => '4wd', 'seats' => 5, 'trims' => ['Pro' => 69990, 'XLT' => 79990], 'towing_lb' => 7700, 'range_km' => 515, 'pitch' => 'Electric full-size pickup. Can power your house in an outage or tools on a job site.'],
            ['make' => 'Ford', 'model' => 'Maverick Hybrid', 'body' => 'truck', 'powertrain' => 'hybrid', 'drivetrain' => 'fwd', 'seats' => 5, 'trims' => ['XL' => 35990, 'XLT' => 38990, 'Lariat' => 45990], 'towing_lb' => 2000, 'fuel' => 6.2, 'pitch' => 'Compact hybrid pickup. Truck bed, car-like fuel bills.'],
            ['make' => 'Ford', 'model' => 'Mustang Mach-E', 'body' => 'suv', 'powertrain' => 'ev', 'drivetrain' => 'awd', 'seats' => 5, 'trims' => ['Select' => 54990, 'Premium' => 62990, 'GT' => 74990], 'range_km' => 470, 'pitch' => 'Sporty electric crossover with BlueCruise hands-free highway driving.'],
            ['make' => 'Hyundai', 'model' => 'Elantra', 'body' => 'sedan', 'powertrain' => 'gas', 'drivetrain' => 'fwd', 'seats' => 5, 'trims' => ['Preferred' => 25990, 'Luxury' => 29990, 'N Line' => 31990], 'fuel' => 6.6, 'pitch' => 'Lots of features for the money and a 5-year comprehensive warranty.'],
            ['make' => 'Hyundai', 'model' => 'Tucson Hybrid', 'body' => 'suv', 'powertrain' => 'hybrid', 'drivetrain' => 'awd', 'seats' => 5, 'trims' => ['Preferred' => 39990, 'Luxury' => 43990, 'Ultimate' => 46990], 'towing_lb' => 1650, 'fuel' => 6.4, 'pitch' => 'Distinctive styling, standard AWD hybrid, and a long warranty.'],
            ['make' => 'Hyundai', 'model' => 'Ioniq 5', 'body' => 'suv', 'powertrain' => 'ev', 'drivetrain' => 'awd', 'seats' => 5, 'trims' => ['Preferred' => 57990, 'Ultimate' => 64990], 'range_km' => 435, 'pitch' => 'Ultra-fast charging: 10 to 80 percent in about 18 minutes on a 350 kW charger.'],
            ['make' => 'Hyundai', 'model' => 'Santa Fe Hybrid', 'body' => 'suv', 'powertrain' => 'hybrid', 'drivetrain' => 'awd', 'seats' => 7, 'trims' => ['Preferred' => 47990, 'Luxury' => 52990], 'towing_lb' => 3500, 'fuel' => 6.9, 'pitch' => 'Boxy, practical three-row hybrid with a huge tailgate opening.'],
            ['make' => 'Kia', 'model' => 'Sorento PHEV', 'body' => 'suv', 'powertrain' => 'phev', 'drivetrain' => 'awd', 'seats' => 6, 'trims' => ['EX' => 52990, 'SX' => 57990], 'towing_lb' => 2000, 'range_km' => 51, 'fuel' => 6.8, 'pitch' => 'Plug-in hybrid with three rows: around 50 km of electric driving for daily errands.'],
            ['make' => 'Kia', 'model' => 'Telluride', 'body' => 'suv', 'powertrain' => 'gas', 'drivetrain' => 'awd', 'seats' => 8, 'trims' => ['EX' => 52990, 'SX' => 57990, 'X-Pro' => 61990], 'towing_lb' => 5000, 'fuel' => 11.2, 'pitch' => 'Award-winning three-row SUV that feels far more expensive than it is.'],
            ['make' => 'Kia', 'model' => 'EV9', 'body' => 'suv', 'powertrain' => 'ev', 'drivetrain' => 'awd', 'seats' => 7, 'trims' => ['Land' => 74990, 'GT-Line' => 81990], 'towing_lb' => 5000, 'range_km' => 451, 'pitch' => 'A true three-row electric SUV that can also tow 5,000 lb.'],
            ['make' => 'Kia', 'model' => 'Carnival', 'body' => 'minivan', 'powertrain' => 'gas', 'drivetrain' => 'fwd', 'seats' => 8, 'trims' => ['LX' => 41990, 'EX' => 46990, 'SX' => 52990], 'towing_lb' => 3500, 'fuel' => 10.8, 'pitch' => 'SUV looks with minivan practicality and sliding doors.'],
            ['make' => 'Subaru', 'model' => 'Outback', 'body' => 'wagon', 'powertrain' => 'gas', 'drivetrain' => 'awd', 'seats' => 5, 'trims' => ['Convenience' => 37990, 'Touring' => 41990, 'Wilderness' => 47990], 'towing_lb' => 2700, 'fuel' => 8.6, 'pitch' => 'Symmetrical AWD and 8.7 inches of ground clearance. Cottage country favourite.'],
            ['make' => 'Subaru', 'model' => 'Crosstrek', 'body' => 'suv', 'powertrain' => 'gas', 'drivetrain' => 'awd', 'seats' => 5, 'trims' => ['Convenience' => 30990, 'Sport' => 34490], 'towing_lb' => 1500, 'fuel' => 7.6, 'pitch' => 'Affordable AWD crossover that handles unplowed side streets with ease.'],
            ['make' => 'Mazda', 'model' => 'CX-5', 'body' => 'suv', 'powertrain' => 'gas', 'drivetrain' => 'awd', 'seats' => 5, 'trims' => ['GS' => 35990, 'GT' => 41990, 'Signature' => 46990], 'towing_lb' => 2000, 'fuel' => 8.4, 'pitch' => 'Upscale interior and genuinely engaging to drive for a compact SUV.'],
            ['make' => 'Mazda', 'model' => 'Mazda3 Sport', 'body' => 'hatchback', 'powertrain' => 'gas', 'drivetrain' => 'awd', 'seats' => 5, 'trims' => ['GS' => 29990, 'GT' => 34990], 'fuel' => 7.3, 'pitch' => 'Premium-feeling hatchback with available AWD.'],
            ['make' => 'Mazda', 'model' => 'MX-5', 'body' => 'coupe', 'powertrain' => 'gas', 'drivetrain' => 'rwd', 'seats' => 2, 'trims' => ['GS' => 36990, 'GT' => 41990], 'fuel' => 7.8, 'pitch' => 'Two seats, rear-wheel drive, pure fun. A summer car people fall in love with.'],
            ['make' => 'Chevrolet', 'model' => 'Silverado 1500', 'body' => 'truck', 'powertrain' => 'gas', 'drivetrain' => '4wd', 'seats' => 6, 'trims' => ['LT' => 61990, 'RST' => 66990, 'High Country' => 79990], 'towing_lb' => 13000, 'fuel' => 12.9, 'pitch' => 'Max towing up to 13,000 lb with the right package. Built for heavy trailers.'],
            ['make' => 'Chevrolet', 'model' => 'Equinox EV', 'body' => 'suv', 'powertrain' => 'ev', 'drivetrain' => 'awd', 'seats' => 5, 'trims' => ['2LT' => 49990, '3RS' => 56990], 'range_km' => 513, 'pitch' => 'Affordable long-range EV: over 500 km on a charge.'],
            ['make' => 'Tesla', 'model' => 'Model 3', 'body' => 'sedan', 'powertrain' => 'ev', 'drivetrain' => 'awd', 'seats' => 5, 'trims' => ['Long Range' => 64990], 'range_km' => 584, 'pitch' => 'Long range, quick, and access to the Supercharger network.'],
            ['make' => 'Tesla', 'model' => 'Model Y', 'body' => 'suv', 'powertrain' => 'ev', 'drivetrain' => 'awd', 'seats' => 5, 'trims' => ['Long Range' => 61990, 'Performance' => 70990], 'towing_lb' => 3500, 'range_km' => 526, 'pitch' => 'Best-selling EV crossover with a big hatch and a frunk.'],
            ['make' => 'Volkswagen', 'model' => 'Golf GTI', 'body' => 'hatchback', 'powertrain' => 'gas', 'drivetrain' => 'fwd', 'seats' => 5, 'trims' => ['Autobahn' => 44990], 'fuel' => 8.2, 'pitch' => 'The original hot hatch. Practical enough for every day, fun on a back road.'],
            ['make' => 'Volkswagen', 'model' => 'Atlas', 'body' => 'suv', 'powertrain' => 'gas', 'drivetrain' => 'awd', 'seats' => 7, 'trims' => ['Comfortline' => 50990, 'Highline' => 56990], 'towing_lb' => 5000, 'fuel' => 10.9, 'pitch' => 'Biggest third row in its class. Easy access with the tilt-and-slide seat.'],
        ];
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function colours(): array
    {
        return [
            ['Midnight Black', '#121418'],
            ['Wind Chill Pearl', '#eef0f2'],
            ['Celestial Silver', '#b8bcc2'],
            ['Magnetic Grey', '#5c6066'],
            ['Supersonic Red', '#b3121f'],
            ['Soul Red Crystal', '#9e1b22'],
            ['Blueprint', '#2c4a7a'],
            ['Atlas Blue', '#3b5f8c'],
            ['Cypress Green', '#4b5d47'],
            ['Ignition Orange', '#d9581b'],
            ['Snowflake White', '#f4f4f2'],
            ['Sonic Grey', '#9a9ea3'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function features(): array
    {
        return [
            'Heated front seats', 'Heated steering wheel', 'Apple CarPlay and Android Auto', 'Adaptive cruise control',
            'Blind-spot monitoring', '360-degree camera', 'Panoramic sunroof', 'Remote start', 'Wireless phone charging',
            'Power liftgate', 'Ventilated front seats', 'Head-up display', 'Premium audio', 'Heated rear seats',
            'Lane-centring assist', 'Hands-free liftgate', 'Leather seating', 'Navigation',
        ];
    }
}

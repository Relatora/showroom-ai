<?php

namespace Database\Seeders\Demo;

/**
 * The kinds of people who walk into a dealership, and the things they tend to say.
 *
 * Seeding leads from personas (instead of lorem ipsum) means the notes and stated needs read
 * like a real CRM. That matters later: the assistant can only be as useful as the text it
 * retrieves, and the evaluation questions are written against these details.
 */
final class BuyerPersonas
{
    /**
     * @return array<string, array{bodies: list<string>, powertrains: list<string>, budget: array{0: int, 1: int}, interest: list<string>, notes: list<string>}>
     */
    public static function all(): array
    {
        return [
            'growing_family' => [
                'bodies' => ['suv', 'minivan'],
                'powertrains' => ['hybrid', 'phev', 'gas'],
                'budget' => [45000, 65000],
                'interest' => [
                    'Needs a 3-row for two kids and a large dog. Hybrid would be nice. Budget around {budget}.',
                    'Growing family, outgrowing their sedan. Wants 7 or 8 seats and good safety ratings, max {budget}.',
                    'Looking at minivans vs 3-row SUVs. Sliding doors are a big plus. Around {budget}.',
                ],
                'notes' => [
                    'Expecting a third child in the spring, so wants 8 seats or captain\'s chairs with a bench in the third row.',
                    'Spouse does most of the driving and wants a power liftgate and heated seats.',
                    'Tows a small pop-up camper, roughly 1,800 lb, a couple of times each summer.',
                    'Concerned about third-row legroom. Their teenager is already 6 feet tall.',
                    'Asked whether two rear-facing car seats fit side by side in the second row.',
                ],
            ],
            'commuter' => [
                'bodies' => ['sedan', 'hatchback', 'suv'],
                'powertrains' => ['hybrid', 'gas', 'ev'],
                'budget' => [28000, 42000],
                'interest' => [
                    'Something efficient for a 90 km daily round trip on the 401. Under {budget}.',
                    'Commutes from Oshawa to downtown, wants the best fuel economy we have under {budget}.',
                ],
                'notes' => [
                    'Spends about $400 a month on gas right now and wants that cut in half.',
                    'Parks on the street, so home charging is not an option. Leaning hybrid over EV.',
                    'Wants adaptive cruise for stop-and-go traffic on the 401.',
                    'Would consider a lease if the payment stays under $550 a month.',
                ],
            ],
            'contractor' => [
                'bodies' => ['truck'],
                'powertrains' => ['gas', 'ev', 'hybrid'],
                'budget' => [55000, 85000],
                'interest' => [
                    'Needs a crew cab that can tow a 7,000 lb equipment trailer. Business purchase, up to {budget}.',
                    'Landscaping company, replacing two older trucks. Towing and payload matter most. Around {budget}.',
                ],
                'notes' => [
                    'Tows a 7,000 lb skid steer on a tandem trailer most weekdays.',
                    'Buying through the company. Asked about commercial leasing and fleet pricing.',
                    'Interested in the onboard generator so the crew can run tools on site.',
                    'Needs a spray-in bedliner and a tonneau cover installed before delivery.',
                ],
            ],
            'ev_curious' => [
                'bodies' => ['suv', 'sedan', 'truck'],
                'powertrains' => ['ev', 'phev'],
                'budget' => [50000, 75000],
                'interest' => [
                    'Ready to go electric. Has a garage. Wants at least 400 km of range, budget {budget}.',
                    'Curious about EVs but worried about winter range. Around {budget}.',
                ],
                'notes' => [
                    'Has a garage and an electrician quoting a Level 2 charger install.',
                    'Worried about losing range in January. Explained the heat pump and preconditioning.',
                    'Drives to Ottawa about once a month, roughly 450 km each way.',
                    'Asked about the federal and provincial EV rebates. Told them to confirm current eligibility.',
                ],
            ],
            'first_car' => [
                'bodies' => ['sedan', 'hatchback', 'suv'],
                'powertrains' => ['gas', 'hybrid'],
                'budget' => [18000, 30000],
                'interest' => [
                    'First car for their daughter starting at university in September. Safety first, max {budget}.',
                    'New grad buying their first car. Wants something reliable and cheap to insure, under {budget}.',
                ],
                'notes' => [
                    'Parents are co-signing. Insurance cost is the biggest worry.',
                    'Prefers certified pre-owned for the extended warranty.',
                    'Asked about winter tires being included in the deal.',
                ],
            ],
            'snow_belt' => [
                'bodies' => ['suv', 'wagon'],
                'powertrains' => ['gas', 'hybrid'],
                'budget' => [32000, 50000],
                'interest' => [
                    'Lives north of Barrie. Needs AWD and good ground clearance for winter, budget {budget}.',
                    'Drives to the cottage every weekend year round. AWD is non-negotiable. Around {budget}.',
                ],
                'notes' => [
                    'Their private road does not get plowed until mid-morning. Ground clearance matters.',
                    'Wants a block heater and remote start.',
                    'Tows a 2,000 lb aluminium fishing boat to the lake in summer.',
                ],
            ],
            'weekend_fun' => [
                'bodies' => ['coupe', 'hatchback'],
                'powertrains' => ['gas'],
                'budget' => [30000, 50000],
                'interest' => [
                    'Already has a daily driver. Wants a fun summer car, up to {budget}.',
                ],
                'notes' => [
                    'Only plans to drive it April to October. Will store it over winter.',
                    'Wants a manual transmission if we can find one.',
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function followUps(): array
    {
        return [
            'Left a voicemail, no answer.',
            'Emailed a pricing sheet and two similar units from inventory.',
            'Customer asked about current finance rates on new models.',
            'Wants to keep the monthly payment under $650.',
            'Spouse needs to see the vehicle before they commit.',
            'Shopping us against a dealer in Mississauga. Price-sensitive.',
            'Asked us to hold the vehicle until the weekend. Explained the deposit policy.',
            'Sent a text with the walkaround video. They replied with a thumbs up.',
            'Asked what extended warranty options we offer.',
            'Rescheduled the visit because of a work trip.',
        ];
    }

    /**
     * @return list<string>
     */
    public static function tradeIns(): array
    {
        return [
            '2013 Honda Civic, 210,000 km',
            '2016 Mazda5, 145,000 km',
            '2015 Ford Escape, 160,000 km',
            '2018 Toyota Camry, 98,000 km',
            '2012 Dodge Grand Caravan, 230,000 km',
            '2019 Hyundai Kona, 72,000 km',
            '2017 Chevrolet Silverado, 180,000 km',
            '2014 Subaru Forester, 175,000 km',
        ];
    }
}

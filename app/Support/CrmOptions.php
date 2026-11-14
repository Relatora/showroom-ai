<?php

namespace App\Support;

use App\Enums\ActivityType;
use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\BodyStyle;
use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Enums\LeadTemperature;
use App\Enums\Powertrain;
use App\Enums\VehicleCondition;
use App\Enums\VehicleStatus;

/**
 * Every enum the UI needs, in one shared Inertia prop.
 *
 * The frontend never hard-codes a stage name or a colour. If we add a pipeline stage in PHP,
 * the board grows a column without touching TypeScript.
 */
class CrmOptions
{
    /**
     * @return array<string, list<array{value: string, label: string, tone: string}>>
     */
    public static function all(): array
    {
        return [
            'stages' => LeadStage::options(),
            'temperatures' => LeadTemperature::options(),
            'sources' => LeadSource::options(),
            'activityTypes' => ActivityType::options(),
            'bodyStyles' => BodyStyle::options(),
            'powertrains' => Powertrain::options(),
            'vehicleConditions' => VehicleCondition::options(),
            'vehicleStatuses' => VehicleStatus::options(),
            'appointmentTypes' => AppointmentType::options(),
            'appointmentStatuses' => AppointmentStatus::options(),
        ];
    }
}

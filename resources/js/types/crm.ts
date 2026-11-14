/**
 * Shapes of the CRM data the server sends. These mirror the API resources in
 * app/Http/Resources, so if you add a field there, add it here too.
 */

export type Tone =
    | 'primary'
    | 'info'
    | 'success'
    | 'warning'
    | 'destructive'
    | 'muted';

export type Option<T extends string = string> = {
    value: T;
    label: string;
    tone: Tone;
};

export type LeadStage =
    | 'new'
    | 'contacted'
    | 'appointment'
    | 'test_drive'
    | 'negotiation'
    | 'won'
    | 'lost';
export type LeadTemperature = 'hot' | 'warm' | 'cold';
export type ActivityType =
    | 'note'
    | 'call'
    | 'email'
    | 'sms'
    | 'meeting'
    | 'stage_change'
    | 'test_drive';

export type CrmOptions = {
    stages: Option<LeadStage>[];
    temperatures: Option<LeadTemperature>[];
    sources: Option[];
    activityTypes: Option<ActivityType>[];
    bodyStyles: Option[];
    powertrains: Option[];
    vehicleConditions: Option[];
    vehicleStatuses: Option[];
    appointmentTypes: Option[];
    appointmentStatuses: Option[];
};

export type UserSummary = {
    id: number;
    name: string;
    title: string | null;
    role: 'rep' | 'manager' | 'bdc';
};

export type Customer = {
    id: number;
    name: string;
    first_name: string;
    last_name: string;
    email: string | null;
    phone: string | null;
    city: string | null;
    province: string | null;
    preferred_contact: string;
    customer_since: string | null;
};

export type Vehicle = {
    id: number;
    title: string;
    stock_number: string;
    vin: string;
    year: number;
    make: string;
    model: string;
    trim: string | null;
    body_style: string;
    powertrain: string;
    drivetrain: string;
    drivetrain_label: string;
    seats: number;
    exterior_color: string;
    exterior_hex: string;
    interior_color: string;
    km: number;
    price: number;
    condition: string;
    status: string;
    towing_lb: number | null;
    range_km: number | null;
    fuel_l_per_100km: number | null;
    features: string[];
    description: string;
    days_on_lot: number;
    leads_count?: number;
};

export type Activity = {
    id: number;
    type: ActivityType;
    body: string;
    meta: Record<string, unknown> | null;
    occurred_at: string;
    user?: UserSummary | null;
};

export type Appointment = {
    id: number;
    type: string;
    status: string;
    starts_at: string;
    ends_at: string;
    notes: string | null;
    lead_id: number;
    lead?: Lead;
    user?: UserSummary;
    vehicle?: Vehicle | null;
};

export type Lead = {
    id: number;
    stage: LeadStage;
    temperature: LeadTemperature;
    source: string;
    budget_max: number | null;
    interest: string;
    trade_in: string | null;
    position: number;
    is_stale: boolean;
    last_contacted_at: string | null;
    next_follow_up_at: string | null;
    closed_at: string | null;
    lost_reason: string | null;
    created_at: string | null;
    customer?: Customer;
    assignee?: UserSummary | null;
    vehicle?: Vehicle | null;
    activities?: Activity[];
    appointments?: Appointment[];
    can: { update: boolean; reassign: boolean };
};

/** Laravel's paginator, as serialised by an API resource collection. */
export type Paginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        per_page: number;
    };
};

<?php

namespace Merciall\Docket;

use App\Models\Appointment;
use App\Models\Period;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Merciall\Docket\Alert\Alert;
use Merciall\Docket\Determine\Types\DetermineMessageState;
use Merciall\Docket\Determine\Types\DetermineTripState;
use Merciall\Docket\Determine\Types\DetermineAppointmentState;
use Merciall\Docket\Determine\Types\DeterminePeriodState;
use Merciall\Docket\Notify\Notify;
use Merciall\Docket\SMS\SMS;
use Merciall\Docket\States\States;

class Docket
{
    protected const HOURS_PER_PERIOD = 24; // Max number of hours in a period

    protected const HOURS_PER_PERIOD_OFFSET = 0; // Number of hours after an appointment will Docket

    protected const LEAD_PERIODS = 3; // Number of periods before first appointment

    protected const IGNORE_TRIGGER_IN_HOURS = 3; // Number of hours to pass before an initial notification is considered ignored

    public static function SMS(Request|Model $target = null): SMS
    {
        return new SMS($target);
    }

    public static function States(): States
    {
        return new States;
    }

    public static function Notify(): Notify
    {
        return new Notify;
    }

    public static function Alert(): Alert
    {
        return new Alert;
    }

    public static function Determine(Model $target, string $message = null, string $from = null): object
    {
        if ($message) {
            return (new DetermineMessageState($target, $message, $from))();
        }

        if ($target instanceof Trip) {
            return (new DetermineTripState($target))();
        }

        if ($target instanceof Appointment) {
            return (new DetermineAppointmentState($target))();
        }

        if ($target instanceof Period) {
            return (new DeterminePeriodState($target))();
        }
    }
}

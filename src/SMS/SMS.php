<?php

namespace Merciall\Docket\SMS;

use App\Http\Controllers\AppointmentsController;
use App\Http\Controllers\CarrierNotificationsController;
use App\Http\Controllers\NotifyController;
use App\Models\Appointment;
use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Merciall\Docket\Docket;

class SMS
{
    public function __construct(
        Request|Model|null $request = null,
        protected ?string $text = null,
        protected ?string $msisdn = null,
        protected ?string $message = null,
        protected ?string $from = null,
        protected ?string $to = null,
        private ?string $state_name = null,
        protected ?string $appointment_id = null,
        protected ?Carbon $now = null,
        protected ?Trip $trip = null,
        protected ?Appointment $appointment = null,
    ) {
        if ($request instanceof Request) {
            $this->message = $request->text;
            $this->from = $request->msisdn;
            $this->to = $request->to;
        }
        if ($request instanceof Model) {
            $this->model($request);
        }
        $this->$appointment_id = $appointment_id;
        $this->now = Carbon::now();
    }

    public function determine(): object
    {
        return Docket::Determine($this->trip, $this->message, $this->from);
    }

    public function model(Trip|Appointment $model)
    {
        if ($model instanceof Appointment) {
            $this->trip = $model->trip;
            $this->appointment = $model;
            return $this;
        }
        $this->trip = $model;
        return $this;
    }

    public function stateOptions(): array
    {
        if (!$this->from) {
            $this->from();
        }
        $from = $this->from;
        if ($this->from !== 'Docket' || $this->from !== 'User') {
            $from = 'Carrier';
        }
        return Docket::States()->collection()->where("from$from")->pluck('name')->toArray();
    }

    public function state(string $state_name)
    {
        if (!$this->from) {
            $this->from();
        }
        $from = $this->from;
        if ($this->from !== 'Docket' || $this->from !== 'User') {
            $from = 'Carrier';
        }
        $this->state = Docket::States()->collection()->where('name', $state_name)->first();
        $prop = "from$from";
        if (!$this->state->$prop) {
            throw new Exception("State must be of type from$from");
        }
        $this->message = $this->getMessage($this->state->message);
        return $this;
    }

    public function message(?string $message)
    {
        $this->message = $message;
        return $this;
    }

    public function to(?string $to)
    {
        $this->to = $to;
        return $this;
    }

    public function from(string $from = 'Docket')
    {
        $this->from = $from;
        return $this;
    }

    public function send(): void
    {
        if (!$this->from) {
            $this->from();
        }

        if (!$this->isPhoneNumber($this->to)) {
            report(new Exception('In order for Docket to send an SMS, a value for to must be given as a string. You can provide that value by calling ->to() before calling send. You can also send a Appointment model by calling ->model().'));
            return;
        }

        if ($this->appointment) {
            if ($this->checkIfMsgExists($this->appointment->id)) return;
        }

        try {
            $respond = (new NotifyController)->viaNexmo($this->trip->driver_phone, $this->message);
        } catch (Exception $ex) {
            echo "- " . Carbon::now()->format('H:i:s') . " SMS message could not be sent to driver<br>";
            echo $ex->getMessage();
        }

        if ($respond) {
            (new CarrierNotificationsController)->save([
                'appointment_id' => $this->appointment->id,
                'type' => 1,
                'carrier_id' => $this->appointment->trip->carrier->id,
                'source_type' => 1,
                'source' => $this->trip->driver_phone,
                'message' => $this->message,
                'send_from' => $this->from,
            ]);
        }
    }

    private function isPhoneNumber(?string $number)
    {
        if (!$number) {
            return false;
        }
        if (strlen($number) < 11 || $number[0] !== '1') {
            return false;
        }
        return true;
    }

    public function receive(): void
    {
        if (!$this->from) {
            throw new Exception("No msisdn provided. Docket can't attached sms to a logged item.");
        }

        $appointment = $this->getAppointment();

        $this->model($appointment);

        if ($this->checkIfMsgExists($this->message)) return;

        $determination = $this->determine();

        $determination->response = $this->getMessage($determination->response);

        $notification = (new CarrierNotificationsController)->save([
            'appointment_id' => $appointment->id,
            'type' => $determination->code,
            'carrier_id' => $appointment->trip->carrier->id,
            'source_type' => 1,
            'source' => $this->from,
            'message' => $this->message
        ]);

        sleep(1);

        if ($determination->code == 8) return;

        try {
            (new NotifyController)->viaEmail($notification);
        } catch (Exception $ex) {
            echo "- " . Carbon::now()->format('H:i:s') . " Email could not be sent to tenant<br>";
            echo $ex->getMessage();
        }

        if ($this->checkIfMsgExists($determination->response)) {
            echo "- " . Carbon::now()->format('H:i:s') . " Docket has already responded, <b>aborting...</b><br>";
            return;
        }

        try {
            $respond = (new NotifyController)->viaNexmo($this->from, $determination->Docket_response);
        } catch (Exception $ex) {
            echo "- " . Carbon::now()->format('H:i:s') . " SMS message could not be sent to driver<br>";
            echo $ex->getMessage();
        }

        if ($respond) {
            (new CarrierNotificationsController)->save([
                'appointment_id' => $appointment->id,
                'type' => 1,
                'carrier_id' => $appointment->trip->carrier->id,
                'source_type' => 1,
                'source' => $this->from,
                'message' => $determination->response
            ]);
        }
    }

    public function getMessage(string $string): string
    {
        if (strlen($string) == 0) {
            return $string;
        }
        $sections = explode("[", $string);
        $string = "";
        foreach ($sections as $section) {
            if (strlen($section) == 0) {
                continue;
            }
            $exploded = explode("]", $section);
            $instruction = $exploded[0];
            $restOfString = $exploded[1] ?? "";
            if ($instruction == "greeting") {
                $string .= $this->getGreeting() . $restOfString;
                continue;
            }
            $magik = $this->trip;
            $properties = explode('.',  $instruction);
            foreach ($properties as $key => $property) {
                if ($key !== 0 && str_starts_with($properties[$key - 1], 'datetime') && $property == 'format' && isset($properties[$key + 1])) {
                    $magik = $magik->$property($properties[$key + 1]);
                    break;
                }
                if ($property == 'dock') {
                    $magik = $magik->next_appointment;
                }
                $magik = $magik->$property;
            }
            $string .= $magik . $restOfString;
        }
        return $string;
    }

    private function getGreeting(): string
    {
        $now = Carbon::now()->timezone($this->trip->dock->timezone ?? 'EST')->format("H");;
        if ($now >= 3 && $now < 12) return 'Morning';
        if ($now >= 12 && $now < 18) return 'Afternoon';
        return 'Evening';
    }

    private function checkIfMsgExists(string $message = null, string $source = null): bool
    {
        return (new CarrierNotificationsController)->checkIfMsgExists(
            $this->appointment->id,
            $message ?? $this->message,
            $source ?? $this->from,
            $this->now
        );
    }

    private function getAppointment(): ?Appointment
    {
        if ($this->appointment_id) {
            return (new AppointmentsController)->show((int) $this->appointment_id);
        }

        $identifier = (new CarrierNotificationsController)->findByPhone($this->from, $this->now);

        if (!$identifier) {
            throw new Exception("Docket was unable to attach sms to a logged item.");
        }

        return (new AppointmentsController)->show($identifier->appointment_id);
    }
}

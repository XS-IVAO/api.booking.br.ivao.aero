<?php

namespace App\Policies;

use App\Models\Slot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\Response;

class SlotPolicy
{
    public function create(User $user)
    {
        if (!$user->admin) {
            return Response::deny("admin.noAdmin");
        }

        return true;
    }

    public function delete(User $user)
    {
        if (!$user->admin) {
            return Response::deny("admin.noAdmin");
        }

        return true;
    }

    public function bookUpdate(User $user, Slot $slot, string $action)
    {

        if ($user->suspended) {
            return Response::deny('book.suspended');
        }

        if (!is_null($slot->pilotId) && $slot->pilotId !== $user->id) {
            return Response::deny("book.notOwner");
        }

        /** @var \App\Models\Event */
        $slotEvent = $slot->event;

        if($slotEvent->status !== 'scheduled') {
            return Response::deny("book.notActive");
        }

        /** @var \Carbon\Carbon */
        $eventEndDate = $slotEvent->dateEnd;
        $now = Carbon::now();

        if ($slot->bookingStatus === "prebooked" && $action === "confirm") {
            /** @var \Carbon\Carbon */
            $eventStartDate = $slotEvent->dateStart;
            $diffFromStart = $now->diffInDays($eventStartDate, false);

            $maxDaysBeforeEvent = config('app.slot.days_before_event_to_confirm');

            if ($eventStartDate->greaterThan($now) && $diffFromStart > $maxDaysBeforeEvent) {
                return Response::deny("book.tooEarly.$maxDaysBeforeEvent");
            }
        } else if($slot->bookingStatus === "free" && $action === "book") {
            //If the user has a slot for the same event and at the same time of the one they are trying to book
            //TODO: For some reason, i had to use count() > 0 because the empty object was not being recognized as false. Maybe there is a better way of doing that?
            if($user->slotsBooked->where('eventId', $slot->event->id)->where('slotTime', $slot->slotTime)->count() > 0){
                return Response::deny('book.alreadyBusy');
            }
        }

        if ($now->greaterThan($eventEndDate)) {
            return Response::deny("book.hasEnded");
        }

        return true;
    }

    public function update(User $user)
    {
        if (!$user->admin) {
            return Response::deny("admin.noAdmin");
        }

        return true;
    }
}

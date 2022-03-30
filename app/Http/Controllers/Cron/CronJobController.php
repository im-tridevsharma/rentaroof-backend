<?php

namespace App\Http\Controllers\Cron;

use App\Events\AdminNotificationSent;
use App\Events\NotificationSent;
use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\IboNotification;
use App\Models\Meeting;
use App\Models\Property;
use App\Models\User;

class CronJobController extends Controller
{
    //check for appointment
    public function _check_for_appointment()
    {
        $meetings = Meeting::where("meeting_status", "pending")
            ->orWhere("landlord_status", "pending")
            ->get();

        $landlord_notify_time = env('LANDLORD_APPOINTMENT_NOTIFY_TIME');
        $agent_notify_time = env('AGENT_APPOINTMENT_NOTIFY_TIME');

        foreach ($meetings as $meeting) {
            $property = Property::find($meeting->property_id);
            if ($property) {
                $agent = User::find($meeting->user_id);

                $diff = round((strtotime(date('Y-m-d')) - strtotime($meeting->created_at)) / 3600);

                if ($diff >= $landlord_notify_time) {
                    //notify to agent
                    $ibo_notify = new IboNotification;
                    $ibo_notify->ibo_id = $agent->id;
                    $ibo_notify->type = 'Urgent';
                    $ibo_notify->title = 'Recent Appointment Status';
                    $ibo_notify->content = 'You have new appointment for property - ' . $property->property_code . '. Scheduled at ' . date('d-m-Y H:i', strtotime($meeting->start_time)) . '. Appointment is not accepted by landlord yet. Please contact him.';
                    $ibo_notify->name = 'Rent A Roof';
                    $ibo_notify->redirect = '/ibo/appointment';

                    $ibo_notify->save();

                    event(new NotificationSent($ibo_notify));
                }

                if ($diff >= $agent_notify_time) {
                    //notify to admin
                    $an = new AdminNotification;
                    $an->content = 'New meeting request for property - ' . $property->property_code . '. No any agent has accepted yet.';
                    $an->type  = 'Urgent';
                    $an->title = 'New Meeting Request Status';
                    $an->redirect = '/admin/meetings/' . $meeting->id;
                    $an->save();

                    event(new AdminNotificationSent($an));
                }
            }
        }
    }
}

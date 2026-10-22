<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Holiday;
use App\Models\Utility;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class HolidayApiController extends Controller
{
    use ApiResponser;

    public function getHolidays()
    {
        $holidays = Holiday::where('created_by', Auth::user()->creatorId())
            ->orderBy('date', 'asc')
            ->get();

        return $this->success(['holidays' => $holidays], 'Holidays retrieved.');
    }

    public function getHolidaysByDateRange(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $holidays = Holiday::where('created_by', Auth::user()->creatorId());

        if (!empty($request->start_date)) {
            $holidays->where('date', '>=', $request->start_date);
        }

        if (!empty($request->end_date)) {
            $holidays->where('date', '<=', $request->end_date);
        }

        $holidays = $holidays->orderBy('date', 'asc')->get();

        return $this->success(['holidays' => $holidays], 'Holidays retrieved by date range.');
    }

    public function createHoliday(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:date',
            'occasion' => 'required|string|max:255',
            'synchronize_type' => 'nullable|string|in:google_calender',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $holiday = new Holiday();
        $holiday->date = $request->date;
        $holiday->end_date = $request->end_date;
        $holiday->occasion = $request->occasion;
        $holiday->created_by = Auth::user()->creatorId();
        $holiday->save();

        // Send notifications
        $setting = Utility::settings(Auth::user()->creatorId());
        $holidayNotificationArr = [
            'holiday_title' => $request->occasion,
            'holiday_date' => $request->date,
        ];

        // Slack Notification
        if (isset($setting['holiday_notification']) && $setting['holiday_notification'] == 1) {
            Utility::send_slack_msg('new_holiday', $holidayNotificationArr);
        }

        // Telegram Notification
        if (isset($setting['telegram_holiday_notification']) && $setting['telegram_holiday_notification'] == 1) {
            Utility::send_telegram_msg('new_holiday', $holidayNotificationArr);
        }

        // Google Calendar Integration
        if ($request->get('synchronize_type') == 'google_calender') {
            $type = 'holiday';
            $request1 = new Holiday();
            $request1->title = $request->occasion;
            $request1->start_date = $request->date;
            $request1->end_date = $request->end_date;

            Utility::addCalendarData($request1, $type);
        }

        // Webhook
        $module = 'New Holiday';
        $webhook = Utility::webhookSetting($module);
        if ($webhook) {
            $parameter = json_encode($holiday);
            $status = Utility::WebhookCall($webhook['url'], $parameter, $webhook['method']);
        }

        return $this->success(['holiday' => $holiday], 'Holiday created successfully.');
    }

    public function getHoliday($id)
    {
        $holiday = Holiday::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$holiday) {
            return $this->error('Holiday not found.', 404);
        }

        return $this->success(['holiday' => $holiday], 'Holiday detail.');
    }

    public function updateHoliday(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:date',
            'occasion' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $holiday = Holiday::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$holiday) {
            return $this->error('Holiday not found.', 404);
        }

        $holiday->date = $request->date;
        $holiday->end_date = $request->end_date;
        $holiday->occasion = $request->occasion;
        $holiday->save();

        return $this->success(['holiday' => $holiday], 'Holiday updated successfully.');
    }

    public function deleteHoliday($id)
    {
        $holiday = Holiday::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$holiday) {
            return $this->error('Holiday not found.', 404);
        }

        $holiday->delete();

        return $this->success([], 'Holiday deleted successfully.');
    }

    public function getCalendarHolidays(Request $request)
    {
        $holidays = Holiday::where('created_by', Auth::user()->creatorId());

        if (!empty($request->start_date)) {
            $holidays->where('date', '>=', $request->start_date);
        }

        if (!empty($request->end_date)) {
            $holidays->where('date', '<=', $request->end_date);
        }

        $holidays = $holidays->get();

        $calendarEvents = [];

        foreach ($holidays as $holiday) {
            $calendarEvents[] = [
                'id'        => $holiday->id,
                'title'     => $holiday->occasion,
                'start'     => $holiday->date,
                'end'       => $holiday->end_date,
                'className' => 'event-primary',
                'allDay'    => true,
            ];
        }

        return $this->success(['events' => $calendarEvents], 'Calendar holidays retrieved.');
    }

    public function getGoogleCalendarHolidays(Request $request)
    {
        if ($request->get('calender_type') == 'google_calender') {
            try {
                $type = 'holiday';
                $arrayJson = Utility::getCalendarData($type);
            } catch (\Exception $e) {
                return $this->error('Google Calendar is not configured. Please set up Google Calendar integration in settings.', 503);
            }
        } else {
            $data = Holiday::where('created_by', Auth::user()->creatorId())->get();

            $arrayJson = [];
            foreach ($data as $val) {
                $end_date = date_create($val->end_date);
                date_add($end_date, date_interval_create_from_date_string("1 days"));
                $arrayJson[] = [
                    "id"        => $val->id,
                    "title"     => $val->occasion,
                    "start"     => $val->date,
                    "end"       => date_format($end_date, "Y-m-d H:i:s"),
                    "className" => 'event-primary',
                    "textColor" => '#51459d',
                    "allDay"    => true,
                ];
            }
        }

        return $this->success(['events' => $arrayJson], 'Google calendar holidays retrieved.');
    }

    public function getUpcomingHolidays()
    {
        $today = date('Y-m-d');
        $holidays = Holiday::where('created_by', Auth::user()->creatorId())
            ->where('date', '>=', $today)
            ->orderBy('date', 'asc')
            ->limit(10)
            ->get();

        return $this->success(['holidays' => $holidays], 'Upcoming holidays retrieved.');
    }

    public function getHolidayStats()
    {
        $currentYear = date('Y');
        $holidays = Holiday::where('created_by', Auth::user()->creatorId())
            ->whereYear('date', $currentYear)
            ->get();

        $totalHolidays = $holidays->count();
        $pastHolidays = $holidays->where('end_date', '<', date('Y-m-d'))->count();
        $upcomingHolidays = $holidays->where('date', '>', date('Y-m-d'))->count();
        $currentHolidays = $holidays->where('date', '<=', date('Y-m-d'))
            ->where('end_date', '>=', date('Y-m-d'))
            ->count();

        return $this->success([
            'total_holidays' => $totalHolidays,
            'past_holidays' => $pastHolidays,
            'upcoming_holidays' => $upcomingHolidays,
            'current_holidays' => $currentHolidays,
            'year' => $currentYear
        ], 'Holiday statistics retrieved.');
    }
}

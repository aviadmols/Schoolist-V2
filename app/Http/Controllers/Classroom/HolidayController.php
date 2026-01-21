<?php

namespace App\Http\Controllers\Classroom;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Holiday;
use App\Services\Classroom\HolidayService;
use App\Services\Audit\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class HolidayController extends Controller
{
    /** @var HolidayService */
    private HolidayService $holidayService;

    /** @var AuditService */
    private AuditService $auditService;

    public function __construct(HolidayService $holidayService, AuditService $auditService)
    {
        $this->holidayService = $holidayService;
        $this->auditService = $auditService;
    }

    /**
     * Display a listing of upcoming holidays.
     */
    public function index(Request $request): Response
    {
        $classroom = $request->attributes->get('current_classroom');

        return Inertia::render('Holidays', [
            'holidays' => $this->holidayService->getUpcomingHolidays($classroom),
        ]);
    }

    /**
     * Store a newly created holiday.
     */
    public function store(Request $request): RedirectResponse
    {
        $classroom = $request->attributes->get('current_classroom');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $holiday = Holiday::create(array_merge($data, [
            'classroom_id' => $classroom->id,
        ]));

        $this->auditService->log('holiday.created', $holiday, null, $holiday->toArray(), $classroom->id);

        return back()->with('success', 'Holiday added.');
    }

    /**
     * Update the specified holiday.
     */
    public function update(Request $request, Holiday $holiday): RedirectResponse
    {
        $classroom = $request->attributes->get('current_classroom');

        if ($holiday->classroom_id !== $classroom->id) {
            abort(403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $oldValues = $holiday->toArray();
        $holiday->update($data);

        $this->auditService->log('holiday.updated', $holiday, $oldValues, $holiday->toArray(), $classroom->id);

        return back()->with('success', 'Holiday updated.');
    }

    /**
     * Remove the specified holiday.
     */
    public function destroy(Request $request, Holiday $holiday): RedirectResponse
    {
        $classroom = $request->attributes->get('current_classroom');

        if ($holiday->classroom_id !== $classroom->id) {
            abort(403);
        }

        $this->auditService->log('holiday.deleted', $holiday, $holiday->toArray(), null, $classroom->id);
        $holiday->delete();

        return back()->with('success', 'Holiday removed.');
    }
}

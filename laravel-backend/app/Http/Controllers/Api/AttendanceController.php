<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\AuthorizesStaff;
use App\Models\AttendanceRecord;
use Illuminate\Http\Request;
use Modules\Attendance\Services\AttendanceDomainService;

class AttendanceController extends Controller
{
    use AuthorizesStaff;

    public function __construct(private readonly AttendanceDomainService $attendance)
    {
    }

    public function index(Request $request)
    {
        $query = $this->attendance->query();
        $this->scope($query, $request);

        return $query->paginate(50);
    }

    public function store(Request $request)
    {
        $this->authorizeStaff($request);
        $data = $request->validate([
            'student_id' => 'required|exists:students,id',
            'date_at' => 'required|date',
            'status' => 'required|in:present,absent,late',
            'note' => 'nullable|string',
        ]);

        return response()->json($this->attendance->create($data, $request->user()->id), 201);
    }

    public function scan(Request $request)
    {
        $this->authorizeStaff($request);
        $payload = $request->validate(['payload' => 'required|string|min:32|max:96'])['payload'];
        $result = $this->attendance->scan($payload, $request->user()->id);

        return response()->json($result, $result['already_recorded'] ? 200 : 201);
    }

    public function update(Request $request, AttendanceRecord $attendance)
    {
        $this->authorizeStaff($request);
        $data = $request->validate([
            'date_at' => 'sometimes|date',
            'status' => 'sometimes|in:present,absent,late',
            'note' => 'nullable|string',
        ]);

        return $this->attendance->update($attendance, $data);
    }

    public function destroy(Request $request, AttendanceRecord $attendance)
    {
        $this->authorizeStaff($request);
        $this->attendance->delete($attendance);

        return response()->noContent();
    }

    private function scope($query, Request $request): void
    {
        $account = $request->user()->studentAccount;
        if ($request->user()->isAnyRole('student', 'parent')) {
            abort_unless($account, 403);
            $query->where('student_id', $account->student_id);
        }
    }
}

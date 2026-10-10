<?php

namespace Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Payments\Models\Payment;
use Modules\Payments\Services\OnlinePayments;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Paying a payment online (Fawry reference number or Vodafone Cash). Staff
 * can start it for any student; a student or parent only for their own.
 */
class OnlinePaymentController extends Controller
{
    public function __construct(private readonly OnlinePayments $online)
    {
    }

    /** Starts (or returns the open) online payment for [$payment]. */
    public function store(Request $request, Payment $payment)
    {
        $this->authorizeFor($request, $payment);
        $data = $request->validate([
            'method' => ['required', Rule::in(OnlinePayments::METHODS)],
            'wallet_mobile' => ['nullable', 'string', 'max:20'],
            'customer_mobile' => ['nullable', 'string', 'max:20'],
        ]);

        $attempt = $this->online->start(
            $payment->loadMissing('student'),
            $data['method'],
            $data['wallet_mobile'] ?? null,
            $data['customer_mobile'] ?? null,
            $request->user(),
        );

        return response()->json($attempt->toPayload(), $attempt->wasRecentlyCreated ? 201 : 200);
    }

    /** The latest online attempt, checked with Fawry first when still open. */
    public function show(Request $request, Payment $payment)
    {
        $this->authorizeFor($request, $payment);
        $attempt = $payment->attempts()->latest('id')->first();
        abort_unless($attempt, 404, 'لا توجد محاولة دفع إلكتروني لهذه الدفعة.');

        return response()->json([
            ...$this->online->refresh($attempt)->toPayload(),
            'payment_status' => $payment->fresh()->status,
        ]);
    }

    private function authorizeFor(Request $request, Payment $payment): void
    {
        $user = $request->user();
        if ($user->isAnyRole('admin', 'teacher')) {
            return;
        }
        abort_unless($user->isAnyRole('student', 'parent'), 403);
        abort_unless($user->studentAccount?->student_id === $payment->student_id, 403);
    }
}

<?php

namespace Modules\Payments\Tests\Feature;

use Modules\Payments\Models\Payment;
use Modules\Payments\Models\PaymentAttempt;
use Modules\Students\Models\Student;
use Modules\Students\Models\StudentAccount;
use Modules\Auth\Models\User;
use Modules\Payments\Services\FawrySigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OnlinePaymentsTest extends TestCase
{
    use RefreshDatabase;

    private const CODE = 'MERCHANT1';

    private const KEY = 'secure-key-for-tests';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.fawry.merchant_code' => self::CODE,
            'services.fawry.secure_key' => self::KEY,
            'services.fawry.base_url' => 'https://atfawry.fawrystaging.com',
            'services.fawry.webhook_url' => 'https://school.example/api/webhooks/fawry',
        ]);
    }

    /** @return array{0: Student, 1: User, 2: Payment} a student, their parent, and 450 EGP due */
    private function family(string $parentPhone = '01012345678'): array
    {
        $student = Student::factory()->create(['parent_phone' => $parentPhone, 'phone' => $parentPhone]);
        $parent = User::factory()->create(['role' => 'parent']);
        StudentAccount::create(['user_id' => $parent->id, 'student_id' => $student->id, 'relationship' => 'parent']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $payment = Payment::create([
            'student_id' => $student->id, 'amount' => 450, 'status' => 'pending',
            'due_at' => '2026-10-01', 'recorded_by' => $teacher->id,
        ]);

        return [$student, $parent, $payment];
    }

    private function fakeFawry(): void
    {
        Http::fake([
            '*/ECommerceWeb/Fawry/payments/charge' => Http::response(['statusCode' => 200, 'statusDescription' => 'Operation done successfully', 'referenceNumber' => '9900112233']),
            '*/ECommerceWeb/api/payments/charge' => Http::response(['statusCode' => 200, 'statusDescription' => 'Operation done successfully', 'referenceNumber' => '7700112233']),
        ]);
    }

    private function signer(): FawrySigner
    {
        return new FawrySigner(self::CODE, self::KEY);
    }

    /** A Server To Server Notification V2, signed the way Fawry signs it. */
    private function notification(PaymentAttempt $attempt, string $status = 'PAID', float $paid = 450.0): array
    {
        $body = [
            'requestId' => 'req-1', 'fawryRefNumber' => '9900112233', 'merchantRefNumber' => $attempt->merchant_ref,
            'paymentAmount' => $paid, 'orderAmount' => 450.0, 'orderStatus' => $status,
            'paymentMethod' => 'PAYATFAWRY', 'paymentRefrenceNumber' => '555', 'fawryFees' => 3.0,
        ];

        return $body + ['messageSignature' => $this->signer()->notification($body)];
    }

    public function test_a_parent_gets_a_fawry_reference_number_and_a_double_tap_reuses_it(): void
    {
        $this->fakeFawry();
        [, $parent, $payment] = $this->family();
        Sanctum::actingAs($parent);

        $first = $this->postJson("/api/payments/{$payment->id}/online", ['method' => 'fawry'])
            ->assertCreated()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('method', 'fawry')
            ->assertJsonPath('amount', 450)
            ->assertJsonPath('reference_number', '9900112233');
        $this->postJson("/api/payments/{$payment->id}/online", ['method' => 'fawry'])
            ->assertOk()->assertJsonPath('id', $first->json('id'));

        $this->assertSame(1, PaymentAttempt::query()->count());
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            $ref = $request['merchantRefNum'];

            return $request->url() === 'https://atfawry.fawrystaging.com/ECommerceWeb/Fawry/payments/charge'
                && $request['merchantCode'] === self::CODE
                && $request['paymentMethod'] === 'PAYATFAWRY'
                && $request['amount'] === '450.00'
                && $request['customerMobile'] === '01012345678'
                && $request['language'] === 'ar-eg'
                && $request['chargeItems'][0]['price'] === '450.00'
                && $request['signature'] === hash('sha256', self::CODE.$ref.''.'PAYATFAWRY'.'450.00'.self::KEY);
        });
    }

    public function test_vodafone_cash_asks_the_wallet_and_needs_a_vodafone_number(): void
    {
        $this->fakeFawry();
        [, $parent, $payment] = $this->family();
        Sanctum::actingAs($parent);
        $url = "/api/payments/{$payment->id}/online";

        $this->postJson($url, ['method' => 'vodafone_cash'])->assertStatus(422)->assertJsonValidationErrors('wallet_mobile');
        // 011 is Etisalat, not Vodafone.
        $this->postJson($url, ['method' => 'vodafone_cash', 'wallet_mobile' => '01112345678'])
            ->assertStatus(422)->assertJsonPath('errors.wallet_mobile.0', 'أدخل رقم فودافون كاش صحيحاً يبدأ بـ 010.');
        Http::assertNothingSent();

        $this->postJson($url, ['method' => 'vodafone_cash', 'wallet_mobile' => '+20 10 1234 5678'])
            ->assertCreated()
            ->assertJsonPath('method', 'vodafone_cash')
            ->assertJsonPath('wallet_mobile', '01012345678')
            ->assertJsonPath('reference_number', '7700112233');
        Http::assertSent(function (Request $request) {
            $ref = $request['merchantRefNum'];

            return $request->url() === 'https://atfawry.fawrystaging.com/ECommerceWeb/api/payments/charge'
                && $request['paymentMethod'] === 'MWALLET'
                && $request['currencyCode'] === 'EGP'
                && $request['debitMobileWalletNo'] === '01012345678'
                && $request['signature'] === hash('sha256', self::CODE.$ref.''.'MWALLET'.'450.00'.'01012345678'.self::KEY);
        });
    }

    public function test_it_refuses_when_fawry_is_not_set_up_or_the_payment_is_not_yours_or_already_paid(): void
    {
        $this->fakeFawry();
        [, $parent, $payment] = $this->family();
        [, , $otherPayment] = $this->family();
        Sanctum::actingAs($parent);

        $this->postJson("/api/payments/{$otherPayment->id}/online", ['method' => 'fawry'])->assertForbidden();
        $this->postJson("/api/payments/{$payment->id}/online", ['method' => 'cash'])->assertStatus(422)->assertJsonValidationErrors('method');

        $payment->update(['status' => 'paid', 'paid_at' => '2026-10-02']);
        $this->postJson("/api/payments/{$payment->id}/online", ['method' => 'fawry'])
            ->assertStatus(422)->assertJsonPath('errors.payment.0', 'هذه الدفعة مدفوعة بالفعل.');

        $payment->update(['status' => 'pending', 'paid_at' => null]);
        config(['services.fawry.secure_key' => null]);
        $this->postJson("/api/payments/{$payment->id}/online", ['method' => 'fawry'])
            ->assertStatus(422)->assertJsonPath('errors.method.0', 'الدفع الإلكتروني غير مفعّل بعد. اطلب من الإدارة ربط حساب فوري.');
        Http::assertNothingSent();
    }

    public function test_a_family_without_a_valid_phone_is_asked_for_one_and_a_declined_request_is_not_kept(): void
    {
        Http::fake(['*/ECommerceWeb/Fawry/payments/charge' => Http::sequence()
            ->push(['statusCode' => 200, 'referenceNumber' => '9900112233'])
            ->push(['statusCode' => 9901, 'statusDescription' => 'Wrong Signature'])]);
        [, $parent, $payment] = $this->family('abc');
        Sanctum::actingAs($parent);
        $url = "/api/payments/{$payment->id}/online";

        $this->postJson($url, ['method' => 'fawry'])->assertStatus(422)->assertJsonValidationErrors('customer_mobile');
        $this->postJson($url, ['method' => 'fawry', 'customer_mobile' => '0100 000 0000'])->assertCreated();

        $payment->attempts()->delete();
        $this->postJson($url, ['method' => 'fawry', 'customer_mobile' => '01000000000'])
            ->assertStatus(422)->assertJsonPath('errors.method.0', 'رفضت خدمة الدفع الطلب: Wrong Signature.');
        $this->assertSame(PaymentAttempt::FAILED, $payment->attempts()->value('status'));
    }

    public function test_a_signed_paid_notification_settles_the_payment_once_and_tells_the_family(): void
    {
        $this->fakeFawry();
        [$student, $parent, $payment] = $this->family();
        Sanctum::actingAs($parent);
        $this->postJson("/api/payments/{$payment->id}/online", ['method' => 'fawry'])->assertCreated();
        $attempt = PaymentAttempt::query()->firstOrFail();

        // Anyone can post; only Fawry's signature is believed.
        $forged = $this->notification($attempt);
        $forged['messageSignature'] = str_repeat('a', 64);
        $this->postJson('/api/webhooks/fawry', $forged)->assertForbidden();
        $this->assertSame('pending', $payment->fresh()->status);

        // Short of the amount: not settled.
        $this->postJson('/api/webhooks/fawry', $this->notification($attempt, 'PAID', 100.0))->assertOk();
        $this->assertSame('pending', $payment->fresh()->status);

        $response = $this->postJson('/api/webhooks/fawry', $this->notification($attempt));
        $response->assertOk();
        $this->assertSame('', $response->getContent(), 'Fawry wants an empty body');
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertStringContainsString('دفع إلكتروني (فوري)', $payment->fresh()->note);
        $this->assertSame(PaymentAttempt::PAID, $attempt->fresh()->status);
        $this->assertSame(1, $parent->notifications()->count());

        // Fawry retries: nothing is applied or announced twice.
        $this->postJson('/api/webhooks/fawry', $this->notification($attempt))->assertOk();
        $this->assertSame(1, $parent->notifications()->count());
    }

    public function test_expired_and_unknown_notifications_leave_the_payment_alone(): void
    {
        $this->fakeFawry();
        [, $parent, $payment] = $this->family();
        Sanctum::actingAs($parent);
        $this->postJson("/api/payments/{$payment->id}/online", ['method' => 'fawry']);
        $attempt = PaymentAttempt::query()->firstOrFail();

        $this->postJson('/api/webhooks/fawry', $this->notification($attempt, 'EXPIRED'))->assertOk();
        $this->assertSame(PaymentAttempt::EXPIRED, $attempt->fresh()->status);
        $this->assertSame('pending', $payment->fresh()->status);

        $unknown = $this->notification($attempt);
        $unknown['merchantRefNumber'] = 'nothing-here';
        $unknown['messageSignature'] = $this->signer()->notification($unknown);
        $this->postJson('/api/webhooks/fawry', $unknown)->assertOk();
        $this->assertSame(PaymentAttempt::EXPIRED, $attempt->fresh()->status);
    }

    public function test_the_app_can_pull_the_status_when_a_notification_was_missed(): void
    {
        $this->fakeFawry();
        [, $parent, $payment] = $this->family();
        Sanctum::actingAs($parent);
        $this->postJson("/api/payments/{$payment->id}/online", ['method' => 'fawry']);
        $attempt = PaymentAttempt::query()->firstOrFail();

        Http::fake(['*/payments/status/v2*' => Http::sequence()
            ->push(['paymentStatus' => 'UNPAID', 'paymentAmount' => 0])
            ->push(['paymentStatus' => 'PAID', 'paymentAmount' => 450])]);
        $this->getJson("/api/payments/{$payment->id}/online")
            ->assertOk()->assertJsonPath('status', 'pending')->assertJsonPath('payment_status', 'pending');
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'status/v2')
            && $request['merchantRefNumber'] === $attempt->merchant_ref
            && $request['signature'] === hash('sha256', self::CODE.$attempt->merchant_ref.self::KEY));

        $this->getJson("/api/payments/{$payment->id}/online")
            ->assertOk()->assertJsonPath('status', 'paid')->assertJsonPath('payment_status', 'paid');
        $this->assertSame('paid', $payment->fresh()->status);
    }

    public function test_staff_can_start_it_for_any_student_but_a_payment_without_attempts_has_no_status(): void
    {
        $this->fakeFawry();
        [, , $payment] = $this->family();
        Sanctum::actingAs(User::factory()->create(['role' => 'teacher']));

        $this->getJson("/api/payments/{$payment->id}/online")->assertNotFound();
        $this->postJson("/api/payments/{$payment->id}/online", ['method' => 'fawry'])->assertCreated();
    }

    public function test_signatures_follow_fawrys_field_order(): void
    {
        $signer = $this->signer();
        $this->assertSame('10.00', FawrySigner::money(10));
        $this->assertSame(hash('sha256', 'MERCHANT1'.'R1'.'42'.'MWALLET'.'10.50'.self::KEY), $signer->charge('R1', 'MWALLET', 10.5, '42'));
        $this->assertSame(hash('sha256', 'MERCHANT1'.'R1'.''.'MWALLET'.'10.00'.'01012345678'.self::KEY), $signer->walletRequest('R1', 'MWALLET', 10, '01012345678'));
        $this->assertSame(hash('sha256', 'MERCHANT1'.'R1'.self::KEY), $signer->status('R1'));

        // Order creation: the payment reference is empty.
        $created = ['fawryRefNumber' => '99', 'merchantRefNumber' => 'R1', 'paymentAmount' => 0, 'orderAmount' => 10, 'orderStatus' => 'NEW', 'paymentMethod' => 'PAYATFAWRY'];
        $this->assertSame(hash('sha256', '99'.'R1'.'0.00'.'10.00'.'NEW'.'PAYATFAWRY'.''.self::KEY), $signer->notification($created));
    }
}

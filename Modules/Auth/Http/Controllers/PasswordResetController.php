<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Models\User;
use Modules\Auth\Services\AuditTrail;

/** "Forgot my password" by email. Off until the server has a real mail transport. */
class PasswordResetController extends Controller
{
    private const SENT = 'إن كان البريد مسجلاً لدينا فقد أرسلنا إليه رابط إعادة تعيين كلمة المرور.';

    /** The default "log" and "array" mailers deliver nothing, so offering the feature would only mislead. */
    public static function enabled(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array'], true);
    }

    public function forgot(Request $request)
    {
        abort_unless(self::enabled(), 404);
        $email = $request->validate(['email' => ['required', 'email', 'max:190']])['email'];

        $user = User::where('email', $email)->first();
        if ($user?->isUsable()) {
            $this->shapeTheEmail($request);
            PasswordBroker::sendResetLink(['email' => $email]);
        }

        // The same answer whether or not the address exists, so this cannot be used to find accounts.
        return ['message' => self::SENT];
    }

    public function reset(Request $request, AuditTrail $audit)
    {
        abort_unless(self::enabled(), 404);
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $status = PasswordBroker::reset($data, function (User $user, string $password) use ($audit) {
            abort_unless($user->isUsable(), 403, 'هذا الحساب غير متاح حالياً. تواصل مع الإدارة.');
            $user->forceFill(['password' => $password])->save();
            $user->tokens()->delete(); // every device signs in again with the new password
            $audit->record($user, 'password.reset', $user);
        });

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'رابط إعادة التعيين غير صالح أو انتهت صلاحيته.']);
        }

        return ['message' => 'تم تغيير كلمة المرور. سجّل الدخول بها.'];
    }

    /** The link points at this school's own page, and the message is in Arabic. */
    private function shapeTheEmail(Request $request): void
    {
        $origin = $request->getSchemeAndHttpHost();
        // Laravel hands this callback the token, not a ready-made link.
        ResetPassword::toMailUsing(fn (User $user, string $token) => (new MailMessage)
            ->subject('إعادة تعيين كلمة المرور')
            ->greeting('مرحباً '.$user->name)
            ->line('وصلنا طلب لإعادة تعيين كلمة مرور حسابك.')
            ->action('إعادة تعيين كلمة المرور', $origin.'/reset-password?token='.$token.'&email='.urlencode($user->email))
            ->line('الرابط صالح لمدة 60 دقيقة. إن لم تطلب ذلك فتجاهل هذه الرسالة.')
            ->salutation('فريق مُدرّسى'));
    }
}

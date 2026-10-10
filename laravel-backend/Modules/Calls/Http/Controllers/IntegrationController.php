<?php

namespace Modules\Calls\Http\Controllers;

use Modules\Core\Http\Concerns\AuthorizesStaff;
use App\Http\Controllers\Controller;
use Modules\Calls\Models\ProviderAccount;
use Modules\Calls\Services\Meetings\OAuthProvider;
use Modules\Tenancy\Support\SchoolContext;
use Illuminate\Http\Request;

/** Staff connect their Google or Zoom account to create meeting links from the app. */
class IntegrationController extends Controller
{
    use AuthorizesStaff;

    public function __construct(private readonly OAuthProvider $oauth)
    {
    }

    /** Which accounts can be connected on this server, and which already are. */
    public function index(Request $request)
    {
        $this->authorizeStaff($request);
        $connected = ProviderAccount::query()->where('user_id', $request->user()->id)->pluck('provider')->all();

        return ['data' => collect(OAuthProvider::PROVIDERS)->map(fn (string $provider) => [
            'provider' => $provider,
            'available' => $this->oauth->isConfigured($provider),
            'connected' => in_array($provider, $connected, true),
        ])->values()];
    }

    /** The address the app opens in a browser to sign in to the provider. */
    public function connect(Request $request, string $provider)
    {
        $this->authorizeStaff($request);
        abort_unless(in_array($provider, OAuthProvider::PROVIDERS, true), 404);

        return ['url' => $this->oauth->authorizeUrl($request->user(), $provider)];
    }

    /** The provider sends the browser back to the central address; the state says which school and who. */
    public function callback(Request $request, string $provider)
    {
        abort_unless(in_array($provider, OAuthProvider::PROVIDERS, true), 404);
        $data = $request->validate(['code' => 'required|string', 'state' => 'required|string']);
        $school = $this->oauth->schoolOf($data['state']);
        abort_if($school === null, 403, 'انتهت صلاحية طلب الربط.');
        SchoolContext::enter($school);
        $this->oauth->complete($provider, $data['code'], $data['state']);

        return response(
            '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<body dir="rtl" style="font-family:sans-serif;text-align:center;padding:48px 16px">'
            .'<h2>تم ربط الحساب</h2><p>يمكنك الآن العودة إلى تطبيق زويل.</p></body>',
            200,
            ['Content-Type' => 'text/html; charset=utf-8'],
        );
    }

    public function disconnect(Request $request, string $provider)
    {
        $this->authorizeStaff($request);
        ProviderAccount::query()->where(['user_id' => $request->user()->id, 'provider' => $provider])->delete();

        return response()->noContent();
    }
}

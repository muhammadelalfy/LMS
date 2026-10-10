<?php

namespace Modules\Payments\Services;

use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * The FawryPay server-to-server calls we use: a Pay at Fawry reference
 * number, an e-wallet request-to-pay (Vodafone Cash and other wallets), and
 * the payment status. Nothing here knows about students or payments; that is
 * {@see OnlinePayments}.
 *
 * Endpoints and fields follow developer.fawrystaging.com (Create Payment
 * Requests Using FawryPay Reference Number, E-Wallet Payments, Get Payment
 * Status V2). Production uses the same paths on the production host.
 */
class FawryClient
{
    /** FawryPay answers `statusCode` 200 when the operation worked. */
    private const OK = 200;

    public function isConfigured(): bool
    {
        return filled(config('services.fawry.merchant_code')) && filled(config('services.fawry.secure_key'));
    }

    public function signer(): FawrySigner
    {
        return new FawrySigner(
            (string) config('services.fawry.merchant_code'),
            (string) config('services.fawry.secure_key'),
        );
    }

    /**
     * Creates a reference number the customer pays at any Fawry outlet, or
     * in the Fawry app.
     *
     * @param  array{ref: string, amount: int, description: string, mobile: string, email: string, name: string}  $order
     * @return array<string, mixed>  Fawry's response, including `referenceNumber`
     */
    public function createReference(array $order, CarbonInterface $expiresAt): array
    {
        $method = (string) config('services.fawry.reference_method');

        return $this->send($this->url('/ECommerceWeb/Fawry/payments/charge'), $this->body($order, $method, $expiresAt) + [
            'signature' => $this->signer()->charge($order['ref'], $method, $order['amount']),
        ]);
    }

    /**
     * Asks the customer's wallet (Vodafone Cash and others) to approve the
     * payment: Fawry pushes a request to the wallet owner's phone.
     *
     * @param  array{ref: string, amount: int, description: string, mobile: string, email: string, name: string}  $order
     * @return array<string, mixed>
     */
    public function requestWalletPayment(array $order, string $walletMobile, CarbonInterface $expiresAt): array
    {
        return $this->send($this->url('/ECommerceWeb/api/payments/charge'), $this->body($order, 'MWALLET', $expiresAt) + [
            'currencyCode' => 'EGP',
            'debitMobileWalletNo' => $walletMobile,
            'signature' => $this->signer()->walletRequest($order['ref'], 'MWALLET', $order['amount'], $walletMobile),
        ]);
    }

    /**
     * Pulls the payment's state, for when a notification did not arrive.
     *
     * @return array<string, mixed>
     */
    public function status(string $merchantRef): array
    {
        try {
            $response = Http::acceptJson()->timeout(20)->get($this->url('/ECommerceWeb/Fawry/payments/status/v2'), [
                'merchantCode' => config('services.fawry.merchant_code'),
                'merchantRefNumber' => $merchantRef,
                'signature' => $this->signer()->status($merchantRef),
            ]);
        } catch (ConnectionException) {
            abort(502, 'تعذر الاتصال بخدمة الدفع. حاول مرة أخرى بعد قليل.');
        }

        return $response->json() ?? [];
    }

    /**
     * @param  array{ref: string, amount: int, description: string, mobile: string, email: string, name: string}  $order
     * @return array<string, mixed>
     */
    private function body(array $order, string $paymentMethod, CarbonInterface $expiresAt): array
    {
        return [
            'merchantCode' => config('services.fawry.merchant_code'),
            'merchantRefNum' => $order['ref'],
            'paymentMethod' => $paymentMethod,
            'customerName' => $order['name'],
            'customerMobile' => $order['mobile'],
            'customerEmail' => $order['email'],
            'amount' => FawrySigner::money($order['amount']),
            'paymentExpiry' => $expiresAt->getTimestampMs(),
            'description' => $order['description'],
            'language' => 'ar-eg',
            'chargeItems' => [[
                'itemId' => $order['ref'],
                'description' => $order['description'],
                'price' => FawrySigner::money($order['amount']),
                'quantity' => 1,
            ]],
            'orderWebHookUrl' => config('services.fawry.webhook_url'),
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function send(string $url, array $body): array
    {
        try {
            $response = Http::acceptJson()->asJson()->timeout(20)->post($url, array_filter($body, fn ($v) => $v !== null));
        } catch (ConnectionException) {
            abort(502, 'تعذر الاتصال بخدمة الدفع. حاول مرة أخرى بعد قليل.');
        }

        $json = $response->json() ?? [];
        if (($json['statusCode'] ?? null) !== self::OK) {
            throw ValidationException::withMessages([
                'method' => 'رفضت خدمة الدفع الطلب: '.($json['statusDescription'] ?? 'خطأ غير معروف').'.',
            ]);
        }

        return $json;
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.fawry.base_url'), '/').$path;
    }
}

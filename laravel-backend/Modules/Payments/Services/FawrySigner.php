<?php

namespace Modules\Payments\Services;

/**
 * Fawry's SHA-256 signatures. Each is the plain concatenation of fields, in
 * Fawry's order, followed by the merchant's secure key (FawryPay server APIs
 * and Server To Server Notification V2 documentation). Amounts are always
 * written with two decimals.
 */
final class FawrySigner
{
    public function __construct(private readonly string $merchantCode, private readonly string $secureKey)
    {
    }

    /** "10" and 10.5 become "10.00" and "10.50", as Fawry signs them. */
    public static function money(int|float|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /** Reference-number (Pay at Fawry) and e-wallet QR charges. */
    public function charge(string $merchantRef, string $paymentMethod, int|float|string $amount, string $customerProfileId = ''): string
    {
        return $this->hash($this->merchantCode.$merchantRef.$customerProfileId.$paymentMethod.self::money($amount));
    }

    /** E-wallet request-to-pay: the debited wallet number joins the signature. */
    public function walletRequest(string $merchantRef, string $paymentMethod, int|float|string $amount, string $walletMobile, string $customerProfileId = ''): string
    {
        return $this->hash(
            $this->merchantCode.$merchantRef.$customerProfileId.$paymentMethod.self::money($amount).$walletMobile,
        );
    }

    /** The "get payment status" query. */
    public function status(string $merchantRef): string
    {
        return $this->hash($this->merchantCode.$merchantRef);
    }

    /**
     * The `messageSignature` Fawry should have put on a V2 notification.
     * Fawry's own spelling `paymentRefrenceNumber` is kept; the field is
     * empty on order-creation notifications.
     *
     * @param  array<string, mixed>  $notification
     */
    public function notification(array $notification): string
    {
        return $this->hash(
            ($notification['fawryRefNumber'] ?? '')
            .($notification['merchantRefNumber'] ?? $notification['merchantRefNum'] ?? '')
            .self::money($notification['paymentAmount'] ?? 0)
            .self::money($notification['orderAmount'] ?? 0)
            .($notification['orderStatus'] ?? '')
            .($notification['paymentMethod'] ?? '')
            .($notification['paymentRefrenceNumber'] ?? $notification['paymentReferenceNumber'] ?? ''),
        );
    }

    /** @param  array<string, mixed>  $notification */
    public function isValidNotification(array $notification): bool
    {
        $given = (string) ($notification['messageSignature'] ?? '');

        return $given !== '' && hash_equals($this->notification($notification), strtolower($given));
    }

    private function hash(string $fields): string
    {
        return hash('sha256', $fields.$this->secureKey);
    }
}

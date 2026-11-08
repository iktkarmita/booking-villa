<?php

namespace App\Support;

use App\Mail\SimpleMail;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Email tagihan langganan dari platform ke owner tenant (bahasa default tenant). Kegagalan kirim tidak menghentikan proses. */
final class PlatformNotifier
{
    public static function invoiceCreated(SubscriptionInvoice $invoice): void
    {
        self::sendInvoice($invoice, 'invoice_created');
    }

    public static function reminder(SubscriptionInvoice $invoice): void
    {
        self::sendInvoice($invoice, 'invoice_reminder');
    }

    public static function paid(SubscriptionInvoice $invoice): void
    {
        self::sendInvoice($invoice, 'invoice_paid');
    }

    public static function suspended(Tenant $tenant): void
    {
        self::send($tenant, 'suspended', []);
    }

    private static function sendInvoice(SubscriptionInvoice $invoice, string $key): void
    {
        $tenant = Tenant::query()->find($invoice->tenant_id);

        if (! $tenant) {
            return;
        }

        self::send($tenant, $key, [
            'number' => $invoice->number,
            'amount' => Money::idr($invoice->amount),
            'due' => $invoice->due_date->format('d/m/Y'),
            'period' => $invoice->period_start->format('d/m/Y').' - '.$invoice->period_end->format('d/m/Y'),
            'bank' => self::bankLine(),
        ]);
    }

    /** @return array<int, string> */
    private static function ownerEmails(Tenant $tenant): array
    {
        return $tenant->users()->wherePivot('role', 'owner')->pluck('users.email')->filter()->unique()->values()->all();
    }

    private static function send(Tenant $tenant, string $key, array $params): void
    {
        try {
            $emails = self::ownerEmails($tenant);

            if ($emails === []) {
                return;
            }

            $previous = app()->getLocale();
            app()->setLocale($tenant->default_locale ?: config('booking.default_locale', 'id'));

            try {
                $params += ['tenant' => $tenant->name];
                $mail = new SimpleMail(
                    __('billing.mail.'.$key.'.subject', $params),
                    __('billing.mail.'.$key.'.body', $params),
                    self::panelUrl($tenant),
                    __('billing.mail.open_panel'),
                    (string) config('app.name'),
                );
            } finally {
                app()->setLocale($previous);
            }

            Mail::to($emails)->send($mail);
        } catch (Throwable $e) {
            if (app()->runningUnitTests() && ! $e instanceof \Symfony\Component\Mailer\Exception\TransportExceptionInterface) {
                throw $e;
            }

            report($e);
        }
    }

    public static function bankLine(): string
    {
        $bank = config('booking.platform_bank');

        if (blank($bank['number'] ?? null)) {
            return '-';
        }

        return trim(($bank['name'] ?? '').' '.$bank['number'].' a.n. '.($bank['holder'] ?? ''));
    }

    private static function panelUrl(Tenant $tenant): string
    {
        return rtrim((string) config('app.url'), '/').'/admin/'.$tenant->slug.'/subscription-invoices';
    }
}

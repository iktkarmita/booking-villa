<?php

namespace App\Support;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Siklus langganan tenant: tagihan periode berikutnya, pembayaran, kedaluwarsa + masa tenggang,
 * penangguhan, dan pengingat. Tagihan dibayar ke rekening platform, dikonfirmasi super admin.
 */
final class SubscriptionService
{
    /** @return array{expired: int, invoiced: int, suspended: int, reminded: int} */
    public static function process(): array
    {
        // Urutan penting: kedaluwarsakan dulu agar langganan yang telat mendapat tagihan yang dimulai hari ini.
        return [
            'expired' => self::expireOverdue(),
            'invoiced' => self::generateInvoices(),
            'suspended' => self::suspendOverdue(),
            'reminded' => self::sendReminders(),
        ];
    }

    /** Langganan trial/aktif yang sudah lewat tanggal berakhir -> menunggak, mulai masa tenggang. */
    public static function expireOverdue(): int
    {
        $count = 0;
        $graceDays = (int) config('booking.subscription.grace_days', 7);

        Subscription::query()
            ->whereIn('status', SubscriptionStatus::usable())
            ->whereNotNull('ends_at')
            ->whereDate('ends_at', '<', now()->toDateString())
            ->each(function (Subscription $subscription) use (&$count, $graceDays) {
                $subscription->update([
                    'status' => SubscriptionStatus::PastDue,
                    'grace_until' => CarbonImmutable::parse($subscription->ends_at)->addDays($graceDays)->toDateString(),
                ]);
                $count++;
            });

        return $count;
    }

    /** Tagihan periode berikutnya untuk langganan yang segera/sudah berakhir dan belum punya tagihan terbuka. */
    public static function generateInvoices(): int
    {
        $count = 0;
        $lead = (int) config('booking.subscription.invoice_lead_days', 7);

        Subscription::query()
            ->with('plan')
            ->whereIn('status', [SubscriptionStatus::Trial->value, SubscriptionStatus::Active->value, SubscriptionStatus::PastDue->value])
            ->whereNotNull('ends_at')
            ->whereDate('ends_at', '<=', now()->addDays($lead)->toDateString())
            ->whereDoesntHave('invoices', fn ($query) => $query->where('status', 'unpaid'))
            ->each(function (Subscription $subscription) use (&$count) {
                if (($subscription->plan?->price ?? 0) > 0) {
                    self::createInvoice($subscription);
                    $count++;
                }
            });

        return $count;
    }

    public static function createInvoice(Subscription $subscription): SubscriptionInvoice
    {
        $today = CarbonImmutable::today();
        $endsAt = CarbonImmutable::parse($subscription->ends_at);
        $late = $endsAt->lessThan($today);

        $start = $late ? $today : $endsAt->addDay();

        $invoice = SubscriptionInvoice::create([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->getKey(),
            'number' => self::newNumber(),
            'amount' => $subscription->plan->price,
            'status' => 'unpaid',
            'period_start' => $start->toDateString(),
            'period_end' => self::periodEnd($subscription->plan, $start)->toDateString(),
            'due_date' => ($late ? $today->addDays(3) : $endsAt)->toDateString(),
        ]);

        PlatformNotifier::invoiceCreated($invoice);

        return $invoice;
    }

    /**
     * Super admin menandai tagihan lunas: langganan diperpanjang, tenant aktif kembali.
     * Tagihan yang periodenya sudah lewat sepenuhnya dihitung ulang mulai hari ini.
     *
     * @throws DomainException
     */
    public static function markPaid(SubscriptionInvoice $invoice, ?string $note = null): SubscriptionInvoice
    {
        $paidNow = false;

        $invoice = DB::transaction(function () use ($invoice, $note, &$paidNow) {
            $invoice = SubscriptionInvoice::withoutTenancy()->lockForUpdate()->findOrFail($invoice->getKey());

            if ($invoice->status === 'paid') {
                return $invoice;
            }

            if ($invoice->status !== 'unpaid') {
                throw new DomainException(__('billing.errors.not_payable'));
            }

            $subscription = Subscription::query()->with('plan')->lockForUpdate()->findOrFail($invoice->subscription_id);
            $today = CarbonImmutable::today();

            $periodStart = CarbonImmutable::parse($invoice->period_start);
            $periodEnd = CarbonImmutable::parse($invoice->period_end);

            if ($periodEnd->lessThan($today)) {
                $periodStart = $today;
                $periodEnd = self::periodEnd($subscription->plan, $today);
            }

            $invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
                'note' => $note ?? $invoice->note,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
            ]);

            $subscription->update([
                'status' => SubscriptionStatus::Active,
                'ends_at' => $periodEnd->toDateString(),
                'grace_until' => null,
            ]);

            Tenant::query()->whereKey($invoice->tenant_id)->update(['status' => TenantStatus::Active->value]);

            $paidNow = true;

            return $invoice;
        });

        if ($paidNow) {
            PlatformNotifier::paid($invoice);
        }

        return $invoice;
    }

    /** @throws DomainException */
    public static function void(SubscriptionInvoice $invoice): SubscriptionInvoice
    {
        if ($invoice->status !== 'unpaid') {
            throw new DomainException(__('billing.errors.not_payable'));
        }

        $invoice->update(['status' => 'void']);

        return $invoice;
    }

    /** Masa tenggang habis dan belum membayar -> tenant ditangguhkan (data aman, situs publik ditutup). */
    public static function suspendOverdue(): int
    {
        $count = 0;

        Subscription::query()
            ->where('status', SubscriptionStatus::PastDue->value)
            ->whereNotNull('grace_until')
            ->whereDate('grace_until', '<', now()->toDateString())
            ->each(function (Subscription $subscription) use (&$count) {
                $tenant = Tenant::query()->find($subscription->tenant_id);

                if ($tenant && $tenant->status !== TenantStatus::Suspended) {
                    $tenant->update(['status' => TenantStatus::Suspended]);
                    PlatformNotifier::suspended($tenant);
                    $count++;
                }
            });

        return $count;
    }

    /** Pengingat jatuh tempo, sekali per tagihan. */
    public static function sendReminders(): int
    {
        $count = 0;
        $days = (int) config('booking.subscription.reminder_days', 3);

        SubscriptionInvoice::withoutTenancy()
            ->where('status', 'unpaid')
            ->whereNull('reminded_at')
            ->whereDate('due_date', '<=', now()->addDays($days)->toDateString())
            ->each(function (SubscriptionInvoice $invoice) use (&$count) {
                PlatformNotifier::reminder($invoice);
                $invoice->update(['reminded_at' => now()]);
                $count++;
            });

        return $count;
    }

    private static function periodEnd(Plan $plan, CarbonImmutable $start): CarbonImmutable
    {
        return ($plan->interval === 'year' ? $start->addYearNoOverflow() : $start->addMonthNoOverflow())->subDay();
    }

    private static function newNumber(): string
    {
        do {
            $number = 'INV-'.now()->format('Ym').'-'.Str::upper(Str::random(5));
        } while (SubscriptionInvoice::withoutTenancy()->where('number', $number)->exists());

        return $number;
    }
}

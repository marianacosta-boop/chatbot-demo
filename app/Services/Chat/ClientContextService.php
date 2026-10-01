<?php

namespace App\Services\Chat;

use App\Models\User;
use App\Services\Salesforce\SalesforceAdapter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class ClientContextService
{
    public function __construct(private SalesforceAdapter $sf) {}

    public function snapshot(User $user): array
    {
        $cacheKey = "chat:snapshot:{$user->id}:" . config('chatbot.salesforce_driver');

        return Cache::remember($cacheKey, config('chatbot.snapshot_cache_ttl'), function () use ($user) {
            $account = $this->sf->account($user->tin, $user->email);
            $contact = $this->sf->contact($user->email, $account['Id'] ?? null);
            $assets  = $this->sf->activeAssets($account['Id'] ?? '');
            $cases   = $this->sf->openCases($account['Id'] ?? '');
            $window  = config('chatbot.renewal_window_days');
            $creditsProductCode = config('chatbot.credits_product_code');
            $lowStampThreshold  = config('chatbot.low_stamp_balance_threshold');

            $products = collect($assets)->filter(fn ($a) => empty($a['Renewed_By__c']))->map(function ($a) use ($window) {
                $days = (int) now()->startOfDay()->diffInDays(Carbon::parse($a['UsageEndDate']), false);
                return [
                    'asset_id'       => $a['Id'],
                    'product_code'   => $a['Product2']['ProductCode'],
                    'name'           => $a['Product2']['Name'],
                    'quantity'       => $a['Quantity'],
                    'end_date'       => $a['UsageEndDate'],
                    'days_to_expiry' => $days,
                    'expiring_soon'  => $days <= $window,
                    'auto_renew'     => (bool) ($a['Auto_Renew__c'] ?? false),
                    'stamp_balance'  => $a['StampBalance'] ?? null,
                ];
            })->values();

            // Most recent "Aquisição de créditos" asset, if any: used to proactively offer a top-up.
            $latestCredits = $products->filter(fn ($p) => $p['product_code'] === $creditsProductCode)
                ->sortByDesc('end_date')->first();

            $credits = null;
            if ($latestCredits) {
                $lowBalance = $latestCredits['stamp_balance'] !== null && $latestCredits['stamp_balance'] < $lowStampThreshold;
                $credits = [
                    'asset_id'           => $latestCredits['asset_id'],
                    'stamp_balance'      => $latestCredits['stamp_balance'],
                    'low_stamp_balance'  => $lowBalance,
                    'expiring_soon'      => $latestCredits['expiring_soon'],
                    'offer_more_credits' => $lowBalance || $latestCredits['expiring_soon'],
                ];
            }

            return [
                'today'   => now()->toDateString(),
                'contact' => [
                    'id'       => $contact['Id'] ?? null,
                    'name'     => $user->name ?: ($contact['Name'] ?? ''),
                    'email'    => $user->email,
                    'language' => 'pt-PT',
                ],
                'account' => [
                    'id'              => $account['Id'] ?? null,
                    'name'            => $account['Name'] ?? null,
                    'tin'             => $user->tin,
                    'segment'         => $account['Segment__c'] ?? null,
                    'account_manager' => $account['Owner']['Name'] ?? null,
                ],
                'products' => $products->values()->all(),
                'credits'  => $credits,
                'open_cases' => collect($cases)->map(fn ($c) => [
                    'number' => $c['CaseNumber'], 'subject' => $c['Subject'], 'status' => $c['Status'],
                ])->values()->all(),
            ];
        });
    }
}
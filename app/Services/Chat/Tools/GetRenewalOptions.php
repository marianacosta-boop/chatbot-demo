<?php

namespace App\Services\Chat\Tools;

use App\Models\User;
use App\Services\Chat\ClientContextService;
use App\Services\Salesforce\SalesforceAdapter;

class GetRenewalOptions implements Tool
{
    public function __construct(private SalesforceAdapter $sf) {}

    public function name(): string { return 'get_renewal_options'; }

    public function definition(): array
    {
        return [
            'name'        => $this->name(),
            'description' => 'Return the renewal and upgrade options, with prices, for one of the client\'s products. Prices must only ever be quoted from this tool.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'asset_id' => ['type' => 'string', 'description' => 'asset_id from the client snapshot'],
                ],
                'required' => ['asset_id'],
            ],
        ];
    }

    public function handle(array $input, ?User $user): array
    {
        if (! $user) {
            return ['error' => 'An authenticated client is required.'];
        }

        // Authorisation: the asset must belong to the logged-in client's account.
        $snapshot = app(ClientContextService::class)->snapshot($user);
        $asset    = $this->sf->asset($input['asset_id'], $snapshot['account']['id']);
        if (! $asset || ! collect($snapshot['products'])->contains('asset_id', $asset['Id'])) {
            return ['error' => 'Asset not found for this client.'];
        }

        $options = $this->sf->renewalOptions($asset);   // PricebookEntry rows or your own pricing rules

        return [
            'asset'   => ['name' => $asset['Product2']['Name'], 'end_date' => $asset['UsageEndDate']],
            'options' => collect($options)->map(fn ($o) => [
                'option_id'   => $o['Id'],
                'product_code'=> $o['Product2']['ProductCode'] ?? null,
                'name'        => $o['Product2']['Name'],
                'term_months' => $o['Term_Months__c'] ?? null,
                'is_upgrade'  => (bool) ($o['Is_Upgrade__c'] ?? false),
                'unit_price'  => $o['UnitPrice'],
                'currency'    => 'EUR',
                'vat'         => 'excl. VAT',
                'total_price' => $o['UnitPrice'] * $asset['Quantity'],
                'notes'       => $o['Description'] ?? null,
            ])->values()->all(),
        ];
    }
}
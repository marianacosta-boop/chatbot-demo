<?php

namespace App\Services\Chat\Tools;

use App\Models\User;
use App\Services\Chat\ClientContextService;
use App\Services\Salesforce\SalesforceAdapter;

class EscalateToHuman implements Tool
{
    public function __construct(private SalesforceAdapter $sf) {}

    public function name(): string { return 'escalate_to_human'; }

    public function definition(): array
    {
        return [
            'name'        => $this->name(),
            'description' => 'Open a support case so a human agent contacts the client. Use when the client asks for it, when you cannot answer, or for contractual, legal or billing disputes.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'subject'  => ['type' => 'string'],
                    'summary'  => ['type' => 'string', 'description' => 'Short summary of the conversation so far'],
                    'priority' => ['type' => 'string', 'enum' => ['Low', 'Medium', 'High']],
                    'asset_id' => ['type' => 'string', 'description' => 'asset_id of the expiring product related to this request, when applicable'],
                ],
                'required' => ['subject', 'summary'],
            ],
        ];
    }

    public function handle(array $input, ?User $user): array
    {
        if (! $user) {
            return ['error' => 'An authenticated client is required.'];
        }

        $snapshot = app(ClientContextService::class)->snapshot($user);

        $case = $this->sf->createCase([
            'AccountId'   => $snapshot['account']['id'],
            'ContactId'   => $snapshot['contact']['id'] ?? null,
            'Subject'     => $input['subject'],
            'Description' => $input['summary'],
            'Priority'    => $input['priority'] ?? 'Medium',
            'Origin'      => 'Web chatbot',
            'Opportunity__c' => $this->expiringOpportunityId($input, $snapshot),
            'Platform__c' => 'acinGov',
        ]);

        return ['status' => 'created', 'case_number' => $case['CaseNumber']];
    }

    private function expiringOpportunityId(array $input, array $snapshot): ?string
    {
        $expiringProducts = collect($snapshot['products'] ?? [])->where('expiring_soon', true);
        $assetId = $input['asset_id'] ?? ($expiringProducts->count() === 1
            ? $expiringProducts->first()['asset_id']
            : null);

        if (! $assetId || ! $expiringProducts->contains('asset_id', $assetId)) {
            return null;
        }

        $asset = $this->sf->asset($assetId, $snapshot['account']['id']);

        return $asset['OpportunityId'] ?? null;
    }
}
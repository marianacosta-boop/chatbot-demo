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
                ],
                'required' => ['subject', 'summary'],
            ],
        ];
    }

    public function handle(array $input, User $user): array
    {
        $case = $this->sf->createCase([
            'AccountId'   => app(ClientContextService::class)->snapshot($user)['account']['id'],
            'ContactId'   => app(ClientContextService::class)->snapshot($user)['contact']['id'] ?? null,
            'Subject'     => $input['subject'],
            'Description' => $input['summary'],
            'Priority'    => $input['priority'] ?? 'Medium',
            'Origin'      => 'Web chatbot',
        ]);

        return ['status' => 'created', 'case_number' => $case['CaseNumber']];
    }
}
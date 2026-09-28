<?php

namespace App\Services\Chat\Tools;

use App\Models\User;
use App\Services\Chat\ClientContextService;
use App\Services\Salesforce\SalesforceAdapter;

class CreateRenewalOpportunity implements Tool
{
    public function __construct(private SalesforceAdapter $sf) {}

    public function name(): string { return 'create_renewal_opportunity'; }

    public function definition(): array
    {
        return [
            'name'        => $this->name(),
            'description' => 'Regista o interesse do cliente numa opção de renovação: cria uma Oportunidade no CRM para que um comercial o contacte. Chamar APENAS depois de o cliente confirmar explicitamente a opção e o preço.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'asset_id'     => ['type' => 'string', 'description' => 'asset_id do produto atual (do snapshot)'],
                    'option_id'    => ['type' => 'string', 'description' => 'option_id devolvido por get_renewal_options'],
                    'client_notes' => ['type' => 'string', 'description' => 'O que o cliente pediu ou perguntou (faturação, data de início, dúvidas)'],
                ],
                'required' => ['asset_id', 'option_id'],
            ],
        ];
    }

    public function handle(array $input, User $user): array
    {
        $snapshot = app(ClientContextService::class)->snapshot($user);

        // Authorisation: the asset must be one of this client's products.
        $asset = $this->sf->asset($input['asset_id'], $snapshot['account']['id']);
        if (! $asset || ! collect($snapshot['products'])->contains('asset_id', $asset['Id'])) {
            return ['error' => 'Produto não encontrado para este cliente.'];
        }

        // The option must be one we actually offered for this asset (prices come from here, never from the model).
        $options = collect($this->sf->renewalOptions($asset));
        $option  = $options->firstWhere('Id', $input['option_id'])
                ?? $options->first(fn ($o) => strcasecmp($o['Product2']['Name'], $input['option_id']) === 0);

        if (! $option) {
            return [
                'error'         => 'option_id inválido. Usa exatamente um dos option_id devolvidos por get_renewal_options.',
                'valid_options' => $options->map(fn ($o) => ['option_id' => $o['Id'], 'name' => $o['Product2']['Name']])->values()->all(),
            ];
        }

        $quantity = (int) $asset['Quantity'];
        $total    = round($option['UnitPrice'] * $quantity, 2);

        $result = $this->sf->createOpportunity([
            'NIF__c'      => $user->tin,
            'AccountId'   => $snapshot['account']['id'],
            'ContactId'   => $snapshot['contact']['id'] ?? null,
            '_asset_id'   => $asset['Id'],
            'Name'        => 'Renovação – ' . $option['Product2']['Name'],
            'CloseDate'   => $asset['UsageEndDate'],
            'Description' => trim("Pedido de renovação via chatbot do portal.\n" .
                                  "Produto atual: {$asset['Product2']['Name']} (x{$quantity}), termina em {$asset['UsageEndDate']}.\n" .
                                  ($input['client_notes'] ?? '')),
            'Products'    => [[
                'ProductCode' => $option['Product2']['ProductCode'] ?? $option['ProductCode'] ?? throw new \RuntimeException('Renewal option has no ProductCode'),
                'Quantity'    => (string) $quantity,
                'TotalPrice'  => number_format($total, 2, '.', ''),
            ]],
        ]);

        return [
            'status'          => 'created',
            'reference'       => $result['Id'] ?? null,
            'option'          => $option['Product2']['Name'],
            'total_price'     => $total,
            'currency'        => 'EUR',
            'account_manager' => $snapshot['account']['account_manager'],
            'next_step'       => 'Um comercial entrará em contacto com o cliente.',
        ];
    }
}
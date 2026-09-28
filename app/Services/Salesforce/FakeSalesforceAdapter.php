<?php

namespace App\Services\Salesforce;

use Illuminate\Support\Facades\Log;

/** In-memory stand-in for Salesforce. Shapes mimic the SF REST API so the real adapter is a drop-in. */
class FakeSalesforceAdapter implements SalesforceAdapter
{
    private array $assets;

    public function __construct()
    {
        $this->assets = [
            ['Id' => 'A-1001', 'AccountId' => '0013N00000xXNM0QAO', 'Quantity' => 25, 'UsageEndDate' => now()->addDays(49)->toDateString(), 'Auto_Renew__c' => false,
             'Product2' => ['ProductCode' => 'ACG0032-N', 'Name' => 'Serviços Avançados Gold - Anual'],
             'Account' => ['Owner' => ['Name' => 'Rui Costa']]],
            ['Id' => 'A-1002', 'AccountId' => '0013N00000xXNM0QAO', 'Quantity' => 1, 'UsageEndDate' => now()->addMonths(9)->toDateString(), 'Auto_Renew__c' => true,
             'Product2' => ['ProductCode' => 'ACG0032-N', 'Name' => 'Serviços Avançados Gold - Anual'],
             'Account' => ['Owner' => ['Name' => 'Rui Costa']]],
            ['Id' => 'A-1003', 'AccountId' => '0013N00000xXNM0QAO', 'Quantity' => 1, 'UsageEndDate' => now()->addMonths(6)->toDateString(), 'Auto_Renew__c' => false,
             'Product2' => ['ProductCode' => 'ACG0032-N', 'Name' => 'Serviços Avançados Gold - Anual'],
             'Account' => ['Owner' => ['Name' => 'Rui Costa']]],
        ];
    }

    public function account(string $tin, ?string $email = null): array
    {
        return ['Id' => '0013N00000xXNM0QAO', 'Name' => 'Câmara Municipal de Vila Serena', 'Segment__c' => 'Administração local', 'Owner' => ['Name' => 'Rui Costa']];
    }

    public function contact(string $id): array
    {
        return ['Name' => 'Maria Santos', 'Email' => 'maria.santos@cm-vilaserena.pt', 'Preferred_Language__c' => 'pt-PT'];
    }

    public function activeAssets(string $accountId): array
    {
        return array_values(array_filter($this->assets, fn ($a) => $a['AccountId'] === $accountId));
    }

    public function asset(string $id): ?array
    {
        foreach ($this->assets as $a) {
            if ($a['Id'] === $id) return $a;
        }
        return null;
    }

    public function openCases(string $accountId): array
    {
        return [['CaseNumber' => '00012345', 'Subject' => 'Erro na exportação de relatórios', 'Status' => 'Em análise']];
    }

    public function renewalOptions(array $asset): array
{
    return match ($asset['Id']) {
        'A-1001' => [
            ['Id' => 'O-12',  'Product2' => ['ProductCode' => 'ACG0032-N', 'Name' => 'Serviços Avançados Gold - Anual'],
             'Term_Months__c' => 12, 'UnitPrice' => 190, 'Description' => null],
            ['Id' => 'O-24',  'Product2' => ['ProductCode' => 'ACG0032-N', 'Name' => 'Serviços Avançados Gold - Bienal (2 x anual)'],
             'Term_Months__c' => 24, 'UnitPrice' => 356, 'Description' => 'Desconto de 6% face a duas renovações anuais'],
            ['Id' => 'O-PRO', 'Product2' => ['ProductCode' => 'ACG0032-N', 'Name' => 'Serviços Avançados Gold - Anual (upgrade)'],
             'Term_Months__c' => 12, 'UnitPrice' => 248, 'Description' => 'Inclui módulo de análise e utilizadores ilimitados'],
        ],
        'A-1003' => [
            ['Id' => 'O-F12', 'Product2' => ['ProductCode' => 'ACG0032-N', 'Name' => 'Serviços Avançados Gold - Anual'],
             'Term_Months__c' => 12, 'UnitPrice' => 1350, 'Description' => null],
        ],
        default => [],
    };
}

    public function createOpportunity(array $fields): array
    {
        Log::info('FAKE Salesforce: Opportunity created', $fields);
        return ['Id' => 'OPP-' . random_int(100000, 999999)] + $fields;
    }

    public function createCase(array $fields): array
    {
        Log::info('FAKE Salesforce: Case created', $fields);
        return ['CaseNumber' => '000' . random_int(10000, 99999)] + $fields;
    }
}

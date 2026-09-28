<?php

namespace App\Services\Salesforce;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Real adapter over the Acinforce integrator (chatbot/* endpoints + existing accounts/* and products).
 * Reproduces the SDK auth: Authorization = base64(sha1(public:private:timestamp)), timestamp, user; form-encoded body.
 * Maps the integrator's responses to the Salesforce-like shapes ClientContextService and the tools expect.
 */
class IntegratorSalesforceAdapter implements SalesforceAdapter
{
    // ---------- HTTP core ----------

    private function headers(): array
    {
        $cfg       = config('chatbot.integrator');
        $timestamp = time();
        $secret    = $cfg['public_key'] . ':' . $cfg['private_key'] . ':' . $timestamp;

        return [
            'Authorization' => base64_encode(sha1($secret)),
            'timestamp'     => (string) $timestamp,
            'user'          => $cfg['public_key'],
        ];
    }

    private function dispatch(string $method, string $endpoint, array $body = []): array
    {
        $cfg = config('chatbot.integrator');
        $url = rtrim($cfg['base_url'], '/') . $endpoint;

        $response = Http::withHeaders($this->headers())
            ->withOptions(['verify' => (bool) $cfg['verify_ssl']])
            ->timeout(30)
            ->withBody(http_build_query($body), 'application/x-www-form-urlencoded')
            ->send($method, $url, ['query' => $method === 'GET' ? $body : []]);

        $out = $response->json() ?? [];
        if (empty($out['status'])) {
            $out['status'] = $response->status();
        }

Log::debug('integrator.call', ['method' => $method, 'endpoint' => $endpoint, 'status' => $out['status'], 'sent' => $body, 'data' => $out['data'] ?? null, 'errors' => $out['errors'] ?? null, 'url' => $url]);
        if ($out['status'] >= 400 && $out['status'] !== 404) {
            throw new \RuntimeException("Integrator {$endpoint} failed ({$out['status']}): " . json_encode($out['errors'] ?? $response->body()));
        }

        return $out;
    }

    private function accountIdByTin(string $tin, ?string $email): ?string
    {
        $raw  = $this->dispatch('GET', '/accounts/getUniqueAccountIDIfExists', array_filter(['NIF__c' => $tin, 'Email' => $email]));
        $data = $raw['data'] ?? [];
        return $data['accountID'] ?? $data['Id'] ?? null;
    }

    // ---------- reads ----------

    public function account(string $tin, ?string $email = null): array
    {
        $id = $this->accountIdByTin($tin, $email);
        if (! $id) {
            return ['Id' => null, 'Name' => null, 'Segment__c' => null, 'Owner' => ['Name' => null]];
        }

        $d = $this->dispatch('GET', '/chatbot/account', ['AccountId' => $id])['data'] ?? [];

        return [
            'Id'         => $d['Id'] ?? $id,
            'Name'       => $d['Name'] ?? null,
            'Segment__c' => $d['Segment'] ?? null,
            'Owner'      => ['Name' => $d['OwnerName'] ?? null, 'Email' => $d['OwnerEmail'] ?? null],
        ];
    }

    public function contact(string $email, ?string $accountId = null): array
    {
        if ($email === '') {
            return [];
        }
        $d = $this->dispatch('GET', '/chatbot/contact', array_filter(['Email' => $email, 'AccountId' => $accountId]))['data'] ?? [];
        if (empty($d['Id'])) {
            return [];
        }
        return ['Id' => $d['Id'], 'Name' => $d['Name'], 'Email' => $d['Email'], 'Phone' => $d['Phone'] ?? null, 'AccountId' => $d['AccountId'] ?? null];
    }

    public function activeAssets(string $accountId): array
    {
        if ($accountId === '') {
            return [];
        }
        $rows = $this->dispatch('GET', '/chatbot/assets', ['AccountId' => $accountId])['data'] ?? [];

        return array_map(fn ($a) => $this->mapAsset($a, $accountId), $rows);
    }

    public function asset(string $id, ?string $accountId = null): ?array
    {
        if (! $accountId) {
            return null;   // assets are only ever looked up within the client's own account
        }
        foreach ($this->activeAssets($accountId) as $a) {
            if ($a['Id'] === $id) {
                return $a;
            }
        }
        return null;
    }

    public function openCases(string $accountId): array
    {
        if ($accountId === '') {
            return [];
        }
        $rows = $this->dispatch('GET', '/chatbot/cases', ['AccountId' => $accountId])['data'] ?? [];

        return array_map(fn ($c) => ['CaseNumber' => $c['CaseNumber'], 'Subject' => $c['Subject'], 'Status' => $c['Status']], $rows);
    }

    public function renewalOptions(array $asset): array
    {
        $product2Id = $asset['Product2']['Id'] ?? null;
        if (! $product2Id) {
            return [];
        }
        $rows = $this->dispatch('GET', '/chatbot/renewal-options', array_filter([
            'Product2Id'  => $product2Id,
            'PricebookId' => config('chatbot.integrator.pricebook_id'),
        ]))['data'] ?? [];

        return array_map(fn ($o) => [
            'Id'             => $o['Id'],                       // PricebookEntry Id = option_id
            'UnitPrice'      => (float) $o['UnitPrice'],
            'Term_Months__c' => (int) ($o['TermMonths'] ?? 12),
            'Is_Upgrade__c'  => (bool) ($o['IsUpgrade'] ?? false),
            'Description'    => $o['Pitch'] ?? null,
            'Product2'       => ['Id' => $o['Product2Id'], 'Name' => $o['Name'], 'ProductCode' => $o['ProductCode']],
        ], $rows);
    }

    // ---------- writes ----------

    public function createOpportunity(array $fields): array
    {
        $payload = array_filter($fields, fn ($v) => $v !== null && $v !== '');
        $assetId = $payload['_asset_id'] ?? null;
        unset($payload['_asset_id']);

        if (isset($payload['Products']) && is_array($payload['Products'])) {
            $payload['Products'] = json_encode($payload['Products']);   // integrator expects a JSON string
        }

        $raw  = $this->dispatch('POST', config('chatbot.integrator.create_opportunity_endpoint'), $payload);
        $data = $raw['data'] ?? [];
        $id   = $data['opportunityID'] ?? $data['Id'] ?? $data['id'] ?? null;

        // Best effort: mark the asset so the bot stops offering it.
        if ($id && $assetId) {
            try {
                $this->dispatch('POST', '/chatbot/assets/renewal-pending', ['AssetId' => $assetId, 'OpportunityId' => $id]);
            } catch (\Throwable $e) {
                Log::warning('integrator.renewal_pending_failed', ['asset' => $assetId, 'error' => $e->getMessage()]);
            }
        }

        return ['Id' => $id] + $payload;
    }

    public function createCase(array $fields): array
    {
        $payload = array_filter($fields, fn ($v) => $v !== null && $v !== '');
        unset($payload['Origin']);                                   // integrator sets it

        $raw  = $this->dispatch('POST', '/chatbot/cases', $payload);
        $data = $raw['data'] ?? [];

        return ['Id' => $data['id'] ?? null, 'CaseNumber' => $data['CaseNumber'] ?? $data['id'] ?? null] + $payload;
    }

    // ---------- mapping ----------

    private function mapAsset(array $a, string $accountId): array
    {
        return [
            'Id'            => $a['Id'],
            'AccountId'     => $accountId,
            'Quantity'      => (float) ($a['Quantity'] ?? 1),
            'InstallDate'   => $a['StartDate'] ?? null,
            'UsageEndDate'  => $a['EndDate'],
            'Status'        => $a['Status'] ?? null,
            'Auto_Renew__c' => (bool) ($a['AutoRenew'] ?? false),
            'Renewed_By__c' => $a['RenewalOpportunityId'] ?? null,
            'Product2'      => ['Id' => $a['Product2Id'], 'Name' => $a['ProductName'], 'ProductCode' => $a['ProductCode'], 'Family' => $a['ProductFamily'] ?? null],
        ];
    }
}
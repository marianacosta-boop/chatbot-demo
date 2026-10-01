<?php

namespace Tests\Unit;

use App\Services\Salesforce\IntegratorSalesforceAdapter;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegratorSalesforceAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('chatbot.integrator', [
            'base_url' => 'http://integrator.test/api/v1',
            'public_key' => 'public',
            'private_key' => 'private',
            'verify_ssl' => false,
        ]);
    }

    public function test_create_case_resolves_the_public_case_number(): void
    {
        Http::fakeSequence()
            ->push(['status' => 201, 'data' => ['id' => '500-internal']], 201)
            ->push(['status' => 200, 'data' => [[
                'id' => '500-internal',
                'caseNumber' => '00012345',
                'subject' => 'Pedido de desconto',
                'status' => 'New',
                'createdAt' => '2026-10-01T10:47:10Z',
            ]]], 200);

        $case = app(IntegratorSalesforceAdapter::class)->createCase([
            'AccountId' => '001-account',
            'Subject' => 'Pedido de desconto',
            'Description' => 'Pedido do cliente.',
        ]);

        $this->assertSame('00012345', $case['CaseNumber']);
        $this->assertNotSame($case['Id'], $case['CaseNumber']);

        Http::assertSentCount(2);
    }

    public function test_asset_mapping_preserves_the_original_opportunity_id(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 200, 'data' => [[
                'Id' => '02i-asset',
                'Quantity' => 1,
                'StartDate' => '2025-10-09',
                'EndDate' => '2026-10-09',
                'Status' => 'Purchased',
                'AutoRenew' => false,
                'RenewalOpportunityId' => null,
                'OpportunityId' => '006-opportunity',
                'Product2Id' => '01t-product',
                'ProductName' => 'Aquisição de Créditos',
                'ProductCode' => 'ACG017',
            ]]], 200),
        ]);

        $assets = app(IntegratorSalesforceAdapter::class)->activeAssets('001-account');

        $this->assertSame('006-opportunity', $assets[0]['OpportunityId']);
    }
}
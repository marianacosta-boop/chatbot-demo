<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Chat\ClientContextService;
use App\Services\Chat\Tools\EscalateToHuman;
use App\Services\Salesforce\SalesforceAdapter;
use Mockery;
use Tests\TestCase;

class EscalateToHumanTest extends TestCase
{
    public function test_it_links_the_only_expiring_products_opportunity_to_the_case(): void
    {
        $user = new User(['email' => 'client@example.com']);
        $snapshot = [
            'account' => ['id' => '001-account'],
            'contact' => ['id' => '003-contact'],
            'products' => [[
                'asset_id' => '02i-asset',
                'expiring_soon' => true,
            ]],
        ];

        $context = Mockery::mock(ClientContextService::class);
        $context->shouldReceive('snapshot')->once()->with($user)->andReturn($snapshot);
        app()->instance(ClientContextService::class, $context);

        $salesforce = Mockery::mock(SalesforceAdapter::class);
        $salesforce->shouldReceive('asset')
            ->once()
            ->with('02i-asset', '001-account')
            ->andReturn(['OpportunityId' => '006-opportunity']);
        $salesforce->shouldReceive('createCase')
            ->once()
            ->withArgs(fn ($fields) => $fields['Opportunity__c'] === '006-opportunity')
            ->andReturn(['CaseNumber' => '00012345']);

        $result = (new EscalateToHuman($salesforce))->handle([
            'subject' => 'Pedido de desconto',
            'summary' => 'Cliente solicita condições especiais.',
        ], $user);

        $this->assertSame(['status' => 'created', 'case_number' => '00012345'], $result);
    }
}
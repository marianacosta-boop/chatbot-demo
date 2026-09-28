<?php

namespace App\Services\Salesforce;

interface SalesforceAdapter
{
    public function account(string $tin, ?string $email = null): array;
    public function contact(string $email, ?string $accountId = null): array;
    public function activeAssets(string $accountId): array;
    public function asset(string $id, ?string $accountId = null): ?array;
    public function openCases(string $accountId): array;
    public function renewalOptions(array $asset): array;
    public function createOpportunity(array $fields): array;
    public function createCase(array $fields): array;
}
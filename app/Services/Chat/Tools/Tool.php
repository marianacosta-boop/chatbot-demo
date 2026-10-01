<?php

namespace App\Services\Chat\Tools;

use App\Models\User;

interface Tool
{
    public function name(): string;

    /** Definition sent to the model: ['name' => ..., 'description' => ..., 'input_schema' => JSON Schema]. */
    public function definition(): array;

    /** Account-bound tools must never trust an account id coming from the model. */
    public function handle(array $input, ?User $user): array|string;
}

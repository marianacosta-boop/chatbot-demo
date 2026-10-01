<?php

namespace App\Services\Chat\Tools;

use App\Models\User;
use Throwable;

class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    public function __construct(iterable $tools)   // bind the concrete list in AppServiceProvider (see README-wiring.md)
    {
        foreach ($tools as $tool) {
            $this->tools[$tool->name()] = $tool;
        }
    }

    public function definitions(bool $guest = false): array
    {
        $tools = $guest
            ? array_filter($this->tools, fn (Tool $tool) => $tool->name() === 'search_knowledge_base')
            : $this->tools;

        return array_values(array_map(fn (Tool $t) => $t->definition(), $tools));
    }

    public function execute(string $name, array $input, ?User $user): array|string
    {
        if (! isset($this->tools[$name])) {
            return ['error' => "Unknown tool {$name}"];
        }
        if (! $user && $name !== 'search_knowledge_base') {
            return ['error' => 'This tool requires an authenticated client.'];
        }
        try {
            return $this->tools[$name]->handle($input, $user);
        } catch (Throwable $e) {
            report($e);
            return ['error' => 'The tool failed. Apologise and offer to escalate to a human.'];
        }
    }
}

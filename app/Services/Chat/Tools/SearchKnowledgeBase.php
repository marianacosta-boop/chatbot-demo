<?php

namespace App\Services\Chat\Tools;

use App\Models\User;
use App\Services\KnowledgeBase\Retriever;   // pgvector / Meilisearch / Typesense — your choice

class SearchKnowledgeBase implements Tool
{
    public function __construct(private Retriever $retriever) {}

    public function name(): string { return 'search_knowledge_base'; }

    public function definition(): array
    {
        return [
            'name'        => $this->name(),
            'description' => 'Pesquisa a FAQ e a documentação oficial dos produtos. Usa antes de responder a qualquer dúvida sobre funcionamento, conteúdo de um produto, suporte ou procedimentos. Inclui na query o nome do produto quando relevante.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Natural-language search query'],
                ],
                'required' => ['query'],
            ],
        ];
    }

    public function handle(array $input, ?User $user): array
    {
        return ['results' => $this->retriever->search($input['query'], limit: 5)];  // [['title','excerpt','url'], ...]
    }
}
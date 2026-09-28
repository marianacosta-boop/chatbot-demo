<?php

namespace App\Services\KnowledgeBase;

interface Retriever
{
    /** @return array<int, array{title:string, text:string, url:string}> */
    public function search(string $query, int $limit = 5): array;
}

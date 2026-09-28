<?php

namespace App\Services\KnowledgeBase;

use App\Models\KnowledgeChunk;
use Illuminate\Support\Str;

/**
 * Keyword retriever over the knowledge_chunks table. Good up to a few hundred chunks;
 * swap for pgvector/Meilisearch when the base grows or paraphrased questions start missing.
 */
class DbRetriever implements Retriever
{
    public function search(string $query, int $limit = 5): array
    {
        $words = collect(preg_split('/\W+/u', Str::lower(Str::ascii($query))))
            ->filter(fn ($w) => mb_strlen($w) > 3)->unique()->values();

        if ($words->isEmpty()) {
            return [];
        }

        return KnowledgeChunk::all()
            ->map(function ($c) use ($words) {
                $hay   = Str::lower(Str::ascii($c->title . ' ' . $c->text . ' ' . $c->product_code));
                $score = $words->sum(fn ($w) => substr_count($hay, $w))
                       + $words->sum(fn ($w) => str_contains(Str::lower(Str::ascii($c->title)), $w) ? 3 : 0);
                return ['score' => $score, 'chunk' => $c];
            })
            ->filter(fn ($r) => $r['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->map(fn ($r) => [
                'title'        => $r['chunk']->title,
                'product_code' => $r['chunk']->product_code,
                'text'         => Str::limit($r['chunk']->text, 1200),
                'url'          => $r['chunk']->url,
            ])
            ->values()->all();
    }
}

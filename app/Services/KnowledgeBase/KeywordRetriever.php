<?php

namespace App\Services\KnowledgeBase;

use Illuminate\Support\Str;

/** Tiny keyword matcher over an in-code FAQ. Replace with pgvector/Meilisearch when the FAQ grows. */
class KeywordRetriever implements Retriever
{
    private array $faq = [
        ['title' => 'Horário do suporte', 'url' => '/ajuda/suporte',
         'text' => 'O suporte técnico funciona nos dias úteis das 9h às 18h. Clientes com Suporte Premium têm linha prioritária e resposta em menos de 2 horas.'],
        ['title' => 'Adicionar utilizadores', 'url' => '/ajuda/utilizadores',
         'text' => 'Um administrador pode adicionar utilizadores em Definições > Utilizadores > Convidar. Cada licença Standard inclui o número de utilizadores contratado; para mais, contacte o gestor de conta.'],
        ['title' => 'Faturas e pagamentos', 'url' => '/ajuda/faturas',
         'text' => 'As faturas são emitidas no início de cada período contratual e enviadas por e-mail ao contacto de faturação. Podem ser consultadas em Conta > Faturas.'],
        ['title' => 'Recuperar palavra-passe', 'url' => '/ajuda/palavra-passe',
         'text' => 'Na página de entrada, escolha "Esqueci-me da palavra-passe" e siga o link enviado por e-mail. O link é válido por 30 minutos.'],
        ['title' => 'Renovação automática', 'url' => '/ajuda/renovacao',
         'text' => 'Os produtos com renovação automática são renovados no fim do período sem ação do cliente. Pode alterar esta opção junto do gestor de conta até 30 dias antes do fim do contrato.'],
    ];

    public function search(string $query, int $limit = 5): array
    {
        $words = array_filter(preg_split('/\W+/u', Str::lower($query)), fn ($w) => mb_strlen($w) > 3);

        return collect($this->faq)
            ->map(function ($f) use ($words) {
                $hay = Str::lower($f['title'] . ' ' . $f['text']);
                $score = count(array_filter($words, fn ($w) => str_contains($hay, $w)));
                return ['score' => $score] + $f;
            })
            ->filter(fn ($f) => $f['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->map(fn ($f) => ['title' => $f['title'], 'text' => $f['text'], 'url' => $f['url']])
            ->values()->all();
    }
}

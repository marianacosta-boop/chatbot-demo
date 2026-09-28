<?php

namespace App\Console\Commands;

use App\Models\KnowledgeChunk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Loads resources/knowledge/*.md into knowledge_chunks.
 * Each file: optional header lines "product_code: X" and "url: /ajuda/x", then sections split by "## ".
 */
class ImportKnowledge extends Command
{
    protected $signature   = 'chatbot:import-knowledge {--path=resources/knowledge}';
    protected $description = 'Import markdown documentation into the chatbot knowledge base';

    public function handle(): int
    {
        $dir   = base_path($this->option('path'));
        $files = File::glob($dir . '/*.md');
        if (! $files) {
            $this->error("No .md files in {$dir}");
            return 1;
        }

        KnowledgeChunk::truncate();
        $count = 0;

        foreach ($files as $file) {
            $raw    = File::get($file);
            $source = basename($file);
            $meta   = ['product_code' => null, 'url' => null];

            // header lines "key: value" before the first heading
            foreach (preg_split('/\R/', $raw) as $line) {
                if (str_starts_with($line, '#')) break;
                if (preg_match('/^(product_code|url):\s*(.+)$/', trim($line), $m)) {
                    $meta[$m[1]] = trim($m[2]);
                }
            }

            $sections = preg_split('/^##\s+/m', $raw);
            array_shift($sections);   // drop everything before the first "## "

            foreach ($sections as $section) {
                [$title, $body] = array_pad(explode("\n", $section, 2), 2, '');
                $body = trim($body);
                if ($body === '') continue;

                KnowledgeChunk::create([
                    'source'       => $source,
                    'product_code' => $meta['product_code'],
                    'title'        => trim($title),
                    'text'         => $body,
                    'url'          => $meta['url'],
                ]);
                $count++;
            }
            $this->line("  {$source}: " . count($sections) . ' sections');
        }

        $this->info("Imported {$count} chunks from " . count($files) . ' files.');
        return 0;
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('knowledge_chunks', function (Blueprint $t) {
            $t->id();
            $t->string('source');            // file name, e.g. servicos-avancados.md
            $t->string('product_code')->nullable()->index();   // optional: ties a chunk to a product
            $t->string('title');             // section heading
            $t->text('text');                // the passage the model reads
            $t->string('url')->nullable();   // link to the public help page, if any
            $t->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('knowledge_chunks'); }
};

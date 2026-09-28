<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeChunk extends Model
{
    protected $fillable = ['source', 'product_code', 'title', 'text', 'url'];
}

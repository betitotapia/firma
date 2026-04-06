<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentVersion extends Model
{
    protected $fillable = [
        'document_id','type','storage_disk','storage_path','original_filename','mime_type','size_bytes'
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}

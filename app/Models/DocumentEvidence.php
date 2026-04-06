<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentEvidence extends Model
{
    protected $table = 'document_evidences';
    
    protected $fillable = [
        'document_id',
        'document_version_id',
        'stage',
        'evidence_id',
        'evidence_sha256',
        'evidence_json',
    ];

    protected $casts = [
        'evidence_id' => 'string',
    ];
}

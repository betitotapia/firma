<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentLiveness extends Model
{
    protected $table = 'document_liveness';

    protected $fillable = [
        'document_id','signer_id','token_id','type','challenge',
        'storage_disk','storage_path','mime_type','size_bytes','sha256',
        'captured_at','captured_ip','user_agent',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
    ];
}
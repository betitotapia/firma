<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentToken extends Model
{
    protected $fillable = ['document_id','recipient_id', 'signer_id', 'token_hash','purpose','expires_at','status','consumed_at'];
    protected $casts = ['expires_at' => 'datetime', 'consumed_at' => 'datetime'];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}

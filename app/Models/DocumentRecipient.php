<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentRecipient extends Model
{
    protected $fillable = ['document_id','name','email','status','signed_at'];
    protected $casts = ['signed_at' => 'datetime'];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class DocumentEvent extends Model
{
    protected $fillable = ['document_id','event_type','occurred_at','ip','user_agent','metadata'];
    protected $casts = ['occurred_at' => 'datetime', 'metadata' => 'array'];

    public static function log(int $documentId, string $type, Request $request, array $metadata = []): self
    {
        return self::create([
            'document_id' => $documentId,
            'event_type' => $type,
            'occurred_at' => now(),
            'ip' => $request->ip(),
            'user_agent' => substr((string)$request->userAgent(), 0, 512),
            'metadata' => $metadata,
        ]);
    }
}

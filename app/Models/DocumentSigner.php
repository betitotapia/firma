<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentSigner extends Model
{
    use HasFactory;

    protected $table = 'document_signers';

    protected $fillable = [
        'document_id',
        'role',
        'display_role',
        'sign_order',
        'name',
        'email',
        'status',
        'signed_at',
        'signature_path',
        'signed_ip',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */
    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers útiles
    |--------------------------------------------------------------------------
    */
    public function isSigned(): bool
    {
        return $this->status === 'signed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isEmployee(): bool
    {
        return $this->role === 'employee';
    }

    public function isDirector(): bool
    {
        return $this->role === 'director';
    }

    public function getRoleLabelAttribute(): string
    {
        if (!empty($this->display_role)) {
            return $this->display_role;
        }

        return match ($this->role) {
            'director' => 'Patrón / Directivo',
            'employee' => 'Trabajador',
            default => 'Firmante',
        };
    }
}
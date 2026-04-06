<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    protected $fillable = ['title','status','created_by_user_id'];

    public function recipient()
    {
        return $this->hasOne(DocumentRecipient::class);
    }

    public function versions()
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function tokens()
    {
        return $this->hasMany(DocumentToken::class);
    }

    public function events()
    {
        return $this->hasMany(DocumentEvent::class);
    }

    public function hashes()
    {
        return $this->hasOne(DocumentHash::class);
    }
    use SoftDeletes;
}


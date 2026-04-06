<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    protected $fillable = ['document_token_id','recipient_email','code_hash','expires_at','used_at'];
    protected $casts = ['expires_at' => 'datetime', 'used_at' => 'datetime'];
}

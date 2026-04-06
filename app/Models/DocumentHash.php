<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentHash extends Model
{
    protected $fillable = ['document_id','original_sha256','signed_sha256'];
}

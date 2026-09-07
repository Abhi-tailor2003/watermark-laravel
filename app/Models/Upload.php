<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Upload extends Model
{
    protected $fillable = ['original_name', 'stored_path', 'mime_type', 'extension', 'uploaded_by_user_id', 'watermark_ids'];

    protected function casts(): array
    {
        return ['watermark_ids' => 'array'];
    }
}
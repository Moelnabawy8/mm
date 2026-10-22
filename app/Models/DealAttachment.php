<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DealAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'deal_id',
        'file_name',
        'file_path',
        'file_type',
        'mime_type',
        'file_size',
        'uploaded_by',
    ];

    protected $appends = ['file_url'];

    public function getFileUrlAttribute()
    {
        return config('app.url') . '/public/storage/' . $this->file_path;
    }

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    /**
     * Get the user who uploaded the attachment.
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Check if the attachment is an image.
     */
    public function isImage()
    {
        return $this->file_type === 'image';
    }

    /**
     * Get formatted file size.
     */
    public function getFormattedSizeAttribute()
    {
        $bytes = $this->file_size;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }
}

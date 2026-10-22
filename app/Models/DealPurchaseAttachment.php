<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class DealPurchaseAttachment extends Model
{
    use HasFactory;

    protected $appends = ['file_url'];

    protected $fillable = [
        'deal_purchase_id',
        'file_name',
        'file_path',
        'file_type',
        'mime_type',
        'file_size',
        'uploaded_by',
    ];

    public function getFileUrlAttribute(): string
    {
        return config('app.url') . '/public/storage/' . $this->file_path;
    }

    /**
     * Get the deal purchase that owns the attachment.
     */
    public function dealPurchase()
    {
        return $this->belongsTo(DealPurchase::class);
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

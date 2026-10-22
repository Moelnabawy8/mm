<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveAttachment extends Model
{
    protected $appends = ['file_url'];

    protected $fillable = [
        'leave_id',
        'file_name',
        'original_name',
        'file_type',
        'file_size',
        'file_path',
        'uploaded_by',
    ];

    /**
     * Get the leave that owns this attachment.
     */
    public function leave()
    {
        return $this->belongsTo(Leave::class, 'leave_id');
    }

    /**
     * Get the user who uploaded this attachment.
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the full URL to the file.
     */
    public function getFileUrlAttribute()
    {
        return asset($this->file_path);
    }

    /**
     * Get human-readable file size.
     */
    public function getFileSizeFormattedAttribute()
    {
        $bytes = $this->file_size;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    /**
     * Get appropriate icon class based on file type.
     */
    public function getFileIconAttribute()
    {
        $extension = strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));

        $icons = [
            'pdf' => 'ti ti-file-type-pdf',
            'doc' => 'ti ti-file-type-doc',
            'docx' => 'ti ti-file-type-docx',
            'txt' => 'ti ti-file-text',
            'jpg' => 'ti ti-photo',
            'jpeg' => 'ti ti-photo',
            'png' => 'ti ti-photo',
            'gif' => 'ti ti-photo',
            'webp' => 'ti ti-photo',
        ];

        return $icons[$extension] ?? 'ti ti-file';
    }

    /**
     * Check if the attachment is an image.
     */
    public function isImage()
    {
        $imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        $extension = strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
        return in_array($extension, $imageTypes);
    }
}

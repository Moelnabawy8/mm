<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketAttachment extends Model
{
    protected $fillable = [
        'support_id',
        'reply_id',
        'file_name',
        'original_name',
        'file_type',
        'file_size',
        'file_path',
        'uploaded_by',
        'is_public_upload',
    ];

    protected $casts = [
        'is_public_upload' => 'boolean',
    ];

    public function ticket()
    {
        return $this->belongsTo(Support::class, 'support_id');
    }

    public function reply()
    {
        return $this->belongsTo(SupportReply::class, 'reply_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getFileUrlAttribute()
    {
        return config('app.url') . '/public/storage/' . $this->file_path;
    }

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

    public function getFileIconAttribute()
    {
        $extension = strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
        
        $icons = [
            'pdf' => 'ti ti-file-type-pdf',
            'doc' => 'ti ti-file-type-doc',
            'docx' => 'ti ti-file-type-docx',
            'xls' => 'ti ti-file-spreadsheet',
            'xlsx' => 'ti ti-file-spreadsheet',
            'jpg' => 'ti ti-photo',
            'jpeg' => 'ti ti-photo',
            'png' => 'ti ti-photo',
            'gif' => 'ti ti-photo',
            'zip' => 'ti ti-file-zip',
            'rar' => 'ti ti-file-zip',
        ];

        return $icons[$extension] ?? 'ti ti-file';
    }

    public function isImage()
    {
        $imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        $extension = strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
        return in_array($extension, $imageTypes);
    }
}

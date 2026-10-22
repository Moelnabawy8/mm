<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketCustomField extends Model
{
    protected $fillable = [
        'name',
        'name_ar',
        'type',
        'options',
        'placeholder',
        'placeholder_ar',
        'is_required',
        'is_active',
        'order',
        'created_by',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'options' => 'array',
    ];

    public static $fieldTypes = [
        'text' => 'Text',
        'textarea' => 'Textarea',
        'select' => 'Select Dropdown',
        'radio' => 'Radio Buttons',
        'checkbox' => 'Checkboxes',
        'number' => 'Number',
        'email' => 'Email',
        'phone' => 'Phone',
        'file' => 'File Upload',
        'date' => 'Date',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc');
    }

    public function getLocalizedNameAttribute()
    {
        $locale = app()->getLocale();
        if ($locale === 'ar' && !empty($this->name_ar)) {
            return $this->name_ar;
        }
        return $this->name;
    }

    public function getLocalizedPlaceholderAttribute()
    {
        $locale = app()->getLocale();
        if ($locale === 'ar' && !empty($this->placeholder_ar)) {
            return $this->placeholder_ar;
        }
        return $this->placeholder;
    }

    public function getOptionsArrayAttribute()
    {
        if (is_array($this->options)) {
            return $this->options;
        }
        return json_decode($this->options, true) ?? [];
    }
}

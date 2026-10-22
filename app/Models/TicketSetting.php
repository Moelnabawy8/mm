<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TicketSetting extends Model
{
    protected $fillable = [
        'portal_enabled',
        'portal_title',
        'portal_title_ar',
        'portal_description',
        'portal_description_ar',
        'portal_logo',
        'portal_primary_color',
        'portal_secondary_color',
        'require_email',
        'require_phone',
        'allow_attachments',
        'max_attachments',
        'allowed_file_types',
        'max_file_size',
        'email_notifications',
        'whatsapp_notifications',
        'success_message',
        'success_message_ar',
        'portal_slug',
        'created_by',
    ];

    protected $casts = [
        'portal_enabled' => 'boolean',
        'require_email' => 'boolean',
        'require_phone' => 'boolean',
        'allow_attachments' => 'boolean',
        'email_notifications' => 'boolean',
        'whatsapp_notifications' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function getSettings($createdBy)
    {
        $settings = self::where('created_by', $createdBy)->first();
        
        if (!$settings) {
            $settings = self::create([
                'portal_enabled' => true,
                'portal_title' => 'Support Portal',
                'portal_title_ar' => 'بوابة الدعم الفني',
                'portal_description' => 'Submit your support request and we will get back to you as soon as possible.',
                'portal_description_ar' => 'أرسل طلب الدعم الخاص بك وسنتواصل معك في أقرب وقت ممكن.',
                'require_email' => true,
                'require_phone' => false,
                'allow_attachments' => true,
                'max_attachments' => 5,
                'allowed_file_types' => 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx',
                'max_file_size' => 5,
                'email_notifications' => true,
                'whatsapp_notifications' => false,
                'success_message' => 'Your ticket has been submitted successfully. You will receive a confirmation email shortly.',
                'success_message_ar' => 'تم إرسال تذكرتك بنجاح. ستتلقى بريدًا إلكترونيًا للتأكيد قريبًا.',
                'portal_slug' => Str::random(10),
                'created_by' => $createdBy,
            ]);
        }

        return $settings;
    }

    public function getLocalizedTitleAttribute()
    {
        $locale = app()->getLocale();
        if ($locale === 'ar' && !empty($this->portal_title_ar)) {
            return $this->portal_title_ar;
        }
        return $this->portal_title;
    }

    public function getLocalizedDescriptionAttribute()
    {
        $locale = app()->getLocale();
        if ($locale === 'ar' && !empty($this->portal_description_ar)) {
            return $this->portal_description_ar;
        }
        return $this->portal_description;
    }

    public function getLocalizedSuccessMessageAttribute()
    {
        $locale = app()->getLocale();
        if ($locale === 'ar' && !empty($this->success_message_ar)) {
            return $this->success_message_ar;
        }
        return $this->success_message;
    }

    public function getAllowedFileTypesArrayAttribute()
    {
        return array_map('trim', explode(',', $this->allowed_file_types));
    }

    public function getPortalUrlAttribute()
    {
        return url('/ticket-portal/' . $this->portal_slug);
    }
}

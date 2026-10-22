<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per WhatsApp notification: the outbox entry and the audit record are the same thing.
 *
 * Rows are created only by App\Support\WhatsApp\TaskNotifier (raw INSERT ... ON DUPLICATE KEY,
 * so the dedupe_key unique index can count suppressed re-fires) and mutated only by
 * App\Console\Commands\WhatsAppDispatch.
 */
class WhatsAppMessage extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'user_id', 'phone', 'task_id', 'project_id',
        'event_type', 'template_name', 'language',
        'payload', 'body_preview', 'status',
        'attempts', 'duplicate_hits', 'next_attempt_at', 'claim_token',
        'provider_message_id', 'error_code', 'error_message',
        'sent_at', 'delivered_at', 'read_at',
        'created_by', 'dedupe_key',
    ];

    protected $casts = [
        'payload'         => 'array',
        'next_attempt_at' => 'datetime',
        'sent_at'         => 'datetime',
        'delivered_at'    => 'datetime',
        'read_at'         => 'datetime',
    ];

    public const EVENT_LABELS = [
        'assign'  => 'إسناد مهمة',
        'stage'   => 'تغيير حالة',
        'comment' => 'تعليق جديد',
        'due'     => 'تذكير تسليم',
        'test'    => 'رسالة اختبار',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function task()
    {
        return $this->belongsTo(ProjectTask::class, 'task_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'sent'                 => 'primary',
            'delivered', 'read'    => 'success',
            'failed'               => 'danger',
            'sending'              => 'info',
            'cancelled'            => 'secondary',
            default                => 'warning',
        };
    }

    public function eventLabel(): string
    {
        return self::EVENT_LABELS[$this->event_type] ?? $this->event_type;
    }

    /** Scope to the company that owns the data (settings and tasks are per-company). */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('created_by', $companyId);
    }
}

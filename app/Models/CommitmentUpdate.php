<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One check-in against a commitment. See the migration for why these exist. */
class CommitmentUpdate extends Model
{
    protected $fillable = [
        'commitment_id',
        'user_id',
        'note',
        'progress',
        'status_after',
        'created_by',
    ];

    protected $casts = [
        'progress' => 'integer',
    ];

    public function commitment()
    {
        return $this->belongsTo(Commitment::class, 'commitment_id', 'id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionEvent extends Model
{
    protected $fillable = [
        'enrollment_id', 'arb_subscription_id', 'event_type', 'payload', 'note',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class, 'enrollment_id');
    }
}

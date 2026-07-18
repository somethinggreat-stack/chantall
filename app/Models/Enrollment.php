<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    protected $fillable = [
        'plan', 'amount', 'first_name', 'last_name', 'email', 'phone',
        'address', 'city', 'state', 'zip',
        'agree_terms', 'agree_privacy', 'agree_marketing', 'status',
        'transaction_id', 'invoice_number', 'auth_code', 'card_type', 'card_last4', 'payment_status', 'paid_at',
    ];

    protected $casts = [
        'agree_terms'     => 'boolean',
        'agree_privacy'   => 'boolean',
        'agree_marketing' => 'boolean',
        'amount'          => 'decimal:2',
        'paid_at'         => 'datetime',
    ];
}

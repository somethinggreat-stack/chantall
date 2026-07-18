<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundingLead extends Model
{
    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone',
        'funding_goal', 'confirmation', 'credit_cards', 'credit_utilization',
        'credit_score', 'business_situation', 'annual_income', 'credit_profile',
        'answers', 'status',
    ];

    protected $casts = [
        'answers' => 'array',
    ];
}

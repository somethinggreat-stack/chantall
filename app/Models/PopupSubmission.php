<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PopupSubmission extends Model
{
    protected $fillable = [
        'name', 'email', 'phone', 'interests', 'source', 'page', 'status',
    ];
}

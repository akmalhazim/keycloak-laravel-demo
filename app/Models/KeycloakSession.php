<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KeycloakSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'keycloak_session_id', 'user'
    ];

    protected $casts = [
        'user' => 'array'
    ];
}

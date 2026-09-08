<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; 

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'student_card',      
        'career_id',         
        'current_year',     
        'status',           
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'current_year' => 'integer',
    ];

    
    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class, 'career_id');
    }

    
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    
    public function scholarships(): HasMany
    {
        return $this->hasMany(Scholarship::class);
    }

    
    public function tutorings(): HasMany
    {
        return $this->hasMany(Tutoring::class);
    }

    
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
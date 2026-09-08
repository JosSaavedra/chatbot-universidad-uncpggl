<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['session_id', 'user_id', 'title', 'student_code'];

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}

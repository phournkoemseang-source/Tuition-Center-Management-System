<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $fillable = [
        'full_name',
        'phone',
        'parent_contact',
        'enrolled_date',
        'status',
    ];

    protected $casts = [
        'enrolled_date' => 'date',
    ];

    public function classRooms(): BelongsToMany
    {
        return $this->belongsToMany(ClassRoom::class, 'enrollments')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}

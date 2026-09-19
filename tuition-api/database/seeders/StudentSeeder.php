<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $students = [
            ['name' => 'Ryan Chan', 'email' => 'ryan@student.test', 'dob' => '2009-03-14', 'level' => 'Secondary 3', 'contact' => '91234567'],
            ['name' => 'Mia Wong', 'email' => 'mia@student.test', 'dob' => '2010-07-22', 'level' => 'Secondary 2', 'contact' => '92345678'],
            ['name' => 'Ethan Ng', 'email' => 'ethan@student.test', 'dob' => '2008-11-02', 'level' => 'Secondary 4', 'contact' => '93456789'],
            ['name' => 'Chloe Teo', 'email' => 'chloe@student.test', 'dob' => '2011-01-30', 'level' => 'Primary 6', 'contact' => '94567890'],
            ['name' => 'Kai Fernandez', 'email' => 'kai@student.test', 'dob' => '2009-09-09', 'level' => 'Secondary 3', 'contact' => '95678901'],
        ];

        foreach ($students as $s) {
            $user = User::create([
                'name' => $s['name'],
                'email' => $s['email'],
                'password' => 'password',
            ]);

            Student::create([
                'user_id' => $user->id,
                'date_of_birth' => $s['dob'],
                'education_level' => $s['level'],
                'parent_contact' => $s['contact'],
            ]);
        }
    }
}

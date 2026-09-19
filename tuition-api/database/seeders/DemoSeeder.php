<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // --- Users: admin + teachers ---
        $admin = User::create([
            'name' => 'Center Admin',
            'email' => 'admin@tuition.test',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $teacherUsers = [
            ['name' => 'Sokha Kim', 'email' => 'sokha@tuition.test'],
            ['name' => 'Dara Chan', 'email' => 'dara@tuition.test'],
        ];

        $teachers = collect();
        foreach ($teacherUsers as $i => $t) {
            $user = User::create([
                'name' => $t['name'],
                'email' => $t['email'],
                'password' => 'password',
                'role' => 'teacher',
            ]);

            $teachers->push(Teacher::create([
                'full_name' => $t['name'],
                'subject' => ['English', 'Computer'][ $i % 2 ],
                'user_id' => $user->id,
            ]));
        }

        // --- Students ---
        $studentRows = [
            ['Srey Nith Reaksa', '012 345 678', '011 999 888'],
            ['Sok Piseth', '092 111 222', '012 333 444'],
            ['Chan Dara', '070 555 666', null],
            ['Nou Sreymom', '097 777 888', '010 222 333'],
            ['Ly Vuthy', '089 234 567', null],
            ['Pov Chanlina', '011 654 321', '012 987 654'],
            ['Yin Sovann', '096 456 789', null],
            ['Tep Chantrea', '015 888 999', '099 123 456'],
        ];

        $students = collect();
        foreach ($studentRows as [$name, $phone, $parent]) {
            $students->push(Student::create([
                'full_name' => $name,
                'phone' => $phone,
                'parent_contact' => $parent,
                'enrolled_date' => now()->subDays(rand(10, 120))->toDateString(),
                'status' => 'active',
            ]));
        }

        // --- Classes ---
        $classes = collect([
            [
                'name' => 'English Basic — Morning',
                'teacher' => 0,
                'schedule' => 'Mon/Wed/Fri 8:00–10:00',
                'fee' => 30.00,
            ],
            [
                'name' => 'English Intermediate — Evening',
                'teacher' => 0,
                'schedule' => 'Tue/Thu 17:30–19:30',
                'fee' => 35.00,
            ],
            [
                'name' => 'Computer Basics — Weekend',
                'teacher' => 1,
                'schedule' => 'Sat/Sun 9:00–11:00',
                'fee' => 40.00,
            ],
            [
                'name' => 'MS Word & Excel — Evening',
                'teacher' => 1,
                'schedule' => 'Mon/Thu 18:00–20:00',
                'fee' => 45.00,
            ],
        ])->map(function (array $c) use ($teachers) {
            return ClassRoom::create([
                'name' => $c['name'],
                'teacher_id' => $teachers[$c['teacher']]->id,
                'schedule' => $c['schedule'],
                'fee_amount' => $c['fee'],
            ]);
        });

        // --- Enrollments: every student in 1–2 classes ---
        $pairs = collect();
        foreach ($students as $student) {
            $classIds = $classes->random(rand(1, 2))->pluck('id');
            foreach ($classIds as $classId) {
                $key = $student->id.':'.$classId;
                if ($pairs->contains($key)) {
                    continue;
                }
                $pairs->push($key);

                Enrollment::create([
                    'student_id' => $student->id,
                    'class_room_id' => $classId,
                    'status' => 'active',
                ]);

                // --- Payments: this month + last month history ---
                $class = $classes->firstWhere('id', $classId);

                // Last month: mostly paid
                Payment::create([
                    'student_id' => $student->id,
                    'class_room_id' => $class->id,
                    'amount' => $class->fee_amount,
                    'due_date' => now()->subMonth()->startOfMonth()->toDateString(),
                    'paid_date' => rand(1, 10) <= 8 ? now()->subMonth()->startOfMonth()->addDays(rand(0, 10))->toDateString() : null,
                    'status' => rand(1, 10) <= 8 ? 'paid' : 'overdue',
                    'method' => rand(1, 10) <= 8 ? ['cash', 'bank'][rand(0, 1)] : null,
                ]);

                // This month: a mix of unpaid / paid / overdue
                $thisMonthStatus = ['unpaid', 'unpaid', 'unpaid', 'paid', 'paid', 'overdue'][rand(0, 5)];
                Payment::create([
                    'student_id' => $student->id,
                    'class_room_id' => $class->id,
                    'amount' => $class->fee_amount,
                    'due_date' => now()->startOfMonth()->toDateString(),
                    'paid_date' => $thisMonthStatus === 'paid' ? now()->subDays(rand(0, 5))->toDateString() : null,
                    'status' => $thisMonthStatus,
                    'method' => $thisMonthStatus === 'paid' ? ['cash', 'bank'][rand(0, 1)] : null,
                ]);
            }
        }

        // --- Attendance: past 7 days for each class (skip weekends per schedule vibe) ---
        foreach ($classes as $class) {
            $enrolled = $class->enrollments()->pluck('student_id');
            foreach (range(1, 7) as $daysAgo) {
                $date = now()->subDays($daysAgo)->toDateString();
                foreach ($enrolled as $studentId) {
                    $roll = rand(1, 100);
                    $status = match (true) {
                        $roll <= 80 => 'present',
                        $roll <= 90 => 'late',
                        default => 'absent',
                    };

                    Attendance::create([
                        'student_id' => $studentId,
                        'class_room_id' => $class->id,
                        'date' => $date,
                        'status' => $status,
                    ]);
                }
            }
        }
    }
}

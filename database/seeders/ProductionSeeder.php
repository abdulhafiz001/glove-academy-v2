<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\ClassSubject;
use Illuminate\Support\Facades\Hash;

class ProductionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * This seeder creates only the essential data needed for production:
     * - Admin user
     * - School classes
     * - Subjects
     * - Class-subject assignments
     */
    public function run(): void
    {
        // Create admin user
        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Admin User',
                'email' => 'admin@gloveacademy.edu.ng',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'phone' => '08125275999',
                'address' => 'BESIDE ASSEMBLIES OF GOD CHURCH ZONE 9 LUGBE ABUJA',
                'is_active' => true,
            ]
        );

        if ($this->command) {
            $this->command->info('Admin user created successfully!');
            $this->command->info('Username: admin');
            $this->command->info('Password: password');
            $this->command->warn('Please change the default password after first login!');
        }

        // Create school classes
        $classes = [
            'Nursery one',
            'Nursery two',
            'Elementary One',
            'Elementary two',
            'Primary one',
            'Primary two',
            'Primary three',
            'Primary four',
            'Primary five',
            'JSS 1',
            'JSS 2',
            'JSS 3',
            'SS 1',
            'SS 2',
            'SS 3'
        ];

        foreach ($classes as $className) {
            SchoolClass::firstOrCreate(
                ['name' => $className],
                [
                    'description' => $className . ' class',
                    'is_active' => true,
                ]
            );
        }

        if ($this->command) {
            $this->command->info('School classes created successfully!');
        }

        // Create subjects
        $subjects = [
            ['name' => 'Mathematics', 'code' => 'MATH'],
            ['name' => 'English Language', 'code' => 'ENG'],
            ['name' => 'Physics', 'code' => 'PHY'],
            ['name' => 'Chemistry', 'code' => 'CHEM'],
            ['name' => 'Biology', 'code' => 'BIO'],
            ['name' => 'Geography', 'code' => 'GEO'],
            ['name' => 'History', 'code' => 'HIST'],
            ['name' => 'Economics', 'code' => 'ECON'],
            ['name' => 'Government', 'code' => 'GOVT'],
            ['name' => 'Literature', 'code' => 'LIT'],
            ['name' => 'Further Mathematics', 'code' => 'FURMATH'],
            ['name' => 'Computer Science', 'code' => 'COMP'],
            ['name' => 'Agricultural Science', 'code' => 'AGRIC'],
            ['name' => 'Christian Religious Studies', 'code' => 'CRS'],
            ['name' => 'Islamic Religious Studies', 'code' => 'IRS'],
            ['name' => 'French', 'code' => 'FRENCH'],
            ['name' => 'Yoruba', 'code' => 'YORUBA'],
            ['name' => 'Igbo', 'code' => 'IGBO'],
            ['name' => 'Hausa', 'code' => 'HAUSA'],
            ['name' => 'Business Studies', 'code' => 'BUS'],
            ['name' => 'Accounting', 'code' => 'ACC'],
            ['name' => 'Commerce', 'code' => 'COMM'],
            ['name' => 'Fine Arts', 'code' => 'ART'],
            ['name' => 'Music', 'code' => 'MUSIC'],
            ['name' => 'Physical Education', 'code' => 'PE'],
        ];

        foreach ($subjects as $subjectData) {
            Subject::firstOrCreate(
                ['name' => $subjectData['name']],
                [
                    'code' => $subjectData['code'],
                    'description' => $subjectData['name'] . ' subject',
                    'is_active' => true,
                ]
            );
        }

        if ($this->command) {
            $this->command->info('Subjects created successfully!');
        }

        // Assign subjects to classes
        $classSubjectAssignments = [
            // Nursery classes - Basic subjects
            ['Nursery one', ['Mathematics', 'English Language', 'Fine Arts', 'Physical Education']],
            ['Nursery two', ['Mathematics', 'English Language', 'Fine Arts', 'Physical Education']],
            // Elementary classes
            ['Elementary One', ['Mathematics', 'English Language', 'Fine Arts', 'Physical Education']],
            ['Elementary two', ['Mathematics', 'English Language', 'Fine Arts', 'Physical Education']],
            // Primary classes
            ['Primary one', ['Mathematics', 'English Language', 'Geography', 'History', 'Fine Arts', 'Physical Education']],
            ['Primary two', ['Mathematics', 'English Language', 'Geography', 'History', 'Fine Arts', 'Physical Education']],
            ['Primary three', ['Mathematics', 'English Language', 'Geography', 'History', 'Fine Arts', 'Physical Education']],
            ['Primary four', ['Mathematics', 'English Language', 'Geography', 'History', 'Fine Arts', 'Physical Education']],
            ['Primary five', ['Mathematics', 'English Language', 'Geography', 'History', 'Fine Arts', 'Physical Education']],
            // JSS classes
            ['JSS 1', ['Mathematics', 'English Language', 'Physics', 'Chemistry', 'Biology', 'Geography', 'History', 'Christian Religious Studies', 'French', 'Business Studies', 'Fine Arts', 'Physical Education']],
            ['JSS 2', ['Mathematics', 'English Language', 'Physics', 'Chemistry', 'Biology', 'Geography', 'History', 'Christian Religious Studies', 'French', 'Business Studies', 'Fine Arts', 'Physical Education']],
            ['JSS 3', ['Mathematics', 'English Language', 'Physics', 'Chemistry', 'Biology', 'Geography', 'History', 'Christian Religious Studies', 'French', 'Business Studies', 'Fine Arts', 'Physical Education']],
            // SS classes
            ['SS 1', ['Mathematics', 'English Language', 'Physics', 'Chemistry', 'Biology', 'Economics', 'Government', 'Literature', 'Christian Religious Studies', 'French', 'Business Studies', 'Fine Arts', 'Physical Education']],
            ['SS 2', ['Mathematics', 'English Language', 'Physics', 'Chemistry', 'Biology', 'Economics', 'Government', 'Literature', 'Christian Religious Studies', 'French', 'Business Studies', 'Fine Arts', 'Physical Education']],
            ['SS 3', ['Mathematics', 'English Language', 'Physics', 'Chemistry', 'Biology', 'Economics', 'Government', 'Literature', 'Christian Religious Studies', 'French', 'Business Studies', 'Fine Arts', 'Physical Education']],
        ];

        foreach ($classSubjectAssignments as $assignment) {
            $className = $assignment[0];
            $subjectNames = $assignment[1];
            
            $class = SchoolClass::where('name', $className)->first();
            
            foreach ($subjectNames as $subjectName) {
                $subject = Subject::where('name', $subjectName)->first();
                
                if ($class && $subject) {
                    ClassSubject::firstOrCreate(
                        [
                            'class_id' => $class->id,
                            'subject_id' => $subject->id,
                        ],
                        [
                            'is_active' => true,
                        ]
                    );
                }
            }
        }

        if ($this->command) {
            $this->command->info('Class-subject assignments created successfully!');
            $this->command->info('Production data seeded successfully!');
            $this->command->info('The school admin can now add teachers and students through the admin panel.');
        }
    }
}

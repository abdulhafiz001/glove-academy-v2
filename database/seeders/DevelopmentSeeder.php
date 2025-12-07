<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Student;
use App\Models\ClassSubject;
use App\Models\TeacherSubject;
use App\Models\StudentSubject;
use App\Models\Score;
use Illuminate\Support\Facades\Hash;

class DevelopmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * This seeder creates sample data for development and testing.
     */
    public function run(): void
    {
        // Create sample teachers
        $teachers = [
            [
                'name' => 'John Smith',
                'email' => 'john.smith@gloveacademy.edu.ng',
                'username' => 'jsmith',
                'phone' => '+234 802 345 6789',
                'address' => '456 Teacher Avenue, Lagos',
            ],
            [
                'name' => 'Sarah Johnson',
                'email' => 'sarah.johnson@gloveacademy.edu.ng',
                'username' => 'sjohnson',
                'phone' => '+234 803 456 7890',
                'address' => '789 Educator Road, Lagos',
            ],
            [
                'name' => 'Michael Brown',
                'email' => 'michael.brown@gloveacademy.edu.ng',
                'username' => 'mbrown',
                'phone' => '+234 804 567 8901',
                'address' => '321 Instructor Lane, Lagos',
            ],
        ];

        foreach ($teachers as $teacherData) {
            User::firstOrCreate(
                ['username' => $teacherData['username']],
                [
                    ...$teacherData,
                    'password' => Hash::make('password'),
                    'role' => 'teacher',
                    'is_active' => true,
                ]
            );
        }

        // Create classes
        $classes = [
            'JSS 1A', 'JSS 1B', 'JSS 2A', 'JSS 2B', 'JSS 3A', 'JSS 3B',
            'SS 1A', 'SS 1B', 'SS 2A', 'SS 2B', 'SS 3A', 'SS 3B'
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

        // Assign form teachers to some classes
        $formTeacherAssignments = [
            ['JSS 1A', 'jsmith'],
            ['JSS 1B', 'sjohnson'],
            ['SS 1A', 'mbrown'],
        ];

        foreach ($formTeacherAssignments as $assignment) {
            $className = $assignment[0];
            $teacherUsername = $assignment[1];
            
            $class = SchoolClass::where('name', $className)->first();
            $teacher = User::where('username', $teacherUsername)->first();
            
            if ($class && $teacher) {
                $class->update(['form_teacher_id' => $teacher->id]);
                $this->command->info("Assigned {$teacher->username} as form teacher to {$className}");
            }
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

        // Assign subjects to classes
        $classSubjectAssignments = [
            // JSS 1 subjects
            ['JSS 1A', ['Mathematics', 'English Language', 'Physics', 'Chemistry', 'Biology', 'Geography', 'History']],
            ['JSS 1B', ['Mathematics', 'English Language', 'Physics', 'Chemistry', 'Biology', 'Geography', 'History']],
            // JSS 2 subjects
            ['JSS 2A', ['Mathematics', 'English Language', 'Physics', 'Chemistry', 'Biology', 'Geography', 'History']],
            ['JSS 2B', ['Mathematics', 'English Language', 'Physics', 'Chemistry', 'Biology', 'Geography', 'History']],
            // JSS 3 subjects
            ['JSS 3A', ['Mathematics', 'English Language', 'Physics', 'Chemistry', 'Biology', 'Geography', 'History']],
            ['JSS 3B', ['Mathematics', 'English Language', 'Physics', 'Chemistry', 'Biology', 'Geography', 'History']],
            // SS 1 subjects
            ['SS 1A', ['Mathematics', 'English Language', 'Physics', 'Chemistry', 'Biology', 'Economics', 'Government']],
            ['SS 1B', ['Mathematics', 'English Language', 'Physics', 'Chemistry', 'Biology', 'Economics', 'Government']],
            // SS 2 subjects
            ['SS 2A', ['Mathematics', 'English Language', 'Physics', 'Chemistry', 'Biology', 'Economics', 'Government']],
            ['SS 2B', ['Mathematics', 'English Language', 'Physics', 'Chemistry', 'Biology', 'Economics', 'Government']],
            // SS 3 subjects
            ['SS 3A', ['Mathematics', 'English Language', 'Physics', 'Chemistry', 'Biology', 'Economics', 'Government']],
            ['SS 3B', ['Mathematics', 'English Language', 'Physics', 'Chemistry', 'Biology', 'Economics', 'Government']],
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

        // Assign teachers to subjects and classes
        $teacherAssignments = [
            ['jsmith', 'Mathematics', ['JSS 1A', 'JSS 2A', 'SS 1A']],
            ['sjohnson', 'English Language', ['JSS 1B', 'JSS 2B', 'SS 1B']],
            ['mbrown', 'Physics', ['JSS 3A', 'SS 2A', 'SS 3A']],
        ];

        foreach ($teacherAssignments as $assignment) {
            $username = $assignment[0];
            $subjectName = $assignment[1];
            $classNames = $assignment[2];
            
            $teacher = User::where('username', $username)->first();
            $subject = Subject::where('name', $subjectName)->first();
            
            if ($teacher && $subject) {
                foreach ($classNames as $className) {
                    $class = SchoolClass::where('name', $className)->first();
                    
                    if ($class) {
                        TeacherSubject::firstOrCreate(
                            [
                                'teacher_id' => $teacher->id,
                                'subject_id' => $subject->id,
                                'class_id' => $class->id,
                            ],
                            [
                                'is_active' => true,
                            ]
                        );
                    }
                }
            }
        }

        // Create sample students
        $students = [
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'admission_number' => 'ADM/2024/001',
                'email' => 'john.doe@student.com',
                'class' => 'JSS 1A',
                'subjects' => ['Mathematics', 'English Language', 'Physics', 'Chemistry'],
            ],
            [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'admission_number' => 'ADM/2024/002',
                'email' => 'jane.smith@student.com',
                'class' => 'JSS 1A',
                'subjects' => ['Mathematics', 'English Language', 'Biology', 'Geography'],
            ],
            [
                'first_name' => 'Michael',
                'last_name' => 'Johnson',
                'admission_number' => 'ADM/2024/003',
                'email' => 'michael.johnson@student.com',
                'class' => 'JSS 2A',
                'subjects' => ['Mathematics', 'English Language', 'Physics', 'Computer Science'],
            ],
        ];

        foreach ($students as $studentData) {
            $className = $studentData['class'];
            $subjectNames = $studentData['subjects'];
            
            $class = SchoolClass::where('name', $className)->first();
            
            if ($class) {
                $student = Student::firstOrCreate(
                    ['admission_number' => $studentData['admission_number']],
                    [
                        'first_name' => $studentData['first_name'],
                        'last_name' => $studentData['last_name'],
                        'email' => $studentData['email'],
                        'class_id' => $class->id,
                        'password' => 'password', // Will be hashed by mutator
                        'is_active' => true,
                    ]
                );

                // Assign subjects to student
                foreach ($subjectNames as $subjectName) {
                    $subject = Subject::where('name', $subjectName)->first();
                    
                    if ($subject) {
                        StudentSubject::firstOrCreate(
                            [
                                'student_id' => $student->id,
                                'subject_id' => $subject->id,
                            ],
                            [
                                'is_active' => true,
                            ]
                        );
                    }
                }
            }
        }

        // Create sample scores
        $sampleScores = [
            [
                'student_name' => 'John Doe',
                'subject_name' => 'Mathematics',
                'term' => 'first',
                'first_ca' => 85,
                'second_ca' => 78,
                'exam_score' => 82,
            ],
            [
                'student_name' => 'John Doe',
                'subject_name' => 'English Language',
                'term' => 'first',
                'first_ca' => 72,
                'second_ca' => 80,
                'exam_score' => 75,
            ],
            [
                'student_name' => 'Jane Smith',
                'subject_name' => 'Mathematics',
                'term' => 'first',
                'first_ca' => 90,
                'second_ca' => 88,
                'exam_score' => 92,
            ],
            [
                'student_name' => 'Jane Smith',
                'subject_name' => 'Biology',
                'term' => 'first',
                'first_ca' => 85,
                'second_ca' => 82,
                'exam_score' => 88,
            ],
            [
                'student_name' => 'Michael Johnson',
                'subject_name' => 'Physics',
                'term' => 'first',
                'first_ca' => 78,
                'second_ca' => 75,
                'exam_score' => 80,
            ],
        ];

        foreach ($sampleScores as $scoreData) {
            $student = Student::where('first_name', explode(' ', $scoreData['student_name'])[0])
                             ->where('last_name', explode(' ', $scoreData['student_name'])[1])
                             ->first();
            $subject = Subject::where('name', $scoreData['subject_name'])->first();
            
            if ($student && $subject) {
                $totalScore = $scoreData['first_ca'] + $scoreData['second_ca'] + $scoreData['exam_score'];
                $grade = $totalScore >= 80 ? 'A' : ($totalScore >= 70 ? 'B' : ($totalScore >= 60 ? 'C' : ($totalScore >= 50 ? 'D' : ($totalScore >= 40 ? 'E' : 'F'))));
                
                Score::firstOrCreate(
                    [
                        'student_id' => $student->id,
                        'subject_id' => $subject->id,
                        'class_id' => $student->class_id,
                        'term' => $scoreData['term'],
                    ],
                    [
                        'teacher_id' => User::where('role', 'teacher')->first()->id,
                        'first_ca' => $scoreData['first_ca'],
                        'second_ca' => $scoreData['second_ca'],
                        'exam_score' => $scoreData['exam_score'],
                        'total_score' => $totalScore,
                        'grade' => $grade,
                        'remark' => $grade === 'A' ? 'Excellent' : ($grade === 'B' ? 'Very Good' : ($grade === 'C' ? 'Good' : ($grade === 'D' ? 'Pass' : ($grade === 'E' ? 'Pass' : 'Fail')))),
                        'is_active' => true,
                    ]
                );
            }
        }

        $this->command->info('Development data seeded successfully!');
    }
}

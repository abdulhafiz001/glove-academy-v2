<?php

namespace App\Imports;

use App\Models\Score;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SchoolClass;
use App\Models\AcademicSession;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Validator;

class ScoresImport implements ToCollection, WithHeadingRow
{
    protected $errors = [];
    protected $successCount = 0;
    protected $teacherId;
    protected $classId;
    protected $subjectId;

    public function __construct($teacherId = null, $classId = null, $subjectId = null)
    {
        $this->teacherId = $teacherId;
        $this->classId = $classId;
        $this->subjectId = $subjectId;
    }

    public function collection(Collection $rows)
    {
        // Get current academic session
        $academicSession = AcademicSession::current();
        if (!$academicSession) {
            throw new \Exception('No current academic session set. Please set an academic session in settings.');
        }

        // If class_id and subject_id are provided, use them (for teacher imports)
        $class = null;
        $subject = null;
        
        if ($this->classId && $this->subjectId) {
            $class = SchoolClass::find($this->classId);
            $subject = Subject::find($this->subjectId);
            
            if (!$class) {
                throw new \Exception('Class not found');
            }
            
            if (!$subject) {
                throw new \Exception('Subject not found');
            }
        }

        foreach ($rows as $index => $row) {
            // Skip empty rows
            if (empty($row['admission_number'])) {
                continue;
            }

            $rowNumber = $index + 2;

            // If class and subject are pre-set, only require admission_number and term
            if ($class && $subject) {
                $validator = Validator::make($row->toArray(), [
                    'admission_number' => 'required|string',
                    'term' => 'required|in:first,second,third',
                    'first_ca' => 'nullable|numeric|min:0|max:100',
                    'second_ca' => 'nullable|numeric|min:0|max:100',
                    'exam_score' => 'nullable|numeric|min:0|max:100',
                ]);
            } else {
                // Otherwise, require all fields (for admin imports)
                $validator = Validator::make($row->toArray(), [
                    'admission_number' => 'required|string',
                    'subject' => 'required|string',
                    'class' => 'required|string',
                    'term' => 'required|in:first,second,third',
                    'first_ca' => 'nullable|numeric|min:0|max:100',
                    'second_ca' => 'nullable|numeric|min:0|max:100',
                    'exam_score' => 'nullable|numeric|min:0|max:100',
                ]);
            }

            if ($validator->fails()) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'admission_number' => $row['admission_number'] ?? 'N/A',
                    'errors' => $validator->errors()->all()
                ];
                continue;
            }

            // Find student
            $student = Student::where('admission_number', $row['admission_number'])->first();
            if (!$student) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'admission_number' => $row['admission_number'],
                    'errors' => ['Student with admission number "' . $row['admission_number'] . '" not found']
                ];
                continue;
            }

            // Use pre-set subject or find from row
            if ($subject) {
                $currentSubject = $subject;
            } else {
                $currentSubject = Subject::where('name', $row['subject'])
                                ->orWhere('code', $row['subject'])
                                ->first();
                if (!$currentSubject) {
                    $this->errors[] = [
                        'row' => $rowNumber,
                        'admission_number' => $row['admission_number'],
                        'errors' => ['Subject "' . $row['subject'] . '" not found']
                    ];
                    continue;
                }
            }

            // Use pre-set class or find from row
            if ($class) {
                $currentClass = $class;
            } else {
                $currentClass = SchoolClass::where('name', $row['class'])->first();
                if (!$currentClass) {
                    $this->errors[] = [
                        'row' => $rowNumber,
                        'admission_number' => $row['admission_number'],
                        'errors' => ['Class "' . $row['class'] . '" not found']
                    ];
                    continue;
                }
            }

            // Verify student is in this class
            if ($student->class_id != $currentClass->id) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'admission_number' => $row['admission_number'],
                    'errors' => ['Student is not in class "' . $currentClass->name . '"']
                ];
                continue;
            }

            // If class and subject are pre-set, verify student is offering this subject
            if ($class && $subject) {
                $isOfferingSubject = $student->studentSubjects()
                    ->where('subject_id', $subject->id)
                    ->where('is_active', true)
                    ->exists();
                
                if (!$isOfferingSubject) {
                    $this->errors[] = [
                        'row' => $rowNumber,
                        'admission_number' => $row['admission_number'],
                        'errors' => ['Student is not offering subject "' . $subject->name . '"']
                    ];
                    continue;
                }
            }

            // Check if score already exists
            $existingScore = Score::where([
                'student_id' => $student->id,
                'subject_id' => $currentSubject->id,
                'class_id' => $currentClass->id,
                'term' => $row['term'],
                'academic_session_id' => $academicSession->id,
            ])->first();

            try {
                if ($existingScore) {
                    // Update existing score
                    $existingScore->update([
                        'first_ca' => $row['first_ca'] ?? null,
                        'second_ca' => $row['second_ca'] ?? null,
                        'exam_score' => $row['exam_score'] ?? null,
                        'remark' => $row['remark'] ?? null,
                    ]);
                } else {
                    // Create new score
                    Score::create([
                        'student_id' => $student->id,
                        'subject_id' => $currentSubject->id,
                        'class_id' => $currentClass->id,
                        'teacher_id' => $this->teacherId ?? 1, // Default to admin user if not provided
                        'academic_session_id' => $academicSession->id,
                        'term' => $row['term'],
                        'first_ca' => $row['first_ca'] ?? null,
                        'second_ca' => $row['second_ca'] ?? null,
                        'exam_score' => $row['exam_score'] ?? null,
                        'remark' => $row['remark'] ?? null,
                        'is_active' => true,
                    ]);
                }

                $this->successCount++;
            } catch (\Exception $e) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'admission_number' => $row['admission_number'],
                    'errors' => [$e->getMessage()]
                ];
            }
        }
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getSuccessCount()
    {
        return $this->successCount;
    }
}


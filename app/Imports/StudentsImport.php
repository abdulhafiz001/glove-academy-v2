<?php

namespace App\Imports;

use App\Models\Student;
use App\Models\SchoolClass;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class StudentsImport implements ToCollection, WithHeadingRow
{
    protected $errors = [];
    protected $successCount = 0;
    protected $classId = null;

    public function __construct($classId = null)
    {
        $this->classId = $classId;
    }

    public function collection(Collection $rows)
    {
        // If class_id is provided, validate it exists
        if ($this->classId) {
            $class = SchoolClass::find($this->classId);
            if (!$class) {
                $this->errors[] = [
                    'row' => 0,
                    'admission_number' => 'N/A',
                    'errors' => ['Invalid class selected']
                ];
                return;
            }
        }

        foreach ($rows as $index => $row) {
            // Convert row to array for easier manipulation
            $rowData = $row->toArray();
            
            // Skip empty rows
            if (empty($rowData['admission_number']) && empty($rowData['first_name'])) {
                continue;
            }

            $rowNumber = $index + 2; // +2 because Excel starts at 1 and we have header

            // Normalize data: Convert numeric values to strings for admission_number, phone, and parent_phone
            // Excel may read these as numbers, but validation expects strings
            if (isset($rowData['admission_number'])) {
                $rowData['admission_number'] = (string) $rowData['admission_number'];
            }
            if (isset($rowData['phone']) && !empty($rowData['phone'])) {
                $rowData['phone'] = (string) $rowData['phone'];
            }
            if (isset($rowData['parent_phone']) && !empty($rowData['parent_phone'])) {
                $rowData['parent_phone'] = (string) $rowData['parent_phone'];
            }
            
            // Normalize gender: convert to lowercase for case-insensitive matching
            if (isset($rowData['gender']) && !empty($rowData['gender'])) {
                $genderValue = strtolower(trim($rowData['gender']));
                // Map common variations
                $genderMap = [
                    'm' => 'male',
                    'f' => 'female',
                    'male' => 'male',
                    'female' => 'female',
                ];
                $rowData['gender'] = $genderMap[$genderValue] ?? $genderValue;
            }

            // Parse date_of_birth if provided - handle multiple formats including Excel dates
            if (isset($rowData['date_of_birth']) && !empty($rowData['date_of_birth'])) {
                $dateValue = $rowData['date_of_birth'];
                $parsedDate = null;
                
                // Handle DateTime objects from PhpSpreadsheet
                if ($dateValue instanceof \DateTime) {
                    $parsedDate = $dateValue->format('Y-m-d');
                }
                // Handle Excel serial numbers (numeric values)
                elseif (is_numeric($dateValue)) {
                    // Excel date serial number (days since Jan 1, 1900)
                    // Excel epoch starts on 1900-01-01, but Excel incorrectly treats 1900 as a leap year
                    // So we need to subtract 2 days for dates after 1900-02-28
                    $excelEpoch = mktime(0, 0, 0, 1, 1, 1900);
                    $timestamp = $excelEpoch + (($dateValue - 2) * 86400); // -2 because Excel's 1900 leap year bug
                    $parsedDate = date('Y-m-d', $timestamp);
                }
                // Handle string dates
                elseif (is_string($dateValue)) {
                    // Trim whitespace
                    $dateValue = trim($dateValue);
                    
                    // Try different date formats
                    $formats = [
                        'Y-m-d',      // 2010-11-03
                        'Y/m/d',      // 2010/11/03
                        'm/d/Y',      // 11/3/2010 or 11/03/2010
                        'd/m/Y',      // 3/11/2010 or 03/11/2010
                        'm-d-Y',      // 11-3-2010
                        'd-m-Y',      // 3-11-2010
                        'Y-m-d H:i:s', // With time
                        'Y/m/d H:i:s',
                    ];
                    
                    // First try strtotime which handles many formats
                    $timestamp = strtotime($dateValue);
                    if ($timestamp !== false && $timestamp > 0) {
                        $parsedDate = date('Y-m-d', $timestamp);
                    } else {
                        // If strtotime fails, try specific formats
                        foreach ($formats as $format) {
                            $date = \DateTime::createFromFormat($format, $dateValue);
                            if ($date !== false) {
                                $parsedDate = $date->format('Y-m-d');
                                break;
                            }
                        }
                    }
                }
                
                // Update the row data with parsed date for validation
                if ($parsedDate) {
                    $rowData['date_of_birth'] = $parsedDate;
                } else {
                    // If date couldn't be parsed, skip date_of_birth (make it optional)
                    // Don't fail the entire row, just set it to null
                    $rowData['date_of_birth'] = null;
                }
            }

            // Validate row data - class is optional if class_id is provided
            $validationRules = [
                'admission_number' => 'required|string|unique:students,admission_number',
                'first_name' => 'required|string',
                'last_name' => 'required|string',
                'email' => 'nullable|email|unique:students,email',
                'phone' => 'nullable|string',
                'date_of_birth' => 'nullable|date',
                'gender' => 'nullable|in:male,female',
                'parent_name' => 'nullable|string',
                'parent_phone' => 'nullable|string',
                'parent_email' => 'nullable|email',
                'password' => 'nullable|string|min:6',
            ];

            // If class_id is provided, class column is optional
            if (!$this->classId) {
                $validationRules['class'] = 'required|string'; // Class name required if no class_id
            }

            $validator = Validator::make($rowData, $validationRules);

            if ($validator->fails()) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'admission_number' => $rowData['admission_number'] ?? 'N/A',
                    'errors' => $validator->errors()->all()
                ];
                continue;
            }

            // Determine class: use provided class_id or find by name
            if ($this->classId) {
                $class = SchoolClass::find($this->classId);
            } else {
                $class = SchoolClass::where('name', $rowData['class'] ?? '')->first();
            }

            if (!$class) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'admission_number' => $rowData['admission_number'] ?? 'N/A',
                    'errors' => [$this->classId ? 'Class not found' : 'Class "' . ($rowData['class'] ?? 'N/A') . '" not found']
                ];
                continue;
            }

            // Get current academic session and term
            $currentSession = \App\Models\AcademicSession::current();
            $currentTerm = \App\Models\Term::current();

            // Create student
            try {
                $student = Student::create([
                    'admission_number' => $rowData['admission_number'],
                    'first_name' => $rowData['first_name'],
                    'last_name' => $rowData['last_name'],
                    'middle_name' => $rowData['middle_name'] ?? null,
                    'email' => $rowData['email'] ?? null,
                    'phone' => $rowData['phone'] ?? null,
                    'date_of_birth' => isset($rowData['date_of_birth']) && !empty($rowData['date_of_birth']) ? $rowData['date_of_birth'] : null,
                    'gender' => $rowData['gender'] ?? null,
                    'parent_name' => $rowData['parent_name'] ?? null,
                    'parent_phone' => $rowData['parent_phone'] ?? null,
                    'parent_email' => $rowData['parent_email'] ?? null,
                    'class_id' => $class->id,
                    'password' => isset($rowData['password']) ? $rowData['password'] : 'password', // Will be hashed by mutator
                    'is_active' => true,
                    'admission_academic_session_id' => $currentSession?->id,
                    'admission_term' => $currentTerm?->name,
                    'status' => 'active',
                ]);

                // Record class history for the current academic session
                if ($currentSession && $student) {
                    \App\Models\StudentClassHistory::updateHistory(
                        $student->id,
                        $currentSession->id,
                        $student->class_id
                    );
                }

                $this->successCount++;
            } catch (\Exception $e) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'admission_number' => $rowData['admission_number'] ?? 'N/A',
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


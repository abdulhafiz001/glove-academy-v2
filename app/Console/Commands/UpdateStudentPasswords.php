<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Student;
use Illuminate\Support\Facades\Hash;

class UpdateStudentPasswords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'students:update-passwords';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update existing students with properly hashed default password "password"';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating student passwords with proper bcrypt hashing...');
        
        $students = Student::all();
        
        if ($students->isEmpty()) {
            $this->info('No students found in the database.');
            return 0;
        }
        
        $updatedCount = 0;
        $skippedCount = 0;
        
        foreach ($students as $student) {
            // Check if password is already properly hashed
            if ($this->isBcryptHash($student->password)) {
                $skippedCount++;
                $this->line("Skipped (already hashed): {$student->first_name} {$student->last_name} ({$student->admission_number})");
                continue;
            }
            
            // Update password - directly set hashed password to bypass mutator
            $student->getConnection()->table('students')
                ->where('id', $student->id)
                ->update(['password' => Hash::make('password')]);
            
            // Refresh the model to get the updated password
            $student->refresh();
            $updatedCount++;
            $this->info("Updated password for student: {$student->first_name} {$student->last_name} ({$student->admission_number})");
        }
        
        $this->info("Updated {$updatedCount} students with properly hashed default password.");
        if ($skippedCount > 0) {
            $this->info("Skipped {$skippedCount} students (already hashed).");
        }
        $this->info('Default password is: password');
        $this->info('All passwords are now properly hashed with bcrypt!');
        
        return 0;
    }
    
    /**
     * Check if a string is a valid bcrypt hash
     */
    private function isBcryptHash($password)
    {
        // Bcrypt hashes start with $2y$, $2a$, or $2b$ and are 60 characters long
        if (!is_string($password) || strlen($password) !== 60) {
            return false;
        }
        
        return strpos($password, '$2y$') === 0 || 
               strpos($password, '$2a$') === 0 || 
               strpos($password, '$2b$') === 0;
    }
}

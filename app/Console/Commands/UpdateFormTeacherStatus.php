<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\SchoolClass;

class UpdateFormTeacherStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:update-form-teacher-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the is_form_teacher field for all users based on their actual class assignments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating form teacher status for all users...');

        $teachers = User::where('role', 'teacher')->get();
        $updatedCount = 0;

        foreach ($teachers as $teacher) {
            $isFormTeacher = SchoolClass::where('form_teacher_id', $teacher->id)
                                       ->where('is_active', true)
                                       ->exists();

            if ($teacher->is_form_teacher != $isFormTeacher) {
                $teacher->update(['is_form_teacher' => $isFormTeacher]);
                $updatedCount++;
                
                $this->info("Updated {$teacher->name}: is_form_teacher = " . ($isFormTeacher ? 'true' : 'false'));
            }
        }

        $this->info("Updated {$updatedCount} teachers.");
        $this->info('Form teacher status update completed!');

        return 0;
    }
}

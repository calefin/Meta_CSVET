<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;

/**
 * Single place that auto-creates a free CourseEnrollment when access didn't
 * come from a purchase or a manual admin grant (open/free courses, or a
 * course unlocked by an initiation characteristic). Payment-based access is
 * still created by PaymentFulfillment, and manual grants by CourseBuilder —
 * this only covers the two "free" paths so the reason is recorded once.
 */
class CourseAccessResolver
{
    public static function grantIfEligible(Course $course, User $user): ?CourseEnrollment
    {
        $existing = $course->enrollments()->where('user_id', $user->id)->first();

        if ($existing) {
            return $existing;
        }

        if ($user->isAdmin() || $user->isConsulente() || ! $course->userCanAccess($user)) {
            return null;
        }

        $source = $course->userMeetsInitiationCriteria($user) ? 'initiation_criteria' : 'open_free';

        return $course->enrollments()->create([
            'user_id' => $user->id,
            'source' => $source,
            'enrolled_at' => now(),
        ]);
    }
}

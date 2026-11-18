<?php

namespace App\Policies;

use App\Models\ConsultationRequest;
use App\Models\User;

class ConsultationPolicy
{
    public function view(User $user, ConsultationRequest $consultationRequest): bool
    {
        $consultation = $consultationRequest->consultation;

        return ! $consultation || $consultation->admin_id === $user->id;
    }
}

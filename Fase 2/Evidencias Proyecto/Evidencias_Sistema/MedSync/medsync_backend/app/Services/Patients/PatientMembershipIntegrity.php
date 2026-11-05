<?php

namespace App\Services\Patients;

use App\Models\Center\Patient;
use App\Models\Core\CenterUser;
use App\Models\User;

class PatientMembershipIntegrity
{
    public function valid(CenterUser $membership, User $user): bool
    {
        if ($membership->role !== 'PACIENTE') {
            return true;
        }

        if (! $membership->patient_id) {
            return false;
        }

        $patient = Patient::query()->find($membership->patient_id);

        return $patient !== null
            && $patient->is_active
            && (int) $patient->user_id === (int) $user->id
            && strtolower(trim((string) $patient->email)) === strtolower(trim((string) $user->email));
    }
}

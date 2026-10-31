<?php

namespace App\Services\Authorization;

use App\Models\Core\CenterUser;
use App\Models\Core\MedicalCenter;
use App\Models\User;
use App\Notifications\CenterAccessInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CenterAccessService
{
    public function list(MedicalCenter $center, string $role): array
    {
        return CenterUser::query()->with('user')->where('medical_center_id', $center->id)
            ->where('role', $role)->orderBy('id')->get()
            ->map(fn (CenterUser $membership) => $this->present($membership))->all();
    }

    public function staff(MedicalCenter $center): array
    {
        return CenterUser::query()->with('user')->where('medical_center_id', $center->id)
            ->whereIn('role', ['ADMIN', 'RECEPCIONISTA'])->orderBy('id')->get()
            ->map(fn (CenterUser $membership) => $this->present($membership))->all();
    }

    public function changeStaffRole(MedicalCenter $center, CenterUser $membership, string $role): array
    {
        abort_unless($membership->medical_center_id === $center->id &&
            in_array($membership->role, ['ADMIN', 'RECEPCIONISTA'], true), 404);
        if ($center->provisioning_status !== 'READY') {
            throw ValidationException::withMessages(['center' => ['El centro aún no está preparado.']]);
        }
        $membership->update(['role' => $role]);
        return $this->present($membership->fresh('user'));
    }

    public function assign(MedicalCenter $center, string $role, string $email, string $name): array
    {
        if ($center->provisioning_status !== 'READY') {
            throw ValidationException::withMessages(['center' => ['El centro aún no está preparado.']]);
        }
        $email = strtolower(trim($email));
        $created = false;
        $needsInvitation = false;
        $membership = DB::connection('core')->transaction(function () use ($center, $role, $email, $name, &$created, &$needsInvitation) {
            $user = User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$email])->first();
            if ($user && (! $user->is_active || $user->isSuperAdmin())) {
                throw ValidationException::withMessages(['email' => ['Esta cuenta no puede recibir acceso a un centro.']]);
            }
            if (! $user) {
                $user = User::create(['name' => trim($name), 'email' => $email, 'password' => Str::random(64), 'is_active' => true]);
                $created = true;
            }
            $needsInvitation = $created || CenterUser::query()->where('user_id', $user->id)
                ->where('invitation_pending', true)->exists();
            $membership = CenterUser::query()->where('medical_center_id', $center->id)->where('user_id', $user->id)->first();
            if ($membership && $membership->role !== $role) {
                throw ValidationException::withMessages(['email' => ['La cuenta ya tiene otro rol en este centro.']]);
            }
            if (! $membership) {
                $membership = CenterUser::create([
                    'medical_center_id' => $center->id, 'user_id' => $user->id, 'role' => $role,
                    'is_active' => true, 'invitation_pending' => $needsInvitation,
                ]);
            } elseif (! $membership->is_active) {
                $membership->update(['is_active' => true]);
            }
            return $membership->load('user');
        });
        $sent = $needsInvitation && $this->sendInvitation($membership);
        return [
            'data' => $this->present($membership->fresh('user')),
            'account_created' => $created,
            'invitation_sent' => $sent,
            'message' => $needsInvitation
                ? ($sent ? 'Acceso guardado e invitación enviada.' : 'Acceso guardado; la invitación no pudo enviarse. Puedes reenviarla.')
                : 'Acceso del centro guardado sin modificar la contraseña existente.',
        ];
    }

    public function setActive(MedicalCenter $center, CenterUser $membership, string $role, bool $active): array
    {
        $this->assertMembership($center, $membership, $role);
        if ($active && ($center->provisioning_status !== 'READY' || ! $membership->user->is_active)) {
            throw ValidationException::withMessages(['is_active' => ['No es posible reactivar este acceso.']]);
        }
        $membership->update(['is_active' => $active]);
        return $this->present($membership->fresh('user'));
    }

    public function resend(MedicalCenter $center, CenterUser $membership, string $role): array
    {
        $this->assertMembership($center, $membership, $role);
        if (! $membership->is_active || ! $membership->invitation_pending) {
            throw ValidationException::withMessages(['email' => ['Esta membresía no tiene una invitación pendiente.']]);
        }
        $sent = $this->sendInvitation($membership);
        return [
            'message' => $sent ? 'Invitación reenviada.' : 'No fue posible enviar la invitación. Puedes reintentar.',
            'invitation_sent' => $sent,
            'data' => $this->present($membership->fresh('user')),
        ];
    }

    private function sendInvitation(CenterUser $membership): bool
    {
        try {
            $token = Password::broker()->createToken($membership->user);
            $membership->user->notify(new CenterAccessInvitationNotification(
                $token, $membership->medicalCenter?->name ?? 'El centro médico',
                $membership->role === 'ADMIN' ? 'administrador del centro' : 'recepción',
            ));
            $membership->update(['invitation_sent_at' => now()]);
            return true;
        } catch (Throwable $exception) {
            report($exception);
            return false;
        }
    }

    private function assertMembership(MedicalCenter $center, CenterUser $membership, string $role): void
    {
        abort_unless($membership->medical_center_id === $center->id && $membership->role === $role, 404);
    }

    private function present(CenterUser $membership): array
    {
        return [
            'id' => $membership->id, 'user_id' => $membership->user_id,
            'name' => $membership->user?->name, 'email' => $membership->user?->email,
            'role' => $membership->role, 'is_active' => $membership->is_active,
            'invitation_pending' => $membership->invitation_pending,
            'invitation_sent_at' => $membership->invitation_sent_at,
        ];
    }
}

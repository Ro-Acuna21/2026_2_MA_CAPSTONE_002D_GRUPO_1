<?php

namespace App\Services\Professionals;

use App\Models\Center\Professional;
use App\Models\Core\CenterUser;
use App\Models\User;
use App\Notifications\ProfessionalInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProfessionalAccessService
{
    /**
     * Habilita el acceso de un profesional a MedSync.
     *
     * La identidad global se almacena en la conexión core,
     * mientras que la ficha profesional pertenece a la conexión center.
     */
    public function enableAccess(
        Professional $professional,
        CenterUser $adminCenterUser
    ): array {
        if (! $professional->is_active) {
            throw ValidationException::withMessages([
                'professional' => [
                    'No se puede habilitar acceso a un profesional inactivo.',
                ],
            ]);
        }

        if (empty($professional->email)) {
            throw ValidationException::withMessages([
                'email' => [
                    'El profesional debe tener un correo electrónico para habilitar su acceso.',
                ],
            ]);
        }

        if ($professional->user_id !== null) {
            throw ValidationException::withMessages([
                'professional' => [
                    'Este profesional ya tiene una cuenta vinculada.',
                ],
            ]);
        }

        $email = strtolower(trim($professional->email));

        $user = User::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->first();

        if ($user && ! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => [
                    'Existe una cuenta con este correo, pero se encuentra inactiva.',
                ],
            ]);
        }

        /*
         * Si la cuenta global ya existe, comprobamos que no tenga
         * otra membresía dentro del mismo centro.
         */
        if ($user) {
            $existingCenterUser = CenterUser::query()
                ->where('medical_center_id', $adminCenterUser->medical_center_id)
                ->where('user_id', $user->id)
                ->first();

            if ($existingCenterUser) {
                throw ValidationException::withMessages([
                    'email' => [
                        'La cuenta asociada a este correo ya pertenece a este centro médico.',
                    ],
                ]);
            }
        }

        $userWasCreated = false;
        $centerUser = null;

        try {
            /*
             * La cuenta global y su membresía pertenecen a medsync_core,
             * por lo que ambas operaciones pueden ejecutarse dentro de
             * la misma transacción.
             */
            DB::connection('core')->transaction(
                function () use (
                    &$user,
                    &$userWasCreated,
                    &$centerUser,
                    $professional,
                    $adminCenterUser,
                    $email
                ) {
                    if (! $user) {
                        $user = User::create([
                            'name' => trim(
                                $professional->first_name
                                .' '
                                .$professional->last_name
                            ),
                            'email' => $email,

                            /*
                             * La contraseña temporal no se entrega al
                             * profesional. Será reemplazada mediante
                             * el enlace de activación enviado por correo.
                             *
                             * User utiliza el cast "hashed", por lo que
                             * Laravel almacena el valor de forma segura.
                             */
                            'password' => Str::random(64),
                            'is_active' => true,
                        ]);

                        $userWasCreated = true;
                    }

                    $centerUser = CenterUser::create([
                        'medical_center_id' => $adminCenterUser->medical_center_id,
                        'user_id' => $user->id,
                        'role' => 'PROFESIONAL',
                        'patient_id' => null,
                        'professional_id' => $professional->id,
                        'is_active' => true,
                    ]);
                }
            );

            /*
             * La ficha Professional se encuentra en otra base de datos,
             * por lo que se actualiza después de confirmar core.
             */
            $professional->update([
                'user_id' => $user->id,
            ]);
        } catch (Throwable $exception) {
            /*
             * No existe una transacción distribuida entre las bases
             * core y center. Si falla la actualización del profesional,
             * compensamos los registros creados en core.
             */
            if ($centerUser) {
                $centerUser->delete();
            }

            if ($userWasCreated && $user) {
                $user->forceDelete();
            }

            throw $exception;
        }

        /*
         * Si la cuenta global ya existía, no modificamos su contraseña.
         * Esa persona puede ingresar con sus credenciales actuales.
         */
        if (! $userWasCreated) {
            return [
                'user' => $user,
                'center_user' => $centerUser,
                'professional' => $professional->fresh(),
                'account_created' => false,
                'invitation_sent' => false,
                'message' => 'La cuenta existente fue vinculada al profesional correctamente.',
            ];
        }

        /*
         * Para una cuenta nueva generamos un token temporal para que
         * el profesional establezca su contraseña.
         */
        $token = Password::broker()->createToken($user);

        $invitationSent = true;

        try {
            $user->notify(
                new ProfessionalInvitationNotification(
                    $token,
                    $adminCenterUser->medicalCenter?->name
                )
            );
        } catch (Throwable $exception) {
            /*
             * Un fallo de correo no elimina la cuenta ya creada.
             * El administrador podrá reenviar la invitación.
             */
            report($exception);

            $invitationSent = false;
        }

        return [
            'user' => $user,
            'center_user' => $centerUser,
            'professional' => $professional->fresh(),
            'account_created' => true,
            'invitation_sent' => $invitationSent,
            'message' => $invitationSent
                ? 'La cuenta fue creada y la invitación fue enviada.'
                : 'La cuenta fue creada, pero no fue posible enviar la invitación.',
        ];
    }

    /**
     * Genera nuevamente una invitación para un profesional
     * que ya posee una cuenta vinculada.
     */
    public function resendInvitation(
        Professional $professional,
        CenterUser $adminCenterUser
    ): array {
        if ($professional->user_id === null) {
            throw ValidationException::withMessages([
                'professional' => [
                    'El profesional todavía no tiene una cuenta vinculada.',
                ],
            ]);
        }

        $user = User::find($professional->user_id);

        if (! $user) {
            throw ValidationException::withMessages([
                'professional' => [
                    'No se encontró la cuenta vinculada al profesional.',
                ],
            ]);
        }
        if ($user->email_verified_at !== null) {
    throw ValidationException::withMessages([
        'professional' => [
            'La cuenta del profesional ya fue activada.',
        ],
    ]);
}

        $centerUser = CenterUser::query()
            ->where('medical_center_id', $adminCenterUser->medical_center_id)
            ->where('user_id', $user->id)
            ->where('professional_id', $professional->id)
            ->where('role', 'PROFESIONAL')
            ->where('is_active', true)
            ->first();

        if (! $centerUser) {
            throw ValidationException::withMessages([
                'professional' => [
                    'El profesional no posee un acceso activo en este centro.',
                ],
            ]);
        }

        $token = Password::broker()->createToken($user);

        $user->notify(
            new ProfessionalInvitationNotification(
                $token,
                $adminCenterUser->medicalCenter?->name
            )
        );

        return [
            'message' => 'La invitación fue reenviada correctamente.',
        ];
    }
}

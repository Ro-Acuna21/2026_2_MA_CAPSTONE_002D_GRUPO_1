<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivateAccountRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AccountActivationController extends Controller
{
    /**
     * Establece la contraseña de una cuenta creada mediante
     * invitación y deja la cuenta preparada para iniciar sesión.
     */
    public function store(ActivateAccountRequest $request)
    {
        $status = Password::broker()->reset(
            $request->only([
                'email',
                'password',
                'password_confirmation',
                'token',
            ]),
            function (User $user, string $password) {
                /*
                 * User utiliza el cast "hashed", por lo que Laravel
                 * almacena la contraseña de forma segura.
                 */
                $user->password = $password;

                /*
                 * La persona demostró acceso al correo mediante
                 * el enlace de invitación recibido.
                 */
                if ($user->email_verified_at === null) {
                    $user->email_verified_at = now();
                }

                /*
                 * Invalida sesiones persistentes antiguas si existieran.
                 */

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [
                    match ($status) {
                        Password::INVALID_TOKEN =>
                            'El enlace de activación no es válido o ya expiró.',

                        Password::INVALID_USER =>
                            'No existe una cuenta asociada a este correo.',

                        default =>
                            'No fue posible activar la cuenta.',
                    },
                ],
            ]);
        }

        return response()->json([
            'message' => 'Contraseña creada correctamente. Ya puedes iniciar sesión.',
        ]);
    }
}
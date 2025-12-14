<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * @param array<string, string> $input
     */
    public function update(User $user, array $input): void
    {
        Validator::make(
            $input,
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => [
                    'required',
                    'string',
                    'email',
                    'max:255',
                    Rule::unique('users')->ignore($user->id),
                ],
            ]
        )->validateWithBag('updateProfileInformation');

        $shouldReVerifyEmail = $user instanceof MustVerifyEmail
            && $input['email'] !== $user->email;

        if ($shouldReVerifyEmail) {
            $this->updateVerifiedUser($user, $input);
            return;
        }

        $user->forceFill([
            'name'  => $input['name'],
            'email' => $input['email'],
        ])->save();
    }

    /**
     * @param array<string, string> $input
     */
    protected function updateVerifiedUser(User $user, array $input): void
    {
        $user->forceFill([
            'name'              => $input['name'],
            'email'             => $input['email'],
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();
    }
}

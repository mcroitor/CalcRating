<?php

namespace App\Console\Commands;

use App\Actions\BootstrapAdminAction;
use App\Exceptions\DomainException;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class BootstrapAdminCommand extends Command
{
    protected $signature = 'admin:bootstrap
        {email : Administrator email address}
        {--name=Admin : Administrator first name}
        {--surname=Administrator : Administrator surname}
        {--send-reset : Generate a random password and email a password reset link}
        {--promote : Promote an existing account to administrator}';

    protected $description = 'Create the initial administrator account or promote an existing user without hardcoded credentials';

    public function handle(BootstrapAdminAction $action): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $name = trim((string) $this->option('name'));
        $surname = trim((string) $this->option('surname'));
        $sendReset = (bool) $this->option('send-reset');
        $promote = (bool) $this->option('promote');

        $validator = Validator::make(
            [
                'email' => $email,
                'name' => $name,
                'surname' => $surname,
            ],
            [
                'email' => ['required', 'string', 'email', 'max:255'],
                'name' => ['required', 'string', 'max:255'],
                'surname' => ['required', 'string', 'max:255'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $existingUser = User::query()->where('email', $email)->first();

        if ($existingUser !== null && ! $promote) {
            $this->error('A user with this email already exists. Pass --promote to grant the admin role.');

            return self::FAILURE;
        }

        $plainPassword = null;

        if (! $sendReset && $existingUser === null) {
            $plainPassword = (string) $this->secret('Password');
            $confirmation = (string) $this->secret('Confirm password');

            $passwordValidator = Validator::make(
                [
                    'password' => $plainPassword,
                    'password_confirmation' => $confirmation,
                ],
                [
                    'password' => ['required', 'confirmed', Password::defaults()],
                ]
            );

            if ($passwordValidator->fails()) {
                foreach ($passwordValidator->errors()->all() as $error) {
                    $this->error($error);
                }

                return self::FAILURE;
            }
        }

        try {
            $result = $action->execute(
                email: $email,
                name: $name,
                surname: $surname,
                plainPassword: $plainPassword,
                sendReset: $sendReset,
                promote: $promote,
            );
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $verb = $result['mode'] === 'promoted' ? 'promoted to' : 'created as';
        $this->info("User {$result['user']->email} {$verb} administrator.");

        if ($sendReset) {
            if ($result['reset_status'] === PasswordBroker::RESET_LINK_SENT) {
                $this->info('Password reset link sent.');
            } else {
                $this->warn('Administrator saved, but the password reset link could not be sent.');
            }
        }

        return self::SUCCESS;
    }
}

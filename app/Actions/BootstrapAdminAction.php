<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Exceptions\DomainException;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class BootstrapAdminAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Create the initial administrator or promote an existing account.
     *
     * @return array{user: User, mode: 'created'|'promoted', reset_status: string|null}
     */
    public function execute(
        string $email,
        string $name,
        string $surname,
        ?string $plainPassword = null,
        bool $sendReset = false,
        bool $promote = false,
    ): array {
        $normalizedEmail = Str::lower(trim($email));

        /** @var array{user: User, mode: 'created'|'promoted'} $persisted */
        $persisted = DB::transaction(function () use ($normalizedEmail, $name, $surname, $plainPassword, $promote): array {
            $existing = User::query()->where('email', $normalizedEmail)->first();

            if ($existing !== null) {
                if (! $promote) {
                    throw new DomainException('A user with this email already exists. Pass --promote to grant the admin role.');
                }

                $attributes = [
                    'role' => UserRole::Admin,
                    'is_blocked' => false,
                    'blocked_at' => null,
                    'blocked_reason' => null,
                ];

                if ($plainPassword !== null && $plainPassword !== '') {
                    $attributes['password'] = Hash::make($plainPassword);
                }

                if ($existing->email_verified_at === null) {
                    $attributes['email_verified_at'] = now();
                }

                $existing->updateWithVersion($attributes, $existing->version);

                return [
                    'user' => $existing->fresh(),
                    'mode' => 'promoted',
                ];
            }

            $passwordToHash = ($plainPassword !== null && $plainPassword !== '')
                ? $plainPassword
                : Str::password(32);

            $created = User::create([
                'name' => trim($name),
                'surname' => trim($surname),
                'email' => $normalizedEmail,
                'password' => Hash::make($passwordToHash),
                'role' => UserRole::Admin,
                'version' => 1,
                'is_blocked' => false,
            ]);

            $created->forceFill(['email_verified_at' => now()])->save();

            return [
                'user' => $created,
                'mode' => 'created',
            ];
        });

        $user = $persisted['user'];
        $mode = $persisted['mode'];

        $this->audit->log('admin.bootstrapped', null, 'user', $user->id, [
            'email' => $user->email,
            'mode' => $mode,
            'send_reset' => $sendReset,
        ]);

        $resetStatus = null;
        if ($sendReset) {
            $resetStatus = Password::sendResetLink(['email' => $user->email]);
        }

        return [
            'user' => $user,
            'mode' => $mode,
            'reset_status' => $resetStatus,
        ];
    }
}

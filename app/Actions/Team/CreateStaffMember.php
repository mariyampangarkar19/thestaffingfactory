<?php

namespace App\Actions\Team;

use App\Audit\AuditLogger;
use App\Enums\Role;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Agency employees are invited by email and choose their own password.
 */
final class CreateStaffMember
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(string $name, string $email, Role $role, ?string $phone = null): User
    {
        if (! $role->isStaff()) {
            throw new DomainRuleViolation(__('Freelancer accounts are created by approving a freelancer.'));
        }

        $email = Str::lower($email);

        if (User::query()->where('email', $email)->exists()) {
            throw new DomainRuleViolation(__('An account with this email already exists.'));
        }

        $user = DB::transaction(function () use ($name, $email, $role, $phone): User {
            $user = new User(['name' => $name, 'email' => $email, 'phone' => $phone]);
            $user->forceFill(['password' => Str::password(40)])->save();
            $user->assignRole($role->value);
            $this->audit->log('team.member_added', $user, ['role' => $role->value]);

            return $user;
        });

        Password::broker()->sendResetLink(['email' => $email]);

        return $user;
    }

    public function changeRole(User $user, Role $role, User $actor): User
    {
        if (! $role->isStaff() || $user->hasRole(Role::Freelancer->value)) {
            throw new DomainRuleViolation(__('Freelancer accounts are created by approving a freelancer.'));
        }

        if ($user->is($actor)) {
            throw new DomainRuleViolation(__('You cannot change your own role.'));
        }

        $this->guardLastOwner($user, $role);

        $user->syncRoles([$role->value]);
        $this->audit->log('team.role_changed', $user, ['role' => $role->value]);

        return $user;
    }

    private function guardLastOwner(User $user, Role $newRole): void
    {
        if ($newRole === Role::Owner || ! $user->hasRole(Role::Owner->value)) {
            return;
        }

        if (User::role(Role::Owner->value)->count() <= 1) {
            throw new DomainRuleViolation(__('The agency must keep at least one owner.'));
        }
    }
}

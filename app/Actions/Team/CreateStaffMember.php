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

        $existing = User::query()->where('email', $email)->first();

        // Someone whose access was removed earlier (no role left) can be invited again; anyone else is a duplicate.
        if ($existing !== null && $existing->roles()->exists()) {
            throw new DomainRuleViolation(__('An account with this email already exists.'));
        }

        $user = DB::transaction(function () use ($existing, $name, $email, $role, $phone): User {
            $user = $existing ?? new User(['email' => $email]);
            $user->fill(['name' => $name, 'phone' => $phone]);
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

    /**
     * Ends a staff member's access at once. The account row stays so that notes, approvals and the audit log keep their author.
     */
    public function removeAccess(User $user, User $actor): User
    {
        if ($user->is($actor)) {
            throw new DomainRuleViolation(__('You cannot remove your own access.'));
        }

        if ($user->hasRole(Role::Freelancer->value)) {
            throw new DomainRuleViolation(__('Freelancer accounts are managed from the freelancer record.'));
        }

        $this->guardLastOwner($user, Role::Viewer);

        DB::transaction(function () use ($user): void {
            $user->syncRoles([]);
            $user->forceFill([
                'password' => Str::password(40),
                'remember_token' => null,
                'app_authentication_secret' => null,
                'app_authentication_recovery_codes' => null,
            ])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete(); // signed out everywhere, immediately
            $this->audit->log('team.access_removed', $user);
        });

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

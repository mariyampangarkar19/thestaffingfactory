<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;

/**
 * The steps of the first-visit tour for each side of the platform. Text only; the view draws it.
 * To add a step, add an entry here (and its French in lang/fr.json).
 */
final class WelcomeTour
{
    /** @return list<array{icon: string, title: string, body: string}> */
    public static function steps(string $panel, User $user, string $agency): array
    {
        return $panel === 'portal' ? self::portal($user, $agency) : self::crm($user, $agency);
    }

    public static function shouldOpen(?User $user, bool $asked): bool
    {
        return $user !== null && ($asked || $user->tour_completed_at === null);
    }

    /** @return list<array{icon: string, title: string, body: string}> */
    private static function crm(User $user, string $agency): array
    {
        $role = collect(Role::cases())->first(fn (Role $r) => $user->hasRole($r->value));

        return [
            ['icon' => 'heroicon-o-sparkles', 'title' => __('Welcome to :agency', ['agency' => $agency]),
                'body' => __('This is where the team runs every job, from the customer’s request to the freelancer’s payment. You are signed in as :role.', ['role' => $role?->label() ?? __('staff')])],
            ['icon' => 'heroicon-o-inbox-arrow-down', 'title' => __('1. A customer asks for staff'),
                'body' => __('Requests arrive from the website or are entered after a call or a WhatsApp message. Each one lists the positions, dates and place.')],
            ['icon' => 'heroicon-o-user-group', 'title' => __('2. Fill the shifts'),
                'body' => __('Assign approved freelancers to each shift. The system stops double bookings and warns about days off.')],
            ['icon' => 'heroicon-o-clock', 'title' => __('3. Check the hours'),
                'body' => __('After the shift, freelancers submit their hours from their phone. Approve them, or correct them with a short reason.')],
            ['icon' => 'heroicon-o-banknotes', 'title' => __('4. Pay the freelancers'),
                'body' => __('Turn approved hours into a payment, pay by mobile money or bank, then mark it paid. The freelancer sees it straight away.')],
            ['icon' => 'heroicon-o-home', 'title' => __('Start each day on the dashboard'),
                'body' => __('It shows what needs you today. Switch between English and French at the top right. You can replay this tour from your profile menu.')],
        ];
    }

    /** @return list<array{icon: string, title: string, body: string}> */
    private static function portal(User $user, string $agency): array
    {
        $first = $user->freelancerProfile?->first_name ?? $user->name;

        return [
            ['icon' => 'heroicon-o-hand-raised', 'title' => __('Welcome, :name', ['name' => $first]),
                'body' => __(':agency uses this portal to send you work and pay you. Here is how it works, in four steps.', ['agency' => $agency])],
            ['icon' => 'heroicon-o-calendar-days', 'title' => __('1. See your assignments'),
                'body' => __('Your next shifts appear on the home screen and in your calendar, with the place, the times and the dress code.')],
            ['icon' => 'heroicon-o-clock', 'title' => __('2. Submit your hours'),
                'body' => __('After each shift, enter your start, end and break. Do it the same day so you are paid on time.')],
            ['icon' => 'heroicon-o-banknotes', 'title' => __('3. Follow your payments'),
                'body' => __('See your approved hours, the amount, and the day the agency paid you.')],
            ['icon' => 'heroicon-o-identification', 'title' => __('4. Keep your profile up to date'),
                'body' => __('Add your CV, photo and certificates, and tell the agency the days you are not available.')],
        ];
    }
}

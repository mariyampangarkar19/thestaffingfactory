<?php

namespace App\Enums;

/**
 * Built-in roles every agency gets. Permissions are attached in RolesAndPermissionsSeeder.
 */
enum Role: string
{
    case Owner = 'owner';           // agency owner: everything incl. users, billing, settings
    case Manager = 'manager';       // runs operations: freelancers, clients, requests, assignments
    case Recruiter = 'recruiter';   // freelancers, documents, quiz results
    case Sales = 'sales';           // clients and staff requests
    case Finance = 'finance';       // payments, invoices, reports
    case Viewer = 'viewer';         // read-only
    case Freelancer = 'freelancer'; // freelancer portal only

    public function isStaff(): bool
    {
        return $this !== self::Freelancer;
    }

    /** @return list<string> */
    public static function staffValues(): array
    {
        return array_values(array_map(fn (self $r) => $r->value, array_filter(self::cases(), fn (self $r) => $r->isStaff())));
    }
}

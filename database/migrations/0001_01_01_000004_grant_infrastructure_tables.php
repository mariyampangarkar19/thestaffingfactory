<?php

use App\Support\Database\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'] as $t) {
            RowLevelSecurity::grantToApp($t);
            RowLevelSecurity::grantToPlatform($t);
        }
        RowLevelSecurity::grantSequenceUsage();
    }

    public function down(): void {}
};

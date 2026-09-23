<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes for the columns the admin panel filters, sorts and counts on.
     *
     * Every entry is guarded with Schema::hasIndex()/hasColumn() so the migration is
     * re-runnable on installs that already added one of these by hand, and safe on
     * both MySQL and the SQLite the test suite runs on.
     *
     * @var array<string, array<string, array<int, string>>>
     */
    private array $indexes = [
        'activity_logs' => [
            'activity_logs_created_at_index' => ['created_at'],
            'activity_logs_action_index' => ['action'],
            'activity_logs_subject_type_subject_id_index' => ['subject_type', 'subject_id'],
            'activity_logs_causer_email_index' => ['causer_email'],
        ],
        'admin_notifications' => [
            'admin_notifications_type_read_at_index' => ['type', 'read_at'],
        ],
        'users' => [
            'users_verified_at_index' => ['verified_at'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($indexes as $indexName => $columns) {
                if (! Schema::hasColumns($tableName, $columns) || Schema::hasIndex($tableName, $indexName)) {
                    continue;
                }

                Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName): void {
                    $table->index($columns, $indexName);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach (array_keys($indexes) as $indexName) {
                if (! Schema::hasIndex($tableName, $indexName)) {
                    continue;
                }

                Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                    $table->dropIndex($indexName);
                });
            }
        }
    }
};

<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('users:purge-deleted {--days= : Override retention window in days}')]
#[Description('Permanently force-delete users whose account_deleted_at is older than the retention window.')]
class PurgeDeletedUsers extends Command
{
    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('auth.account_deletion_retention_days', 30));
        $cutoff = now()->subDays($days);

        $users = User::whereNotNull('account_deleted_at')
            ->where('account_deleted_at', '<', $cutoff)
            ->limit(50)
            ->get();

        $count = 0;
        foreach ($users as $user) {
            $user->forceDelete();
            $count++;
        }

        $this->info("Purged {$count} account(s) deleted before {$cutoff->toDateTimeString()}.");

        return self::SUCCESS;
    }
}

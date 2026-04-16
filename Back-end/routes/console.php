<?php

use App\Models\Group;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('groups:repair-legacy', function () {
    $groups = Group::with('users:id')->get();
    $updatedOwners = 0;
    $attachedOwners = 0;
    $generatedCodes = 0;

    foreach ($groups as $group) {
        $ownerId = $group->owner_id;
        if (empty($ownerId)) {
            $ownerId = $group->users()->orderBy('users.id')->value('users.id');
            if (!empty($ownerId)) {
                $group->owner_id = (int) $ownerId;
                $group->save();
                $updatedOwners++;
            }
        }

        if (!empty($ownerId)) {
            $isOwnerMember = $group->users()->where('users.id', (int) $ownerId)->exists();
            if (!$isOwnerMember) {
                $group->users()->syncWithoutDetaching([(int) $ownerId]);
                $attachedOwners++;
            }
        }

        if (empty($group->invite_code)) {
            do {
                $code = strtoupper(Str::random(8));
            } while (Group::where('invite_code', $code)->exists());

            $group->invite_code = $code;
            $group->save();
            $generatedCodes++;
        }
    }

    $this->info('Legacy group repair complete.');
    $this->line("Owners backfilled: {$updatedOwners}");
    $this->line("Owner memberships attached: {$attachedOwners}");
    $this->line("Invite codes generated: {$generatedCodes}");
})->purpose('Repairs legacy groups: owner, membership, invite code');

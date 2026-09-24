<?php

namespace App\Policies;

use App\Models\AIImport;
use App\Models\User;

class AIImportPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('super_admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function view(User $user, AIImport $import): bool
    {
        // Import owner may subscribe to private-ai-import.{id} (teacher paste/generate status).
        return (int) $user->id === (int) $import->user_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function update(User $user, AIImport $import): bool
    {
        return $user->hasRole('super_admin');
    }

    public function delete(User $user, AIImport $import): bool
    {
        return $user->hasRole('super_admin');
    }
}

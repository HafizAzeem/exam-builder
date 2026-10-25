<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Diagram extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['image_path'];

    protected $casts = ['elements' => 'array', 'is_builtin' => 'boolean'];

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('status', 'approved')->orWhere('user_id', $user->id);
            if ($user->hasRole('super_admin')) {
                $q->orWhere('status', 'pending');
            }
        });
    }

    public function canEdit(User $user): bool
    {
        return ! $this->is_builtin && $this->user_id === $user->id && in_array($this->status, ['private', 'rejected'], true);
    }

    public function payload(User $user): array
    {
        return array_merge($this->toArray(), [
            'image_url' => $this->image_path ? route('diagrams.image', $this) : null,
            'can_edit' => $this->canEdit($user),
            'is_owner' => $this->user_id === $user->id,
        ]);
    }
}

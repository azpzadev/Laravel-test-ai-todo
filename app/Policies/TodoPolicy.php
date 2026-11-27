<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Todo;
use App\Models\User;

final class TodoPolicy
{
    /**
     * Determine whether the user can view the todo.
     */
    public function view(User $user, Todo $todo): bool
    {
        return $this->isOwner(user: $user, todo: $todo);
    }

    /**
     * Determine whether the user can update the todo.
     */
    public function update(User $user, Todo $todo): bool
    {
        return $this->isOwner(user: $user, todo: $todo);
    }

    /**
     * Determine whether the user can delete the todo.
     */
    public function delete(User $user, Todo $todo): bool
    {
        return $this->isOwner(user: $user, todo: $todo);
    }

    /**
     * A todo belongs to exactly one user; unowned todos belong to nobody.
     */
    private function isOwner(User $user, Todo $todo): bool
    {
        return $todo->user_id !== null && $todo->user_id === $user->id;
    }
}

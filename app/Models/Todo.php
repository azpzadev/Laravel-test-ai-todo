<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TodoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title Short human-readable label for the task.
 * @property string|null $description Optional long-form details about the task.
 * @property bool $completed Whether the task has been finished.
 * @property Carbon|null $due_date Optional date the task is due.
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['title', 'description', 'completed', 'due_date'])]
class Todo extends Model
{
    /** @use HasFactory<TodoFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed' => 'boolean',
            'due_date' => 'date',
        ];
    }
}

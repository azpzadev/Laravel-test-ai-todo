<?php

declare(strict_types=1);

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

final readonly class TodoData
{
    public function __construct(
        public string $title,
        public ?string $description,
        public bool $completed,
        public ?string $due_date,
    ) {}

    /**
     * Build the DTO from a validated FormRequest.
     */
    public static function fromRequest(FormRequest $request): self
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        return new self(
            title: (string) $validated['title'],
            description: isset($validated['description']) ? (string) $validated['description'] : null,
            completed: (bool) ($validated['completed'] ?? false),
            due_date: isset($validated['due_date']) ? (string) $validated['due_date'] : null,
        );
    }

    /**
     * Derive a change-set of ONLY the attributes present in the validated
     * payload. Absent keys are omitted so a partial PATCH never clobbers
     * stored values. Values remain validated and typed — the raw request is
     * never used.
     *
     * @return array<string, mixed>
     */
    public static function changesFromRequest(FormRequest $request): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $changes = [];

        if (array_key_exists('title', $validated)) {
            $changes['title'] = (string) $validated['title'];
        }

        if (array_key_exists('description', $validated)) {
            $changes['description'] = $validated['description'] === null
                ? null
                : (string) $validated['description'];
        }

        if (array_key_exists('completed', $validated)) {
            $changes['completed'] = (bool) $validated['completed'];
        }

        if (array_key_exists('due_date', $validated)) {
            $changes['due_date'] = $validated['due_date'] === null
                ? null
                : (string) $validated['due_date'];
        }

        return $changes;
    }

    /**
     * Represent the DTO as an array for persistence.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'completed' => $this->completed,
            'due_date' => $this->due_date,
        ];
    }
}

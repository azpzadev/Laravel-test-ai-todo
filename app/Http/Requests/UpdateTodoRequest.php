<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Todo;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateTodoRequest extends FormRequest
{
    /**
     * Only the owner of the todo may update it (see TodoPolicy::update).
     */
    public function authorize(): bool
    {
        $todo = $this->route('todo');

        return $todo instanceof Todo
            && $this->user()?->can('update', $todo) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'completed' => ['sometimes', 'boolean'],
            'due_date' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return config('todo.messages');
    }
}

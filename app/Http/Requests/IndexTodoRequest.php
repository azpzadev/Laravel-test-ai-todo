<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class IndexTodoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'completed' => ['sometimes', 'boolean'],
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

    /**
     * Resolve the completed filter as a strict tri-state value.
     *
     * The `boolean` rule accepts query-string "1"/"0" as strings, so an
     * explicit (bool) cast is needed to hand the service a real bool.
     * array_key_exists (not isset) preserves the absent-vs-present
     * distinction that drives the unchanged-when-absent listing behavior.
     */
    public function completedFilter(): ?bool
    {
        $validated = $this->validated();

        return array_key_exists('completed', $validated)
            ? (bool) $validated['completed']
            : null;
    }
}

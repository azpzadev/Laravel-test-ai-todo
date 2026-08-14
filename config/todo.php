<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Messages
    |--------------------------------------------------------------------------
    |
    | Centralised validation messages for the Todo form requests. Keys follow
    | the "attribute.rule" convention consumed by FormRequest::messages().
    |
    */

    'messages' => [
        'title.required' => 'A title is required for the todo.',
        'title.string' => 'The title must be a valid string.',
        'title.max' => 'The title may not be greater than 255 characters.',
        'description.string' => 'The description must be a valid string.',
        'description.max' => 'The description may not be greater than 5000 characters.',
        'completed.boolean' => 'The completed flag must be true or false.',
        'due_date.date' => 'The due date must be a valid date.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Controls the page size of the todo listing endpoint. Extracted here so
    | the value is never hardcoded in the service or controller.
    |
    */

    'pagination' => [
        'per_page' => 15,
    ],

];

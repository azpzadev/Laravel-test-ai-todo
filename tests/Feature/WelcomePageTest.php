<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Feature test for the static Todo welcome page.
 *
 * The page at GET / renders a hardcoded Todo UI directly from the Blade view
 * (no DB, controller, or CRUD). RefreshDatabase is intentionally omitted
 * because the page is fully static and touches no database.
 */
final class WelcomePageTest extends TestCase
{
    // ---------------------------------------------------------------------
    // happy path — route + status
    // ---------------------------------------------------------------------

    public function test_welcome_route_returns_ok(): void
    {
        $response = $this->get('/');

        $response->assertOk();
    }

    // ---------------------------------------------------------------------
    // heading + stat labels
    // ---------------------------------------------------------------------

    public function test_welcome_page_shows_heading_and_stats(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('My Todos');
        $response->assertSee('Total');
        $response->assertSee('Completed');
        $response->assertSee('Pending');

        // Derived stat counts bound to their labels in rendered order:
        // each stat card renders the number immediately before its label.
        // 5 total, 2 completed, 3 pending — computed in-view from $todos.
        $response->assertSeeInOrder(['5', 'Total', '2', 'Completed', '3', 'Pending']);
    }

    // ---------------------------------------------------------------------
    // sample todo list — all five titles
    // ---------------------------------------------------------------------

    public function test_welcome_page_lists_all_sample_todos(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Buy groceries');
        $response->assertSee('Finish project report');
        $response->assertSee('Call the dentist');
        $response->assertSee('Read Laravel docs');
        $response->assertSee('Pay electricity bill');
    }

    // ---------------------------------------------------------------------
    // null-field edge case + add form markers
    // ---------------------------------------------------------------------

    public function test_welcome_page_renders_null_due_date_fallback_and_add_form(): void
    {
        $response = $this->get('/');

        $response->assertOk();

        // "Call the dentist" has a null due_date → the view renders the fallback.
        $response->assertSee('No due date');

        // Add-todo form markers.
        $response->assertSee('Add Todo');
        $response->assertSee('Title');
        $response->assertSee('Description');
        $response->assertSee('Due date');

        // Bind the markers to real labelled controls, not bare substrings,
        // so a labelled input actually exists in the markup.
        $response->assertSee('for="title"', escape: false);
        $response->assertSee('id="title"', escape: false);
        $response->assertSee('for="description"', escape: false);
        $response->assertSee('id="description"', escape: false);
        $response->assertSee('for="due_date"', escape: false);
        $response->assertSee('id="due_date"', escape: false);
        $response->assertSee('type="date"', escape: false);
    }
}

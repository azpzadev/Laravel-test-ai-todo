<?php

declare(strict_types=1);

// -------------------------------------------------------------------------
// GET / — Todo AI landing page (static, no database or auth involved)
// -------------------------------------------------------------------------

it('renders the landing page for guests', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('Todo AI')
        ->assertSee('AI-assisted planning')
        ->assertSee('sorted by AI.');
});

it('links the navigation to every landing section', function (): void {
    $response = $this->get('/')->assertOk();

    foreach (['features', 'preview', 'get-started'] as $sectionId) {
        $response->assertSee('href="#'.$sectionId.'"', escape: false)
            ->assertSee('id="'.$sectionId.'"', escape: false);
    }
});

it('lists the three feature cards', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder(['Why Todo AI', 'Smart next step', 'Due dates that matter', 'Private by default']);
});

it('suggests the open todo with the earliest due date', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder(['AI suggestion', 'Start with', 'Buy groceries', 'Aug 20, 2026'])
        ->assertDontSee("You're all caught up.");
});

it('shows the API endpoints needed to get started', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('Get started with the API')
        ->assertSee('Authorization: Bearer &lt;token&gt;', escape: false)
        ->assertSeeInOrder(['/api/register', '/api/login', '/api/todos', '/api/todos']);
});

it('does not disclose the framework or PHP version', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertDontSee('Laravel v'.app()->version())
        ->assertDontSee('PHP v'.PHP_VERSION);
});

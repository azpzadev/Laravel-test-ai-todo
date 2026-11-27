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

it('links the navigation to the AI chat section', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('href="#chat"', escape: false)
        ->assertSee('id="chat"', escape: false)
        ->assertSee('AI chat');
});

it('renders the AI chat sign-in form with labelled fields', function (): void {
    $response = $this->get('/')->assertOk();

    foreach (['chat-email', 'chat-password', 'chat-message'] as $fieldId) {
        $response->assertSee('for="'.$fieldId.'"', escape: false)
            ->assertSee('id="'.$fieldId.'"', escape: false);
    }

    $response->assertSee('id="chat-login-form"', escape: false)
        ->assertSee('Sign in to chat')
        ->assertSee('id="chat-panel" class="mt-6 flex flex-col gap-4" hidden', escape: false);
});

it('points the AI chat client at the login, logout and chat endpoints', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('data-login-url="'.route('auth.login').'"', escape: false)
        ->assertSee('data-logout-url="'.route('auth.logout').'"', escape: false)
        ->assertSee('data-chat-url="'.route('todos.chat').'"', escape: false)
        ->assertSee('/api/chat');
});

it('shows example prompts while the chat is empty', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder(['No messages yet.', 'Add &quot;Buy milk&quot; due tomorrow', 'What is still open?'], escape: false);
});

it('does not disclose the framework or PHP version', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertDontSee('Laravel v'.app()->version())
        ->assertDontSee('PHP v'.PHP_VERSION);
});

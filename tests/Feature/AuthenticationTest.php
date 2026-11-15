<?php

use App\Http\Middleware\EnsureAdmin;
use App\Models\Profile;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

function makeAdmin(): User
{
    $admin = User::factory()->create();
    Profile::create(['user_id' => $admin->id, 'type' => 'admin']);

    return $admin;
}

function makeCommonUser(): User
{
    $user = User::factory()->create();
    Profile::create(['user_id' => $user->id, 'type' => 'PF']);

    return $user;
}

// --- Visitante ---------------------------------------------------------------

test('guest is redirected to login when opening the home page', function () {
    $this->get('/')->assertRedirect(route('login'));
});

test('guest is redirected to login when opening the admin panel', function () {
    $this->get('/admin/users')->assertRedirect(route('login'));
});

test('login page is a standalone form without the admin layout', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Entre com seu email e senha')
        ->assertSee('name="_token"', false)
        ->assertDontSee('app-sidebar', false)
        ->assertDontSee('app-header', false);
});

test('register page is a standalone form without the admin layout', function () {
    $this->get('/register')
        ->assertOk()
        ->assertSee('Preencha os campos para se cadastrar')
        ->assertDontSee('app-sidebar', false)
        ->assertSee(route('login'), false);
});

// --- Cadastro ----------------------------------------------------------------

test('public registration creates a common user and redirects to login', function () {
    $this->post('/store', [
        'name' => 'Maria Teste',
        'email' => 'maria@example.test',
        'password' => 'senha-segura',
    ])->assertRedirect(route('login'))
        ->assertSessionHas('success');

    $user = User::where('email', 'maria@example.test')->firstOrFail();
    expect($user->isAdmin())->toBeFalse();
    expect($user->profile)->toBeNull();
});

test('public registration cannot grant admin privileges', function () {
    $this->post('/store', [
        'name' => 'Espertinho',
        'email' => 'esperto@example.test',
        'password' => 'senha-segura',
        'type' => 'admin',
        'profile' => ['type' => 'admin'],
    ])->assertRedirect(route('login'));

    $user = User::where('email', 'esperto@example.test')->firstOrFail();
    expect($user->isAdmin())->toBeFalse();
    expect(Profile::where('user_id', $user->id)->exists())->toBeFalse();
});

test('registration validates fields and keeps non sensitive input', function () {
    User::factory()->create(['email' => 'repetido@example.test']);

    $this->from('/register')->post('/store', [
        'name' => 'Fulana',
        'email' => 'repetido@example.test',
        'password' => 'curta',
    ])->assertRedirect('/register')
        ->assertSessionHasErrors(['email', 'password'])
        ->assertSessionHasInput('name', 'Fulana')
        ->assertSessionMissing('_old_input.password');
});

test('admin creating a user from the form returns to the admin list', function () {
    $this->actingAs(makeAdmin())
        ->post('/store', [
            'name' => 'Criado pelo admin',
            'email' => 'novo@example.test',
            'password' => 'senha-segura',
        ])->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success');
});

// --- Login -------------------------------------------------------------------

test('common user logs in and goes to the home page', function () {
    $user = makeCommonUser();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

test('admin logs in and goes to the admin panel', function () {
    $admin = makeAdmin();

    $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect(route('admin.users.index'));
});

test('admin is sent back to the internal page requested before login', function () {
    $admin = makeAdmin();

    $this->get('/admin/user/'.$admin->id.'/edit')->assertRedirect(route('login'));

    $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect(route('admin.user.edit', $admin));
});

test('common user ignores a stored admin destination after login', function () {
    $user = makeCommonUser();

    $this->get('/admin/users')->assertRedirect(route('login'));

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('home'));
});

test('login regenerates the session id', function () {
    $user = makeCommonUser();

    $this->get('/login');
    $before = session()->getId();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    expect(session()->getId())->not->toBe($before);
});

test('invalid credentials show a generic message and keep only the email', function () {
    $user = makeCommonUser();

    $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'errada'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => 'Credenciais inválidas.'])
        ->assertSessionHasInput('email', $user->email)
        ->assertSessionMissing('_old_input.password');

    $this->from('/login')->post('/login', ['email' => 'naoexiste@example.test', 'password' => 'errada'])
        ->assertSessionHasErrors(['email' => 'Credenciais inválidas.']);

    $this->assertGuest();
});

test('login validates required fields', function () {
    $this->from('/login')->post('/login', [])
        ->assertSessionHasErrors(['email', 'password']);
});

test('login is rate limited after five attempts', function () {
    $user = makeCommonUser();

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['email' => $user->email, 'password' => 'errada'])->assertStatus(302);
    }

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(429);
    $this->assertGuest();
});

// --- Logout ------------------------------------------------------------------

test('logout is a POST that ends the session and redirects to login', function () {
    $this->actingAs(makeCommonUser());

    $this->post('/logout')->assertRedirect(route('login'));

    $this->assertGuest();
});

test('logout rejects GET requests and guests', function () {
    $this->actingAs(makeCommonUser());
    $this->get('/logout')->assertStatus(405);

    auth()->logout();
    $this->post('/logout')->assertRedirect(route('login'));
});

test('logout invalidates the session and regenerates the csrf token', function () {
    $this->actingAs(makeCommonUser())->withSession(['foo' => 'bar']);
    $tokenBefore = session()->token();

    $this->post('/logout');

    expect(session()->has('foo'))->toBeFalse();
    expect(session()->token())->not->toBe($tokenBefore);
});

test('header exposes a POST logout form with csrf', function () {
    $this->actingAs(makeAdmin())
        ->get('/admin/users')
        ->assertOk()
        ->assertSee('action="'.route('logout').'"', false)
        ->assertSee('method="POST"', false)
        ->assertSee('Sair');
});

// --- Autorização -------------------------------------------------------------

test('common user sees the home page but gets 403 on the admin panel', function () {
    $user = makeCommonUser();

    $this->actingAs($user)->get('/')->assertOk();
    $this->actingAs($user)->get('/admin/users')->assertForbidden();
});

test('user without profile gets 403 on the admin panel', function () {
    $this->actingAs(User::factory()->create())->get('/admin/users')->assertForbidden();
});

test('common user gets 403 on every admin route and method', function () {
    $user = makeCommonUser();
    $target = User::factory()->create();

    $this->actingAs($user);
    $this->get("/admin/user/{$target->id}")->assertForbidden();
    $this->get("/admin/user/{$target->id}/edit")->assertForbidden();
    $this->put("/admin/user/{$target->id}", ['name' => 'x', 'email' => 'x@example.test'])->assertForbidden();
    $this->put("/admin/user/{$target->id}/profile", ['type' => 'PF'])->assertForbidden();
    $this->put("/admin/user/{$target->id}/languages", [])->assertForbidden();
    $this->delete("/admin/user/{$target->id}")->assertForbidden();

    expect(User::find($target->id))->not->toBeNull();
});

test('admin reaches the admin panel', function () {
    $this->actingAs(makeAdmin())->get('/admin/users')->assertOk();
});

test('every admin route is protected by the admin middleware', function () {
    $adminRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'admin'));

    expect($adminRoutes)->not->toBeEmpty();

    $adminRoutes->each(fn ($route) => expect($route->gatherMiddleware())
        ->toContain(EnsureAdmin::class));
});

// --- Painel (funcionalidades existentes) -------------------------------------

test('admin can edit a user, profile and languages', function () {
    $admin = makeAdmin();
    $target = makeCommonUser();

    $this->actingAs($admin);

    $this->get(route('admin.user.edit', $target))->assertOk();

    $this->put(route('admin.user.update', $target), ['name' => 'Novo Nome', 'email' => $target->email])
        ->assertRedirect(route('admin.users.index'));
    expect($target->fresh()->name)->toBe('Novo Nome');

    $this->put(route('admin.user.updateProfile', $target), ['type' => 'PJ', 'address' => 'Rua A, 1'])
        ->assertRedirect(route('admin.users.index'));
    expect($target->profile()->first())->type->toBe('PJ')->address->toBe('Rua A, 1');

    $this->put(route('admin.user.updateLanguages', $target), ['languages' => ['PHP', 'Java']]);
    $this->put(route('admin.user.updateLanguages', $target), ['languages' => ['PHP']]);
    expect($target->languages()->pluck('language')->all())->toBe(['PHP']);
});

test('editing the profile of an admin does not remove the admin type', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)
        ->put(route('admin.user.updateProfile', $admin), ['type' => 'PF', 'address' => 'Rua B'])
        ->assertRedirect(route('admin.users.index'));

    expect($admin->fresh()->isAdmin())->toBeTrue();
});

test('admin routes return 404 for a missing user', function () {
    $this->actingAs(makeAdmin())->get('/admin/user/9999/edit')->assertNotFound();
});

// --- Seeder ------------------------------------------------------------------

test('admin seeder provisions an admin account for local environments', function () {
    $this->seed(AdminUserSeeder::class);
    $this->seed(AdminUserSeeder::class); // idempotente

    $admin = User::where('email', 'admin@example.test')->firstOrFail();

    expect($admin->isAdmin())->toBeTrue();
    expect(User::where('email', 'admin@example.test')->count())->toBe(1);

    $this->post('/login', ['email' => 'admin@example.test', 'password' => 'senha-dev-123'])
        ->assertRedirect(route('admin.users.index'));
});

test('admin cannot delete their own account but can delete another user', function () {
    $admin = makeAdmin();
    $other = makeCommonUser();

    $this->actingAs($admin)
        ->delete(route('admin.user.destroy', $admin))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('error');
    expect(User::find($admin->id))->not->toBeNull();

    $this->actingAs($admin)
        ->delete(route('admin.user.destroy', $other))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success');
    expect(User::find($other->id))->toBeNull();
});

test('admin list shows the delete form with csrf and the success message', function () {
    makeCommonUser();

    $this->actingAs(makeAdmin())
        ->withSession(['success' => 'Usuário criado com sucesso!'])
        ->get('/admin/users')
        ->assertOk()
        ->assertSee('Usuário criado com sucesso!')
        ->assertSee('name="_method" value="DELETE"', false);
});

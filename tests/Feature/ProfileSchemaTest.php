<?php

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

// --- Perfil e schema ---------------------------------------------------------
// Estes testes rodam contra o schema REAL produzido pelas migrations do projeto
// (a original do perfil + a que renomeia description para address). Se algum
// falhar, o model e o banco estão divergentes e o problema aparece aqui.

test('the Profile model table matches the real schema', function () {
    $table = (new Profile)->getTable();

    expect($table)->toBe('profile');
    expect(Schema::hasTable($table))->toBeTrue();
});

test('the profile table has user_id, type and address but no description', function () {
    expect(Schema::hasColumns('profile', ['user_id', 'type', 'address']))->toBeTrue();
    expect(Schema::hasColumn('profile', 'description'))->toBeFalse();
});

test('User has one Profile and the admin middleware can read its type', function () {
    expect((new User)->profile())->toBeInstanceOf(HasOne::class);

    $user = User::factory()->create();
    expect($user->profile)->toBeNull();
    expect($user->isAdmin())->toBeFalse();

    Profile::create(['user_id' => $user->id, 'type' => 'admin']);

    $user = $user->fresh();
    expect($user->profile->type)->toBe('admin');
    expect($user->isAdmin())->toBeTrue();
});

test('the profile type column accepts PF, PJ and admin', function () {
    foreach (['PF', 'PJ', 'admin'] as $type) {
        $user = User::factory()->create();
        Profile::create(['user_id' => $user->id, 'type' => $type]);

        expect($user->fresh()->profile->type)->toBe($type);
    }
});

test('address is optional on the profile', function () {
    $user = User::factory()->create();
    Profile::create(['user_id' => $user->id, 'type' => 'PF']);

    expect($user->fresh()->profile->address)->toBeNull();
});

test('the address migration is safe to run again on an already corrected schema', function () {
    $migration = require database_path('migrations/2026_11_04_120000_rename_description_to_address_on_profile_table.php');

    $migration->up();
    $migration->up();

    expect(Schema::hasColumn('profile', 'address'))->toBeTrue();
    expect(Schema::hasColumn('profile', 'description'))->toBeFalse();
});

// --- Tela de cadastro --------------------------------------------------------

test('register form posts to the store route with csrf and asks for name, email and password', function () {
    $this->get('/register')
        ->assertOk()
        ->assertSee('action="'.route('users.store').'"', false)
        ->assertSee('method="POST"', false)
        ->assertSee('name="_token"', false)
        ->assertSee('name="name"', false)
        ->assertSee('name="email"', false)
        ->assertSee('name="password"', false)
        ->assertDontSee('app-header', false);
});

test('after a failed registration the form keeps name and email but never the password', function () {
    $this->from('/register')->post('/store', [
        'name' => 'Fulana de Tal',
        'email' => 'invalido',
        'password' => 'segredo-curto',
    ]);

    $this->followingRedirects()->get('/register')
        ->assertSee('value="Fulana de Tal"', false)
        ->assertSee('value="invalido"', false)
        ->assertDontSee('segredo-curto');
});

test('registration enforces unique email and a minimum password length', function () {
    User::factory()->create(['email' => 'ja@example.test']);

    $this->post('/store', ['name' => 'A', 'email' => 'ja@example.test', 'password' => '12345678'])
        ->assertSessionHasErrors('email');

    $this->post('/store', ['name' => 'A', 'email' => 'novo@example.test', 'password' => '1234567'])
        ->assertSessionHasErrors('password');

    expect(User::where('email', 'novo@example.test')->exists())->toBeFalse();
});

test('the login page shows the confirmation after a public registration', function () {
    $this->post('/store', ['name' => 'Maria', 'email' => 'maria2@example.test', 'password' => 'senha-segura'])
        ->assertRedirect(route('login'));

    $this->followingRedirects()->get('/login')
        ->assertSee('Cadastro realizado com sucesso');
});

<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Cria o administrador de demonstração. Só roda em ambiente local/testes:
 * é a única forma de provisionar um admin, não existe formulário público para isso.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('AdminUserSeeder ignorado: disponível apenas em ambiente local.');

            return;
        }

        $admin = User::updateOrCreate(
            ['email' => 'admin@example.test'],
            ['name' => 'Administrador (dev)', 'password' => 'senha-dev-123'],
        );

        Profile::updateOrCreate(
            ['user_id' => $admin->id],
            ['type' => 'admin'],
        );
    }
}

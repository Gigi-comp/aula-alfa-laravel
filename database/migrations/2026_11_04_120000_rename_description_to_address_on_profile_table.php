<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A tabela real é `profile` (singular), a mesma configurada no model Profile.
 * A coluna criada na migration original se chama `description`, mas o model e o
 * formulário usam `address`. Em vez de editar a migration já compartilhada, esta
 * nova migration corrige o schema e é segura para bancos que já a aplicaram
 * (ou que já tenham a coluna renomeada).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('profile', 'description') && ! Schema::hasColumn('profile', 'address')) {
            Schema::table('profile', function (Blueprint $table) {
                $table->renameColumn('description', 'address');
            });
        }

        // O endereço é opcional (a validação usa `nullable`).
        Schema::table('profile', function (Blueprint $table) {
            $table->string('address')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('profile', 'address') && ! Schema::hasColumn('profile', 'description')) {
            Schema::table('profile', function (Blueprint $table) {
                $table->renameColumn('address', 'description');
            });
        }
    }
};

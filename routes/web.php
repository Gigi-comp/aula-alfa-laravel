<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->middleware('auth')->name('home');

// Autenticação
Route::get('login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('login.store');
Route::post('logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Cadastro (público; um administrador autenticado também pode usar o formulário)
Route::get('register', [UserController::class, 'register'])->name('register');
Route::post('store', [UserController::class, 'store'])->name('users.store');

// Todas as rotas administrativas passam pelo mesmo grupo: autenticação + perfil admin.
Route::middleware(EnsureAdmin::class)->prefix('admin')->group(function () {
    Route::get('users', [UserController::class, 'index'])->name('admin.users.index');
    Route::get('user/{user}', [UserController::class, 'show'])->name('admin.user.show');
    Route::get('user/{user}/edit', [UserController::class, 'edit'])->name('admin.user.edit');
    Route::put('user/{user}', [UserController::class, 'update'])->name('admin.user.update');
    Route::put('user/{user}/profile', [UserController::class, 'updateProfile'])->name('admin.user.updateProfile');
    Route::put('user/{user}/languages', [UserController::class, 'updateLanguages'])->name('admin.user.updateLanguages');
    Route::delete('user/{user}', [UserController::class, 'destroy'])->name('admin.user.destroy');
});

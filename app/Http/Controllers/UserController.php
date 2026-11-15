<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;
use App\Models\Profile;

class UserController extends Controller
{
    
   public function index(){
    $users = User::paginate(5);

    return view('user.index',[
        'users' => $users,
        'title' => 'Lista de usuario do Sistema'
    ]);
   }

   public function show(User $user)
   {
    return view('user.show',[
        'user' => $user]);

   }

   public function register()
   {
       return view('user.create');
   }

   public function store(Request $request)
   {
    $input = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
        'password' => 'required|string|min:8',
    ]);

    // Só name/email/password são aceitos: o cadastro nunca cria perfil nem concede privilégios.
    User::create($input);

    if ($request->user()?->isAdmin()) {
        return redirect()->route('admin.users.index')->with('success', 'Usuário criado com sucesso!');
    }

    return redirect()->route('login')->with('success', 'Cadastro realizado com sucesso. Faça login para continuar.');

   }


   public function edit(User $user)
   {
       $user->load('profile','languages');
       return view('user.edit', [
           'user' => $user
       ]);
   }

   public function update(Request $request, User $user)
   {
       $input = $request->validate([
           'name' => 'required|string|max:255',
           'email' => [
               'required',
               'string',
               'email',
               'max:255',
               Rule::unique('users', 'email')->ignore($user->id),
           ],
       ]);

       $user->update($input);

       return redirect()->route('admin.users.index')->with('success', 'Usuário atualizado com sucesso!');
   }

   public function updateProfile(User $user ,Request $request)
   {
       
       $input = $request->validate([
           'type' => 'required|string|in:PJ,PF',
           'address' => [
               'nullable',
               'string',
               'max:255'
           ],
       ]);
       
       $profile = Profile::firstOrNew(['user_id' => $user->id]);
       $profile->address = $input['address'] ?? null;

       // O tipo 'admin' não pode ser alterado por este formulário (PF/PJ), senão o
       // administrador perderia o acesso ao editar o próprio perfil.
       if (! $user->isAdmin()) {
           $profile->type = $input['type'];
       }

       $profile->save();

       return redirect()->route('admin.users.index')->with('success', 'Perfil atualizado com sucesso!');
   }

   public function updateLanguages(User $user, Request $request)
   {
       $input = $request->validate([
           'languages' => 'nullable|array',
           'languages.*' => 'string|in:PHP,JavaScript,Python,Java,C#',
       ]);

    $user->languages()->delete();
    $user->languages()->createMany(array_map(fn($language) => ['language' => $language], $input['languages'] ?? []));

       return redirect()->route('admin.users.index')->with('success', 'Linguagens atualizadas com sucesso!');
   }

   public function destroy(Request $request, User $user)
   {
       // Evita que o administrador exclua a própria conta e perca o acesso ao painel.
       if ($request->user()->is($user)) {
           return redirect()->route('admin.users.index')->with('error', 'Você não pode excluir a própria conta.');
       }

       $user->delete();
       return redirect()->route('admin.users.index')->with('success', 'Usuário excluído com sucesso!');
   }


}

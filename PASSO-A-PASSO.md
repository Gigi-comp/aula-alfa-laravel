# Autenticação e área administrativa — passo a passo

Projeto: `aula-alfa-laravel` (Laravel 13, PHP 8.4, AdminLTE/Bootstrap 5, Pest).
Os comandos abaixo funcionam no PowerShell (Windows). No Linux/macOS troque `Copy-Item` por `cp` e `New-Item` por `touch`.

## 1. Preparar o projeto

```powershell
# 1. Instalar dependências PHP
composer install

# 2. Criar o .env e a chave da aplicação
Copy-Item .env.example .env
php artisan key:generate

# 3. Criar o banco SQLite (arquivo vazio) e rodar migrations + seeders
New-Item -ItemType File database/database.sqlite -Force
php artisan migrate --seed

# 4. Instalar e compilar o front-end
npm install
npm run build        # ou: npm run dev (deixe aberto em outro terminal)

# 5. Subir a aplicação (se usar Laravel Herd, ele já serve o projeto)
php artisan serve
```

Abra `http://localhost:8000`.

Para zerar o banco e semear de novo: `php artisan migrate:fresh --seed`.

Para usar MySQL em vez de SQLite: suba `database/docker-compose.yml` (`docker compose -f database/docker-compose.yml up -d`) e use as variáveis comentadas no `.env.example`.

## 2. Contas de demonstração

| Conta | Email | Senha | Como é criada |
|---|---|---|---|
| Administrador (dev) | `admin@example.test` | `senha-dev-123` | `AdminUserSeeder`, chamado pelo `DatabaseSeeder` |
| Conta comum | a que você cadastrar em `/register` | a que você escolher (mín. 8 caracteres) | cadastro público |

O administrador só é criado quando `APP_ENV` é `local` ou `testing`. Em qualquer outro ambiente o seeder avisa e não cria nada. Não existe formulário público que aceite `type = admin`: o cadastro só grava nome, email e senha. A senha `senha-dev-123` é apenas de desenvolvimento.

## 3. Roteiro de demonstração

1. **Visitante.** Sem login, abra `/` e depois `/admin/users`. Nos dois casos você é enviado para `/login`.
2. **Cadastro de conta comum.** Em `/login` clique em "Cadastre-se" (ou abra `/register`). Preencha nome, email e senha. Você volta para `/login` com a mensagem "Cadastro realizado com sucesso. Faça login para continuar."
3. **Login e logout da conta comum.** Entre com a conta criada. Você cai em `/` e o seu nome aparece no topo, ao lado do botão **Sair**. Clique em **Sair**: a sessão é encerrada e você volta para `/login`.
4. **Acesso administrativo negado.** Entre de novo com a conta comum e abra `/admin/users`. A resposta é **403**.
5. **Administrador.** Saia, entre com `admin@example.test` / `senha-dev-123`. Você cai em `/admin/users`, onde pode ver, editar, excluir e cadastrar usuários.
6. **Destino interno preservado.** Sem login, abra `/admin/user/1/edit`. Depois de entrar como administrador, você é levado de volta a essa página (um usuário comum nunca é levado para uma página administrativa).
7. **Limite de tentativas.** Erre a senha 5 vezes seguidas para o mesmo email. A 6ª tentativa, mesmo com a senha certa, devolve **429 Too Many Requests**. Espere cerca de 1 minuto para tentar de novo.

## 4. Como cada requisito foi atendido

### Login (`GET /login`, `POST /login`)
- `resources/views/auth/login.blade.php` é uma página HTML completa, sem `@extends('layouts.default')`: só o formulário, sem cabeçalho, menu lateral ou painel.
- Usa as classes do AdminLTE (`login-page`, `login-box`) e os assets do projeto (`@vite('resources/scss/app.scss')`).
- O formulário envia por POST para `login.store` com `@csrf`.
- Erros de validação aparecem sob cada campo. Credenciais erradas mostram sempre a mesma mensagem, "Credenciais inválidas.", sem dizer se o email existe.
- O email digitado volta preenchido (`old('email')` e `onlyInput('email')`). A senha nunca volta.
- Tem link para `/register`.
- Ao autenticar, `AuthController::login` chama `session()->regenerate()`. Usuário comum vai para `/`. Administrador vai para `/admin/users`, respeitando o destino pedido antes do login (`redirect()->intended`). Para usuário comum, o destino guardado é descartado.

### Limitação de tentativas
- Definida em `AppServiceProvider::boot()`: `RateLimiter::for('login', ...)` com `Limit::perMinute(5)`.
- A chave é `email em minúsculas + IP`. Cada combinação tem seu próprio contador, então errar a senha de uma conta não bloqueia as outras e trocar maiúsculas/minúsculas no email não burla o limite.
- É aplicada na rota com `->middleware('throttle:login')`, antes do controller. Na 6ª tentativa dentro de 1 minuto a resposta é 429 e a senha nem é verificada.
- O contador usa o cache da aplicação (`CACHE_STORE`).

### Logout (`POST /logout`)
- A rota exige `auth`. `AuthController::logout` faz `logout()`, `session()->invalidate()` e `session()->regenerateToken()`, e redireciona para `login`.
- O botão **Sair** está em `resources/views/parts/header.blade.php`, no menu do usuário, dentro de um `<form method="POST">` com `@csrf`. Não existe logout por link GET (um GET em `/logout` devolve 405).

### Middleware administrativo
- `app/Http/Middleware/EnsureAdmin.php`:
  - visitante: redireciona para `/login` (e guarda o destino);
  - autenticado sem perfil admin: `403`;
  - perfil com `type = admin`: segue.
- A checagem é `User::isAdmin()`, que lê `profile?->type === 'admin'`. Estar autenticado, por si só, não dá acesso.

### Rotas (`routes/web.php`)

| Método e caminho | Acesso | Nome |
|---|---|---|
| `GET /` | autenticado | `home` |
| `GET /login` | público | `login` |
| `POST /login` | público, com `throttle:login` | `login.store` |
| `POST /logout` | autenticado | `logout` |
| `GET /register` | público | `register` |
| `POST /store` | público | `users.store` |
| `GET /admin/users` | admin | `admin.users.index` |
| `GET /admin/user/{user}` | admin | `admin.user.show` |
| `GET /admin/user/{user}/edit` | admin | `admin.user.edit` |
| `PUT /admin/user/{user}` | admin | `admin.user.update` |
| `PUT /admin/user/{user}/profile` | admin | `admin.user.updateProfile` |
| `PUT /admin/user/{user}/languages` | admin | `admin.user.updateLanguages` |
| `DELETE /admin/user/{user}` | admin | `admin.user.destroy` |

Todas as rotas `/admin/*` estão num único grupo com `EnsureAdmin`. Há um teste que falha se alguma rota `admin*` ficar fora da proteção.

### Cadastro (`GET /register`, `POST /store`)
- `resources/views/user/create.blade.php` é independente do layout, pede nome, email e senha, usa POST com `@csrf`, mostra erros, preserva nome e email e tem link para o login.
- Regras (`UserController::store`): nome obrigatório (máx. 255), email obrigatório, válido e **único**, senha obrigatória com **mínimo de 8 caracteres**.
- A conta criada é sempre comum: sem perfil e sem privilégios.
- Visitante: vai para `/login` com confirmação. Administrador logado: volta para `/admin/users` com "Usuário criado com sucesso!".

### Perfil e schema
O que foi conferido no fork:
- A tabela real é **`profile`** (singular), criada por `2026_08_27_000637_create_profile_table.php`, e o model já tem `protected $table = 'profile'`.
- A migration original cria a coluna **`description`** (obrigatória), mas o model, a validação e o formulário usam **`address`**.
- `User::profile()` é um `hasOne(Profile::class)`, e é por ela que o middleware lê o `type`.

Estratégia adotada: a migration original **não foi editada**. A nova `2026_11_04_120000_rename_description_to_address_on_profile_table.php` renomeia `description` para `address` (só se `description` existir e `address` não) e torna `address` opcional. Funciona em bancos já migrados (os dados são mantidos) e em bancos novos, e pode rodar de novo sem erro. O `down()` desfaz o renome.

Também entrou `2026_10_21_201117_create_user_languages_table.php` e o model `UserLanguages`, que o `User::languages()` e a tela de edição já usavam e que não existiam no fork.

## 5. O que mudou em relação ao fork

| Arquivo | Mudança |
|---|---|
| `routes/web.php` | reescrito: login, logout, cadastro e grupo admin completo (corrige o middleware `autenyticado` inexistente e o `EnsureAdmin` sem `use`) |
| `app/Http/Controllers/AuthController.php` | novo |
| `app/Http/Controllers/UserController.php` | route model binding, `404` automático, `isAdmin()`, linguagens sem duplicar, admin não exclui a própria conta |
| `app/Http/Middleware/EnsureAdmin.php` | middleware admin (login ou 403) |
| `app/Models/User.php`, `UserLanguages.php` | `isAdmin()`, relação `languages`, model novo |
| `app/Providers/AppServiceProvider.php` | limitador `login` (5/min por email + IP) |
| `database/migrations/…user_languages…`, `…rename_description_to_address…` | novas |
| `database/seeders/AdminUserSeeder.php`, `DatabaseSeeder.php` | administrador de demonstração (só em `local`/`testing`) |
| `resources/views/parts/*` | novos (o layout já os incluía, mas a pasta não existia); header com nome do usuário e botão **Sair** |
| `resources/views/layouts/default.blade.php` | removidos scripts de demonstração e arquivos inexistentes (`./js/adminlte.js`, `./css/adminlte.css`, gráficos); título e idioma ajustados; Bootstrap Icons via CDN |
| `resources/views/user/index.blade.php` | mensagens de sucesso/erro, botão Ver e formulário de exclusão funcionando (POST + `DELETE` + CSRF) |
| `resources/views/user/edit.blade.php` | checkbox de linguagens envia `languages[]`, compatível com a validação |
| `resources/views/auth/login.blade.php`, `user/create.blade.php` | do fork, só com o link dos Bootstrap Icons |
| `tests/Feature/*` | testes de autenticação, autorização, cadastro, schema e exclusão |
| `.env.example` | novo (o fork não tinha) |

## 6. Rodar os testes

```powershell
php artisan test --compact
```

Cobrem, entre outros: visitante redirecionado, login de comum e de admin, destino interno, regeneração de sessão, mensagem genérica, limite de 5 tentativas, logout só por POST, 403 em todas as rotas admin, cadastro sem privilégio, schema da tabela `profile` e seeder.

## 7. Problemas comuns

- **Página sem estilo:** rode `npm run build` (ou deixe `npm run dev` aberto).
- **Ícones não aparecem:** os ícones vêm de um CDN, então precisam de internet.
- **`npm ci` reclama do lock:** o `package-lock.json` está desatualizado em relação ao `package.json`. Use `npm install`.
- **`composer install` reclama da versão do PHP:** o `composer.lock` exige PHP 8.4.
- **`no such table`:** rode `php artisan migrate --seed`.
- **Admin não foi criado:** confira `APP_ENV=local` no `.env` e rode `php artisan db:seed --class=AdminUserSeeder`.
- **Conferir o schema real:** `php artisan tinker --execute="dump(Schema::getColumnListing('profile'));"` deve listar `address` e não listar `description`.

@extends('layouts.default')
@section('title', 'Usuários')
@section('page-title', 'Usuários')
@section('content')
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    <table class="table table-bordered" role="table">
        <thead>
            <tr>
                <th style="width: 10px" scope="col">#</th>
                <th scope="col">Nome</th>
                <th scope="col">Email</th>
                <th scope="col">Ações</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($users as $user)
                <tr class="align-middle">
                    <td>{{ $user->id }}.</td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>
                        <a href="{{ route('admin.user.show', $user->id) }}" class="btn btn-secondary btn-sm">Ver</a>
                        <a href="{{ route('admin.user.edit', $user->id) }}" class="btn btn-primary btn-sm">Editar</a>
                        <form action="{{ route('admin.user.destroy', $user->id) }}" method="POST" class="d-inline"
                            onsubmit="return confirm('Excluir este usuário?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Excluir</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    {{ $users->links('pagination::bootstrap-5') }}
@endsection

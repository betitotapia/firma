@extends('layouts.app')

@section('content')
<div class="card">
  <h2>Acceso</h2>
  <p class="muted">Ingresa con tu usuario administrador.</p>
  <form method="POST" action="{{ route('login') }}">
    @csrf
    <label>Email</label>
    <input type="email" name="email" value="{{ old('email') }}" required>
    <label>Password</label>
    <input type="password" name="password" required>
    <div style="margin-top:14px;">
      <button class="btn" type="submit">Entrar</button>
    </div>
  </form>
</div>
@endsection

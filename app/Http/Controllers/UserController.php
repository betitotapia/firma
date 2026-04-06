<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->when($q !== '', fn($qq) => $qq->where('name','like',"%$q%")->orWhere('email','like',"%$q%"))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('users.index', compact('users', 'q'));
    }

    public function create()
    {
        return view('users.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:users,email'],
            'password' => ['required','string','min:8','confirmed'],
            'can_send' => ['nullable'],
            'can_delete' => ['nullable'],
            'can_manage_users' => ['nullable'],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'can_send' => (bool)($data['can_send'] ?? false),
            'can_delete' => (bool)($data['can_delete'] ?? false),
            'can_manage_users' => (bool)($data['can_manage_users'] ?? false),
        ]);

        return redirect()->route('users.index')->with('ok', 'Usuario creado.');
    }

    public function edit(User $user)
    {
        return view('users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:users,email,'.$user->id],
            'password' => ['nullable','string','min:8','confirmed'],
            'can_send' => ['nullable'],
            'can_delete' => ['nullable'],
            'can_manage_users' => ['nullable'],
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->can_send = (bool)($data['can_send'] ?? false);
        $user->can_delete = (bool)($data['can_delete'] ?? false);
        $user->can_manage_users = (bool)($data['can_manage_users'] ?? false);

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return redirect()->route('users.index')->with('ok', 'Usuario actualizado.');
    }

    public function destroy(User $user)
    {
        // Evitar que se borre a sí mismo
        if (auth()->id() === $user->id) {
            return back()->withErrors(['user' => 'No puedes eliminar tu propio usuario.']);
        }

        $user->delete();

        return redirect()->route('users.index')->with('ok', 'Usuario eliminado.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Show the admin's profile edit form.
     */
    public function edit(Request $request)
    {
        return view('admin.profile.edit', ['user' => $request->user()]);
    }

    /**
     * Update the admin's profile.
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => 'nullable|required_with:password|current_password',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];

        if (!empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        return redirect()->route('admin.profile.edit')->with('success', 'Perfil atualizado com sucesso!');
    }
}

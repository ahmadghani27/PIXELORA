<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed']
        ], [
            'current_password.required' => 'Kata sandi saat ini wajin diisi',
            'current_password.current_password' => 'Kata sandi saat ini salah',
            'password.required' => 'Kata sandi baru wajib diisi',
            'password.confirmed' => 'Konfirmasi kata sandi tidak sesuai'
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', 'Kata sandi berhasil diperbarui');
    }
}

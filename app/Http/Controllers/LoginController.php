<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


use App\Helpers\AlertHelper;

class LoginController extends Controller
{
    public function index()
    {
        // $alert = AlertHelper::alertSuccess('Pesan sukses kustom', 'Selamat!', 2000);

        return view('pages.login.index');
    }
    public function authenticate(Request $request)
    {   
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $credentials = $request->only('username', 'password');

        Log::info('Attempting to authenticate user', $credentials);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            Log::info('User authenticated successfully', ['user_id' => Auth::id()]);

            // Ambil pengguna yang terautentikasi
            $user = Auth::user();

            // Tambahkan log untuk melihat peran pengguna
            Log::info('User role:', ['role' => $user->role]);

            // Redirect berdasarkan role
            switch ($user->role) {
                case 'dosen':
                    AlertHelper::alertSuccess('Anda telah berhasil login', 'Selamat!', 2000);
                    return redirect()->route('dosendashboard');
                case 'mahasiswa':
                    AlertHelper::alertSuccess('Anda telah berhasil login', 'Selamat!', 2000);
                    return redirect()->route('mahasiswadashboard');
                case 'kaprodi':
                    AlertHelper::alertSuccess('Anda telah berhasil login', 'Selamat!', 2000);
                    return redirect()->route('kaprodi.dashboard');
                case 'admin':
                    AlertHelper::alertSuccess('Anda telah berhasil login', 'Selamat!', 2000);
                    return redirect()->route('admin.dashboard');
                default:
                    Log::warning('Unknown user role', ['role' => $user->role]);
                    return redirect('dashboard');
            }
        }

        Log::warning('Failed to authenticate user', $credentials);

        return back()->withErrors([
            'auth' => 'The provided credentials do not match our records.',
        ])->withInput()->with('error', 'Username dan Password anda salah');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

<?php

namespace App\Http\Controllers;


use App\Helpers\AlertHelper;
use App\Models\Penilaian;
use App\Models\PenilaianSeminar;
use App\Models\PenilaianSeminarKomprehensif;
use App\Models\Pertanyaan;
use App\Models\SeminarKomprehensif;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Dosen;
use App\Models\DosenPembimbing;
use App\Models\JudulTugasAkhir;
use App\Models\Konsultasi;
use App\Models\Logbook;
use App\Models\Mahasiswa;
use App\Models\MahasiswaBimbingan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\SeminarProposal;

class MahasiswaBimbinganController extends Controller
{

    public function __construct()
    {
        $this->middleware('role:dosen');
    }
    public function showStudents()
    {
        // Mendapatkan ID pengguna yang sedang login
        $userId = auth()->user()->id;

        // Cari dosen berdasarkan user_id
        $dosen = Dosen::where('user_id', $userId)->first();


        // Mengambil data dosen pembimbing
        $dosenPembimbing = DosenPembimbing::where('dosen_id', $dosen->id)->firstOrFail();

        // Mengambil mahasiswa yang dibimbing oleh dosen ini
        $mahasiswaBimbingans = MahasiswaBimbingan::where('dosen_pembimbing_id', $dosenPembimbing->id)->with('mahasiswa')->get();

        // Mengambil judul tugas akhir yang diterima
        $judulTugasAkhirs = JudulTugasAkhir::whereIn('mahasiswa_bimbingan_id', $mahasiswaBimbingans->pluck('id'))
            ->where('status', 'diterima')
            ->get();

        // Mengambil logbook mahasiswa bimbingan
        $logbooks = Logbook::whereIn('mahasiswa_bimbingan_id', $mahasiswaBimbingans->pluck('id'))->get();

        // Mengirim data ke view
        return view('pages.dosen.daftarmahasiswabimbingan', compact('dosenPembimbing', 'mahasiswaBimbingans', 'judulTugasAkhirs', 'logbooks'));
    }


    public function bimbingan_show($id)
    {
        // Mendapatkan ID pengguna yang sedang login
        $userId = auth()->user()->id;

        // Cari dosen berdasarkan user_id
        $dosen = Dosen::where('user_id', $userId)->first();

        // Memastikan dosen ditemukan
        if (!$dosen) {
            return redirect()->back()->with('error', 'Dosen tidak ditemukan.');
        }

        // Mengambil data dosen pembimbing
        $dosenPembimbing = DosenPembimbing::where('dosen_id', $dosen->id)->firstOrFail();

        // Mengambil mahasiswa bimbingan berdasarkan ID
        $mahasiswaBimbingan = MahasiswaBimbingan::findOrFail($id);

        // Pastikan mahasiswa ini benar-benar dibimbing oleh dosen yang sedang login
        if ($mahasiswaBimbingan->dosen_pembimbing_id !== $dosenPembimbing->id) {
            abort(403, 'Unauthorized action.');
        }

        // Mengambil judul tugas akhir yang diterima untuk mahasiswa ini
        $judulTugasAkhir = JudulTugasAkhir::where('mahasiswa_bimbingan_id', $id)
            ->where('status', 'diterima')
            ->first();

        // Mengambil semua logbook untuk mahasiswa ini
        $logbooks = Logbook::where('mahasiswa_bimbingan_id', $id)
            ->latest()
            ->get();

        // Mengirim data ke view, pastikan judulTugasAkhir tidak null sebelum dikirimkan
        return view('pages.dosen.mahasiswadetail', compact('mahasiswaBimbingan', 'judulTugasAkhir', 'logbooks'));
    }



}

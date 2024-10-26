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




class DosenController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:dosen');
    }

    public function dashboard()
    {
        // Ambil ID dosen yang sedang login
        $dosenId = auth()->user()->id;

        // Hitung jumlah mahasiswa bimbingan yang terhubung dengan dosen ini
        $mahasiswaCount = MahasiswaBimbingan::whereHas('dosenPembimbing', function ($query) use ($dosenId) {
            $query->where('dosen_id', $dosenId);
        })->count();

        // Hitung jumlah PenilaianSeminar yang terhubung dengan dosen ini
        $penilaianSeminarCount = PenilaianSeminar::whereHas('seminarProposal', function ($query) use ($dosenId) {
            $query->where(function ($subQuery) use ($dosenId) {
                $subQuery->where('dosen_penguji_1_id', $dosenId)
                         ->orWhere('dosen_penguji_2_id', $dosenId);
            });
        })->count();

        // Hitung jumlah PenilaianSeminarKomprehensif yang terhubung dengan dosen ini
        $penilaianKomprehensifCount = PenilaianSeminarKomprehensif::whereHas('seminarKomprehensif', function ($query) use ($dosenId) {
            $query->where(function ($subQuery) use ($dosenId) {
                $subQuery->where('dosen_penguji_1_id', $dosenId)
                         ->orWhere('dosen_penguji_2_id', $dosenId);
            });
        })->count();

        // Jumlahkan total dari PenilaianSeminar dan PenilaianSeminarKomprehensif
        $totalPenilaian = $penilaianSeminarCount + $penilaianKomprehensifCount;

        // Hitung jumlah konsultasi yang terhubung dengan dosen ini dan belum terlewat
        $today = \Carbon\Carbon::today();
        $konsultasi = Konsultasi::whereHas('mahasiswaBimbingan', function ($query) use ($dosenId) {
            $query->whereHas('dosenPembimbing', function ($subQuery) use ($dosenId) {
                $subQuery->where('dosen_id', $dosenId);
            });
        })->whereDate('tanggal', '>=', $today)->count();

        return view('pages.dosen.dashboard', compact('mahasiswaCount', 'totalPenilaian', 'konsultasi'));
    }


    public function profile()
    {
        // dd(Auth::user()->dosen);

        return view('Dosen.Biodata.biodata');
    }

    public function updateProfile(Request $request)
    {
        // Validasi data
        $request->validate([
            'nidn' => 'required|string|max:255',
            'nama' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'no_hp' => 'required|string|max:255',
            'alamat' => 'required|string',
            'deskripsi' => 'nullable|string',
            'gambar' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Validasi gambar
        ]);

        // Ambil data dosen yang sedang login
        $dosen = auth()->user()->dosen;

        // Update atribut dosen
        $dosen->nidn = $request->nidn;
        $dosen->nama = $request->nama;
        $dosen->department = $request->department;
        $dosen->no_hp = $request->no_hp;
        $dosen->alamat = $request->alamat;
        $dosen->deskripsi = $request->deskripsi;

        // Perbarui gambar profil jika ada
        if ($request->hasFile('gambar')) {
            Log::info('Gambar ditemukan dalam request.');
            $profileImage = $request->file('gambar');
            $profileImageSaveAsName = time() . Auth::id() . "-profile." . $profileImage->getClientOriginalExtension();
            $upload_path = 'dosen_images/';
            $profile_image_url = $upload_path . $profileImageSaveAsName;
            $profileImage->move(public_path($upload_path), $profileImageSaveAsName);
            Log::info('Gambar berhasil diunggah ke: ' . $profile_image_url);
            $dosen->gambar = $profile_image_url;
        } else {
            Log::info('Gambar tidak ditemukan dalam request.');
        }

        // Simpan perubahan
        $dosen->save();

        AlertHelper::alertSuccess('Anda telah berhasil mengupdate profil', 'Selamat!', 2000);
        return redirect()->route('profile');
    }




public function updatePassword(Request $request)
{
    $request->validate([
        'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore(Auth::user()->id)],
        'password_lama' => ['required'],
        'password' => 'required|confirmed', // Password confirmation
    ], [
        'username.required' => 'Username harus diisi.',
        'username.max' => 'Username maksimal 255 karakter.',
        'username.unique' => 'Username sudah digunakan oleh pengguna lain.',
        'password_lama.required' => 'Password lama harus diisi.',
        'password.required' => 'Password baru harus diisi.',
        'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
    ]);

    $user = Auth::user();

    // Validasi password lama
    if (!Hash::check($request->password_lama, $user->password)) {
        return back()->withErrors(['password_lama' => 'Password lama tidak cocok']);
    }

    // Update username
    $user->username = $request->username;
    $user->save();

    // Update password
    $user->password = Hash::make($request->password);
    $user->save();

    // Hapus gambar lama jika ada
    if ($user->profile_image) {
        $gambarProfilPath = 'dosen_images/' . $user->profile_image;

        // Hapus gambar dari storage
        if (Storage::disk('public')->exists($gambarProfilPath)) {
            Storage::disk('public')->delete($gambarProfilPath);
            // Set kolom gambar_profil ke null (jika ada)
            $user->profile_image = null;
            $user->save();
        }
    }
    AlertHelper::alertSuccess('Anda telah berhasil mengupdate username dan passowrd', 'Selamat!', 2000);
    return redirect()->back();
}







public function showSubmittedTitles()
{
    // Mendapatkan ID dosen pembimbing yang sedang login
    $dosenId = Auth::user()->dosen->id;

    // Mengambil semua judul tugas akhir terkait dosen yang sedang login, diurutkan berdasarkan ID terbaru dan status 'diproses'
    $judulTugasAkhirs = JudulTugasAkhir::whereHas('mahasiswaBimbingan', function($query) use ($dosenId) {
        $query->whereHas('dosenPembimbing', function($query) use ($dosenId) {
            $query->where('dosen_id', $dosenId);
        });
    })
    ->where('status', 'diproses') // Filter status 'diproses'
    ->orderBy('id', 'desc') // Urutkan berdasarkan ID terbaru
    ->get();

    // Debugging data judul tugas akhir
    // dd($judulTugasAkhirs);

    // Mengirim data ke view
    return view('pages.dosen.pengajuanjudul', compact('judulTugasAkhirs'));
}





public function approveTitle(Request $request, $id)
{
    $judulTugasAkhir = JudulTugasAkhir::findOrFail($id);
    $judulTugasAkhir->status = 'diterima';
    $judulTugasAkhir->saran = $request->input('saran');
    $judulTugasAkhir->save();

    return redirect()->route('dosen_pengajuan_judul')->with('success', 'Judul tugas akhir berhasil diterima.');
}

public function rejectTitle(Request $request, $id)
{
    $judulTugasAkhir = JudulTugasAkhir::findOrFail($id);
    $judulTugasAkhir->status = 'ditolak';
    $judulTugasAkhir->saran = $request->input('saran');
    $judulTugasAkhir->save();

    return redirect()->route('dosen_pengajuan_judul')->with('success', 'Judul tugas akhir berhasil ditolak.');
}


public function rutekonsultasi()
{
    $dosenId = Auth::user()->dosen->id; // Ambil ID dosen yang sedang login

    // Ambil semua data konsultasi terkait dosen yang sedang login, diurutkan berdasarkan tanggal terbaru
    $konsultasis = Konsultasi::with('mahasiswaBimbingan.mahasiswa')
        ->whereHas('mahasiswaBimbingan.dosenPembimbing', function($query) use ($dosenId) {
            $query->where('dosen_id', $dosenId);
        })
        ->orderBy('tanggal', 'desc') // Urutkan berdasarkan tanggal terbaru
        ->get();

    // Ambil daftar nama mahasiswa dari mahasiswaBimbingan terkait dosen yang sedang login
    $mahasiswaList = Mahasiswa::whereHas('mahasiswaBimbingans.dosenPembimbing', function($query) use ($dosenId) {
        $query->where('dosen_id', $dosenId);
    })->get();

    return view('pages.dosen.konsultasimahasiswa', compact('konsultasis', 'mahasiswaList'));
}



public function respond(Request $request, $id)
{
    $request->validate([
        'status' => 'required|in:Diproses,Diterima,Ditolak',
        'Pembahasan' => 'nullable|string'
    ]);

    $konsultasi = Konsultasi::findOrFail($id);
    $konsultasi->status = $request->status;
    $konsultasi->Pembahasan = $request->Pembahasan;
    $konsultasi->save();

    return redirect()->route('dosen.konsultasi.index')->with('success', 'Respon konsultasi berhasil disimpan.');
}
public function printKonsultasiBimbingan($mahasiswaBimbinganId)
    {
        // Ambil data dosen yang sedang login
        $dosen = Auth::user()->dosen;

        // Ambil data mahasiswa bimbingan berdasarkan ID
        $mahasiswaBimbingan = MahasiswaBimbingan::with('mahasiswa', 'dosenPembimbing.dosen')->findOrFail($mahasiswaBimbinganId);

        // Periksa apakah dosen yang sedang login adalah dosen pembimbing dari mahasiswa bimbingan tersebut
        if ($mahasiswaBimbingan->dosenPembimbing->dosen->id !== $dosen->id) {
            return abort(403, 'NOT FOUND ');
        }

        // Ambil riwayat konsultasi yang diterima oleh dosen tersebut dengan mahasiswa bimbingannya
        $konsultasis = Konsultasi::where('mahasiswa_bimbingan_id', $mahasiswaBimbinganId)
            ->where('status', 'Diterima')
            ->get();

        // Ambil judul tugas akhir yang diterima
        $judulTugasAkhir = JudulTugasAkhir::where('mahasiswa_bimbingan_id', $mahasiswaBimbinganId)
            ->where('status', 'diterima')
            ->first();

        return view('pages.dosen.konsultasi_bimbingan_print', compact('mahasiswaBimbingan', 'judulTugasAkhir', 'konsultasis'));
    }




public function approvelogbook(Request $request, $id)
{
    $logbook = Logbook::findOrFail($id);
    $logbook->status = 'Diterima';
    $logbook->respon = $request->input('respon');
    $logbook->save();

    return redirect()->back()->with('success', 'Logbook berhasil diterima.');
}

public function rejectlogbook(Request $request, $id)
{
    $logbook = Logbook::findOrFail($id);
    $logbook->status = 'Direvisi';
    $logbook->respon = $request->input('respon');
    $logbook->save();

    return redirect()->back()->with('success', 'Logbook berhasil ditolak.');
}


public function rutelogbook()
{
    $dosenId = Auth::user()->dosen->id; // Ambil ID dosen yang sedang login

    // Ambil semua data logbook terkait dosen yang sedang login, diurutkan berdasarkan tanggal terbaru
    $logbooks = Logbook::with('mahasiswaBimbingan.mahasiswa') // Ambil data logbook bersama relasi mahasiswa
        ->whereHas('mahasiswaBimbingan', function($query) use ($dosenId) {
            $query->whereHas('dosenPembimbing', function($query) use ($dosenId) {
                $query->where('dosen_id', $dosenId);
            });
        })
        ->where('status', 'diproses') // Hanya ambil logbook dengan status 'diproses'
        ->orderBy('id', 'desc') // Urutkan berdasarkan tanggal terbaru
        ->get();

    // Ambil daftar nama mahasiswa dari mahasiswaBimbingan terkait dosen yang sedang login
    $mahasiswaList = Mahasiswa::whereHas('mahasiswaBimbingans', function($query) use ($dosenId) {
        $query->whereHas('dosenPembimbing', function($query) use ($dosenId) {
            $query->where('dosen_id', $dosenId);
        });
    })->get();

    return view('pages.dosen.pengajuanglogbook', compact('logbooks', 'mahasiswaList'));
}





public function semprovalidasi()
{
    // Ambil dosen yang sedang login
    $userId = Auth::user()->id;

    // Cari dosen berdasarkan user_id
    $dosen = Dosen::where('user_id', $userId)->first();



    // Ambil dosen_pembimbing_id dari dosen yang sedang login
    $dosenPembimbingIds = DosenPembimbing::where('dosen_id', $dosen->id)->pluck('id')->toArray();

    // Ambil semua proposal seminar yang terkait dengan dosen yang sedang login
    $seminarProposals = SeminarProposal::whereHas('mahasiswaBimbingan', function($query) use ($dosenPembimbingIds) {
        $query->whereIn('dosen_pembimbing_id', $dosenPembimbingIds);
    })->with(['mahasiswaBimbingan.mahasiswa', 'mahasiswaBimbingan.acceptedJudulTugasAkhirs'])->get();

    return view('pages.dosen.validasisempro', compact('seminarProposals'));
}



public function approve_sempro(Request $request, $id)
{
    $seminarProposal = SeminarProposal::findOrFail($id);
    $seminarProposal->validasi_pembimbing = 'valid';
    $seminarProposal->save();

    return redirect()->route('dosen_semprovalidasi')->with('success', 'Seminar proposal telah diterima.');
}

public function reject_sempro(Request $request, $id)
{
    $seminarProposal = SeminarProposal::findOrFail($id);
    $seminarProposal->status_prodi = 'ditolak';
    $seminarProposal->validasi_pembimbing = 'ditolak';
    $seminarProposal->save();

    return redirect()->route('dosen_semprovalidasi')->with('success', 'Seminar proposal telah ditolak.');
}

public function semkomvalidasi()
{
    // Ambil dosen yang sedang login
    $userId = Auth::user()->id;

    // Cari dosen berdasarkan user_id
    $dosen = Dosen::where('user_id', $userId)->first();

    // Ambil dosen_pembimbing_id dari dosen yang sedang login
    $dosenPembimbingIds = DosenPembimbing::where('dosen_id', $dosen->id)->pluck('id')->toArray();

    // Ambil semua seminar komprehensif yang terkait dengan dosen yang sedang login
    $seminarProposals = SeminarKomprehensif::where('validasi_pembimbing', 'diproses')
        ->whereHas('mahasiswaBimbingan', function($query) use ($dosenPembimbingIds) {
            $query->whereIn('dosen_pembimbing_id', $dosenPembimbingIds);
        })->with(['mahasiswaBimbingan.mahasiswa', 'mahasiswaBimbingan.acceptedJudulTugasAkhirs'])->get();

    return view('pages.dosen.validasikompre', compact('seminarProposals'));
}


    public function approve_semkom(Request $request, $id)
    {
        $seminarProposal = SeminarKomprehensif::findOrFail($id);
        $seminarProposal->validasi_pembimbing = 'valid';
        $seminarProposal->save();

        return redirect()->route('dosen_semkomvalidasi')->with('success', 'Seminar komprehensif telah diterima.');
    }

    public function reject_semkom(Request $request, $id)
    {
        $seminarProposal = SeminarKomprehensif::findOrFail($id);
        $seminarProposal->status_prodi = 'ditolak';
        $seminarProposal->validasi_pembimbing = 'tidak_valid';
        $seminarProposal->save();

        return redirect()->route('dosen_semkomvalidasi')->with('success', 'Seminar komprehensif telah ditolak.');
    }
    public function createPenilaian(SeminarProposal $seminarProposal)
{
    $userId = auth()->user()->id;

    // Cari dosen berdasarkan user_id
    $dosen = Dosen::where('user_id', $userId)->first();

    // Jika dosen tidak ditemukan, return dengan error
    if (!$dosen) {
        return redirect()->route('dosen_seminarproposals.index')->with('error', 'Dosen tidak ditemukan.');
    }

    // Ambil dosen_id
    $dosenId = $dosen->id;

    // Cek apakah pengguna yang sedang login adalah dosen penguji 1 atau dosen penguji 2
    if ($dosenId !== $seminarProposal->dosen_penguji_1_id && $dosenId !== $seminarProposal->dosen_penguji_2_id) {
        return redirect()->route('dosen_seminarproposals.index')->with('error', 'Anda tidak berwenang mengakses halaman ini.');
    }

    // Ambil semua penilaian dengan relasi pertanyaans
    $penilaians = Penilaian::with('pertanyaans')->get();

    // Mengambil penilaian yang sudah ada untuk penguji yang sedang login
    $existingPenilaians = PenilaianSeminar::where('seminar_proposal_id', $seminarProposal->id)
        ->where('dosen_id', $dosenId)
        ->get()
        ->keyBy(function ($item) {
            return $item['kriteria_id'] . '.' . $item['pertanyaan_id'];
        });

    return view('pages.dosen.penilaianseminar', compact('seminarProposal', 'penilaians', 'existingPenilaians'));
}




















public function storePenilaian(Request $request, SeminarProposal $seminarProposal)
{
    // Ambil dosen_id dari user yang sedang login
    $dosen = Dosen::where('user_id', auth()->user()->id)->first();
    $dosenId = $dosen->id;

    // Ambil data penilaian dari request
    $penilaians = $request->input('penilaians');

    foreach ($penilaians as $penilaianId => $penilaianData) {
        foreach ($penilaianData['pertanyaans'] as $pertanyaanId => $nilai) {
            // Periksa apakah kriteria dan pertanyaan yang diberikan ada di database
            $kriteriaExists = Penilaian::find($penilaianId);
            $pertanyaanExists = Pertanyaan::find($pertanyaanId);

            if ($kriteriaExists && $pertanyaanExists) {
                PenilaianSeminar::updateOrCreate(
                    [
                        'seminar_proposal_id' => $seminarProposal->id,
                        'dosen_id' => $dosenId,
                        'kriteria_id' => $penilaianId,
                        'pertanyaan_id' => $pertanyaanId
                    ],
                    [
                        'nilai' => $nilai
                    ]
                );
            }
        }
    }

    // Update komentar dosen penguji
    if ($dosenId === $seminarProposal->dosen_penguji_1_id) {
        $seminarProposal->update(['komentar_penguji_1' => $request->input('komentar_penguji_1')]);
    } elseif ($dosenId === $seminarProposal->dosen_penguji_2_id) {
        $seminarProposal->update(['komentar_penguji_2' => $request->input('komentar_penguji_2')]);
    }

    // Menghitung nilai akhir dari kedua dosen penguji
    $totalNilaiDosenPenguji1 = 0;
    $totalBobotDosenPenguji1 = 0;
    $totalNilaiDosenPenguji2 = 0;
    $totalBobotDosenPenguji2 = 0;

    $dosenPenguji1Menilai = false;
    $dosenPenguji2Menilai = false;

    foreach ($seminarProposal->penilaianSeminars as $penilaian) {
        if ($penilaian->dosen_id == $seminarProposal->dosen_penguji_1_id) {
            $totalNilaiDosenPenguji1 += $penilaian->nilai * $penilaian->pertanyaan->bobot;
            $totalBobotDosenPenguji1 += $penilaian->pertanyaan->bobot;
            $dosenPenguji1Menilai = true;
        } elseif ($penilaian->dosen_id == $seminarProposal->dosen_penguji_2_id) {
            $totalNilaiDosenPenguji2 += $penilaian->nilai * $penilaian->pertanyaan->bobot;
            $totalBobotDosenPenguji2 += $penilaian->pertanyaan->bobot;
            $dosenPenguji2Menilai = true;
        }
    }

    $nilaiAkhirDosenPenguji1 = $totalBobotDosenPenguji1 ? $totalNilaiDosenPenguji1 / $totalBobotDosenPenguji1 : 0;
    $nilaiAkhirDosenPenguji2 = $totalBobotDosenPenguji2 ? $totalNilaiDosenPenguji2 / $totalBobotDosenPenguji2 : 0;

    // Menghitung nilai rata-rata
    $nilaiRataRata = ($nilaiAkhirDosenPenguji1 + $nilaiAkhirDosenPenguji2) / 2;

    // Perbarui status prodi jika kedua dosen penguji telah memberikan penilaian
    if ($dosenPenguji1Menilai && $dosenPenguji2Menilai) {
        if ($nilaiRataRata < 72) {
            $seminarProposal->update(['status_prodi' => 'direvisi']);
        } else {
            $seminarProposal->update(['status_prodi' => 'lulus']);
        }
    }

    return redirect()->route('dosen_seminarproposals.index')->with('success', 'Penilaian berhasil disimpan.');
}


public function storePenilaianKompre(Request $request, SeminarKomprehensif $komprehensif)
{
    // Ambil user_id dari pengguna yang sedang login
    $userId = auth()->user()->id;

    // Cari dosen berdasarkan user_id
    $dosen = Dosen::where('user_id', $userId)->first();

    // Ambil dosen_id dari dosen yang ditemukan
    $dosenId = $dosen->id;

    // Ambil penilaian dari permintaan
    $penilaians = $request->input('penilaians');
    foreach ($penilaians as $penilaianId => $penilaianData) {
        foreach ($penilaianData['pertanyaans'] as $pertanyaanId => $nilai) {
            PenilaianSeminarKomprehensif::updateOrCreate(
                [
                    'seminar_komprehensif_id' => $komprehensif->id,
                    'dosen_id' => $dosenId,
                    'kriteria_id' => $penilaianId,
                    'pertanyaan_id' => $pertanyaanId
                ],
                [
                    'nilai' => $nilai
                ]
            );
        }
    }

    // Menyimpan komentar penguji
    if ($dosenId === $komprehensif->dosen_penguji_1_id) {
        $komprehensif->update(['komentar_penguji_1' => $request->input('komentar_penguji_1')]);
    } elseif ($dosenId === $komprehensif->dosen_penguji_2_id) {
        $komprehensif->update(['komentar_penguji_2' => $request->input('komentar_penguji_2')]);
    }

    // Menghitung nilai akhir dari kedua dosen penguji
    $totalNilaiDosenPenguji1 = 0;
    $totalBobotDosenPenguji1 = 0;
    $totalNilaiDosenPenguji2 = 0;
    $totalBobotDosenPenguji2 = 0;

    $dosenPenguji1Menilai = false;
    $dosenPenguji2Menilai = false;

    foreach ($komprehensif->penilaianSeminarKomprehensif as $penilaian) {
        if ($penilaian->dosen_id == $komprehensif->dosen_penguji_1_id) {
            $totalNilaiDosenPenguji1 += $penilaian->nilai * $penilaian->pertanyaan->bobot;
            $totalBobotDosenPenguji1 += $penilaian->pertanyaan->bobot;
            $dosenPenguji1Menilai = true;
        } elseif ($penilaian->dosen_id == $komprehensif->dosen_penguji_2_id) {
            $totalNilaiDosenPenguji2 += $penilaian->nilai * $penilaian->pertanyaan->bobot;
            $totalBobotDosenPenguji2 += $penilaian->pertanyaan->bobot;
            $dosenPenguji2Menilai = true;
        }
    }

    $nilaiAkhirDosenPenguji1 = $totalBobotDosenPenguji1 ? $totalNilaiDosenPenguji1 / $totalBobotDosenPenguji1 : 0;
    $nilaiAkhirDosenPenguji2 = $totalBobotDosenPenguji2 ? $totalNilaiDosenPenguji2 / $totalBobotDosenPenguji2 : 0;

    // Menghitung nilai rata-rata
    $nilaiRataRata = ($nilaiAkhirDosenPenguji1 + $nilaiAkhirDosenPenguji2) / 2;

    // Perbarui status prodi jika kedua dosen penguji telah memberikan penilaian
    if ($dosenPenguji1Menilai && $dosenPenguji2Menilai) {
        if ($nilaiRataRata < 72) {
            $komprehensif->update(['status_prodi' => 'direvisi']);
        } else {
            $komprehensif->update(['status_prodi' => 'lulus']);
        }
    }

    return redirect()->route('dosen_seminarkomprehensif.index')->with('success', 'Penilaian berhasil disimpan.');
}


    public function show_seminar()
    {
        $userId = auth()->user()->id;

        // Cari dosen berdasarkan user_id
        $dosen = Dosen::where('user_id', $userId)->first();

        // Ambil dosen_id
        $dosenId = $dosen->id;

        // Query yang asli
        $seminarProposals = SeminarProposal::where('dosen_penguji_1_id', $dosenId)
                                ->orWhere('dosen_penguji_2_id', $dosenId)
                                ->with([
                                    'mahasiswaBimbingan.mahasiswa',
                                    'mahasiswaBimbingan.acceptedJudulTugasAkhirs',
                                    'dosenPenguji1',
                                    'dosenPenguji2',
                                    'penilaianSeminars' => function($query) use ($dosenId) {
                                        $query->where('dosen_id', $dosenId);
                                    },
                                    'penilaianSeminars.pertanyaan'
                                ])
                                ->get();

        // Hitung nilai akhir
        foreach ($seminarProposals as $seminarProposal) {
            $totalNilai = 0;
            $totalBobot = 0;
            foreach ($seminarProposal->penilaianSeminars as $penilaianSeminar) {
                $totalNilai += $penilaianSeminar->nilai * $penilaianSeminar->pertanyaan->bobot;
                $totalBobot += $penilaianSeminar->pertanyaan->bobot;
            }
            $seminarProposal->nilaiAkhir = $totalBobot ? $totalNilai / $totalBobot : 0;
        }

        return view('pages.dosen.daftar_ujian_seminar', compact('seminarProposals'));
    }


    public function show_komprehensif()
    {
        // Ambil dosen yang sedang login
        $userId = Auth::user()->id;

        // Cari dosen berdasarkan user_id
        $dosen = Dosen::where('user_id', $userId)->first();

        // Jika dosen tidak ditemukan, kembalikan dengan pesan error
        if (!$dosen) {
            return redirect()->route('dashboard')->with('error', 'Dosen tidak ditemukan.');
        }

        $dosenId = $dosen->id;

        // Ambil seminar komprehensif di mana dosen adalah penguji 1 atau 2
        $seminarKomprehensif = SeminarKomprehensif::where('dosen_penguji_1_id', $dosenId)
                            ->orWhere('dosen_penguji_2_id', $dosenId)
                            ->with(['mahasiswaBimbingan.mahasiswa', 'penilaianSeminarKomprehensif' => function($query) use ($dosenId) {
                                $query->where('dosen_id', $dosenId);
                            }, 'penilaianSeminarKomprehensif.pertanyaan'])
                            ->get();

        // Hitung nilai akhir untuk setiap seminar
        foreach ($seminarKomprehensif as $seminar) {
            $totalNilai = 0;
            $totalBobot = 0;
            foreach ($seminar->penilaianSeminarKomprehensif as $penilaianSeminar) {
                $totalNilai += $penilaianSeminar->nilai * $penilaianSeminar->pertanyaan->bobot;
                $totalBobot += $penilaianSeminar->pertanyaan->bobot;
            }
            $seminar->nilaiAkhir = $totalBobot ? $totalNilai / $totalBobot : 0;
        }

        return view('pages.dosen.daftarkomprehesif', compact('seminarKomprehensif'));
    }


    public function createPenilaianKompre(SeminarKomprehensif $komprehensif)
    {
        $userId = auth()->user()->id;

        // Cari dosen berdasarkan user_id
        $dosen = Dosen::where('user_id', $userId)->first();

        // Cek apakah pengguna yang sedang login adalah dosen penguji 1 atau dosen penguji 2
        if ($dosen->id !== $komprehensif->dosen_penguji_1_id && $dosen->id !== $komprehensif->dosen_penguji_2_id) {
            return redirect()->route('dosen_seminarkomprehensif.index')->with('error', 'Anda tidak berwenang mengakses halaman ini.');
        }

        $penilaians = Penilaian::with('pertanyaans')->get();

        // Mengambil penilaian yang sudah ada untuk penguji yang sedang login
        $existingPenilaians = PenilaianSeminarKomprehensif::where('seminar_komprehensif_id', $komprehensif->id)
            ->where('dosen_id', $dosen->id)
            ->get()
            ->keyBy(function ($item) {
                return $item['kriteria_id'] . '.' . $item['pertanyaan_id'];
            });

        return view('pages.dosen.penilaiankomprehensif', compact('komprehensif', 'penilaians', 'existingPenilaians'));
    }









    }

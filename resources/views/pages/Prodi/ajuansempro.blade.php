<!-- resources/views/pages/Prodi/ajuansempro.blade.php -->
@extends('layout.master')

@section('title', 'Validasi Seminar Proposal')

@section('content')
<div class="container">
    <h2>Validasi Seminar Proposal</h2>

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="datatable-container mb-3">
        <table id="seminarProposalTable" class="table table-bordered mt-3">
            <thead>
                <tr>
                    <th class="text-center">No</th>
                    <th class="text-center">Nama Mahasiswa</th>
                    <th class="text-center">Dosen Pembimbing</th>
                    <th class="text-center">Judul Proposal</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($seminarProposals as $index => $seminarProposal)
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td class="text-center">{{ $seminarProposal->mahasiswaBimbingan->mahasiswa->nama }}</td>
                        <td class="text-center">{{ $seminarProposal->mahasiswaBimbingan->dosenPembimbing->dosen->nama }}</td>
                        <td class="text-center">{{ $seminarProposal->mahasiswaBimbingan->acceptedJudulTugasAkhirs->isNotEmpty() ? $seminarProposal->mahasiswaBimbingan->acceptedJudulTugasAkhirs->last()->judul : 'Tidak ada judul tugas akhir' }}</td>
                        <td class="text-center">
                            <a href="{{ route('seminar-proposal.atur', ['id' => $seminarProposal->id]) }}" class="btn btn-success btn-sm">
                                <i class="fa fa-check"></i> Atur Jadwal Seminar
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        $('#seminarProposalTable').DataTable({
            "pagingType": "full_numbers",
            "language": {
                "search": "Cari:",
                "paginate": {
                    "first": "Awal",
                    "last": "Akhir",
                    "next": "Berikutnya",
                    "previous": "Sebelumnya"
                },
                "emptyTable": "Tidak ada pengajuan di dalam tabel",
                "zeroRecords": "Tidak ditemukan data yang sesuai"
            },
            "dom": '<"top"lf>rt<"bottom"ip><"clear">'
        });
    });
</script>
@endsection

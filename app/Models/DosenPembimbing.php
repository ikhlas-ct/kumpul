<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DosenPembimbing extends Model
{
    use HasFactory;

    protected $fillable = [
        'dosen_id',
        'jenis_dosbing',
    ];

    public function dosen()
    {
        return $this->belongsTo(Dosen::class);
    }

    public function mahasiswaBimbingans()
    {
        return $this->hasMany(MahasiswaBimbingan::class, 'dosen_pembimbing_id');
    }

}

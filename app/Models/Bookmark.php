<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bookmark extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'article_id', // Sesuaikan dengan nama kolom di migrasi
    ];

    public function article() // Ubah nama fungsi relasi
    {
        // Pastikan menunjuk ke model yang benar (Article::class)
        return $this->belongsTo(Article::class);
    }
}

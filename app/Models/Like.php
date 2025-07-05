<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Like extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'article_id',
        'type', // 'like' atau 'dislike'
    ];

    /**
     * Mendefinisikan bahwa sebuah 'like' dimiliki oleh satu User.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mendefinisikan bahwa sebuah 'like' dimiliki oleh satu Article.
     */
    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}

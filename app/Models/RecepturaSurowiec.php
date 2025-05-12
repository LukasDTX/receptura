<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecepturaSurowiec extends Model
{
    use HasFactory;

    protected $table = 'receptura_surowiec';
    
    protected $fillable = [
        'receptura_id',
        'surowiec_id',
        'ilosc',
    ];
}

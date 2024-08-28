<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SheinItem extends Model
{
    use HasFactory;
    protected $fillable = [
        'site_link',
        'url_hash',
        'woocommerce_product_id',
        'url',
        'woocommerce_link',
        'payload',
        'file_id',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Udalosť projektu, po ktorej môže odísť automatická kampaň.
 * Zoznam posiela projekt sám (PUT /api/v1/mailing/triggers).
 */
class MailingTrigger extends Model
{
    protected $fillable = ['product_id', 'key', 'label', 'short_label', 'variables', 'quote_kind'];

    protected function casts(): array
    {
        return ['variables' => 'array'];
    }
}

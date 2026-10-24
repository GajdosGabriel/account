<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Štítok kontaktu (segment), ktorý projekt posiela – napr. `trial`, `customer`.
 * Zoznam posiela projekt sám (PUT /api/v1/mailing/tags).
 */
class MailingTag extends Model
{
    protected $fillable = ['product_id', 'key', 'label', 'description'];
}

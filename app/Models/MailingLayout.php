<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Obal e-mailu kampane (hlavička, pätička, farby projektu).
 *
 * Značky: {{heading}}, {{subject}}, {{{content}}}, {{unsubscribe_url}},
 * {{product_name}}, {{from_name}}, {{from_address}} a sekcie
 * {{#unsubscribe_url}}…{{/unsubscribe_url}} – pozri MailingRenderer.
 */
class MailingLayout extends Model
{
    protected $fillable = ['product_id', 'name', 'html'];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}

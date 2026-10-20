<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Uložený obsah, z ktorého sa začína nová kampaň. */
class MailingTemplate extends Model
{
    protected $fillable = ['product_id', 'layout_id', 'name', 'subject', 'heading', 'body', 'button_label', 'button_url'];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /** @return BelongsTo<MailingLayout, $this> */
    public function layout(): BelongsTo
    {
        return $this->belongsTo(MailingLayout::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Odkaz v odoslanom e-maile prepísaný na meranie kliknutí. */
class MailingLink extends Model
{
    public $timestamps = false;

    protected $fillable = ['delivery_id', 'position', 'token', 'url', 'label', 'click_count', 'clicked_at'];

    protected function casts(): array
    {
        return ['clicked_at' => 'datetime'];
    }

    /** @return BelongsTo<MailingDelivery, $this> */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(MailingDelivery::class, 'delivery_id');
    }
}

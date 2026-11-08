<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * E-maily kampaní už inboxom nehýbu (ConversationMessage::SOURCE_MAILING).
 *
 * Staršie odpovede kampaní vo vlákne sa označia a konverzáciám, ktoré
 * kampaň posunula navrch a prepla na „Čaká na klienta“, sa vráti čas
 * a stav podľa poslednej správy, ktorá nebola z kampane. Vlákno, v ktorom
 * je len e-mail kampane, z inboxu zmizne, kým klient neodpovie.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('conversation_messages')
            ->where('kind', 'mailing.reply')
            ->update(['source' => 'mailing']);

        $ids = DB::table('conversation_messages')
            ->where('source', 'mailing')
            ->distinct()
            ->pluck('conversation_id');

        foreach ($ids as $id) {
            $messages = DB::table('conversation_messages')
                ->where('conversation_id', $id)
                ->where('source', '!=', 'mailing')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['direction', 'source', 'created_at']);

            $last = $messages->last();
            $conversation = DB::table('conversations')->where('id', $id)->first(['status']);

            $changes = [
                'last_message_at' => $last?->created_at,
                'last_outbound_at' => $messages->where('direction', 'out')->last()?->created_at,
            ];

            // Stav meníme len tam, kde ho mohla prepnúť kampaň.
            if ($conversation?->status === 'waiting' && $last) {
                $changes['status'] = match (true) {
                    $last->direction === 'in' => 'open',
                    $last->source === 'inbox' => 'answered',
                    default => 'waiting',
                };
            }

            DB::table('conversations')->where('id', $id)->update($changes);
        }
    }

    public function down(): void
    {
        // Pôvodný čas a stav sa vrátiť nedá – len označenie správ.
        DB::table('conversation_messages')
            ->where('kind', 'mailing.reply')
            ->update(['source' => 'project']);
    }
};

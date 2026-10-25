<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast after a successful Builder::persist() so every other browser
 * tab with this form's builder open finds out immediately, instead of
 * silently having their next autosave clobber it (fields are deleted and
 * recreated wholesale on every save -- there's no per-field merge). This
 * doesn't attempt to merge changes; it surfaces a "reload to see the
 * latest version" prompt, the same non-destructive stance Dot.Sheet and
 * Dot.Press take on a conflicting save.
 */
class FormBuilderUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $formId,
        public int $editedByUserId,
        public string $editedByName,
    ) {}

    /**
     * Without a real broadcaster (the "null" and "log" drivers: production has
     * no websocket server) there is nobody to tell, so do not queue a
     * broadcast job at all. It would be a write on the single SQLite file for
     * every builder save, picked up by the cron worker only to be discarded.
     */
    public function broadcastWhen(): bool
    {
        return ! in_array(config('broadcasting.default'), [null, 'null', 'log'], true);
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('form-builder.'.$this->formId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'builder.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'form_id' => $this->formId,
            'user_id' => $this->editedByUserId,
            'user_name' => $this->editedByName,
            'timestamp' => now()->toISOString(),
        ];
    }
}

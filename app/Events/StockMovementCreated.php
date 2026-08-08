<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockMovementCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $movement;
    public $warehouseId;

    public function __construct($movement, $warehouseId)
    {
        $this->movement = $movement;
        $this->warehouseId = $warehouseId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('warehouse.' . $this->warehouseId);
    }

    public function broadcastWith()
    {
        return [
            'movement_id' => $this->movement->id,
            'product_id' => $this->movement->product_id,
            'movement_type' => $this->movement->movement_type,
            'quantity' => $this->movement->quantity,
            'reference' => $this->movement->reference,
            'notes' => $this->movement->notes,
            'created_at' => $this->movement->created_at->format('Y-m-d H:i:s'),
            'message' => $this->movement->movement_type === 'in' 
                ? 'تم إيداع ' . $this->movement->quantity . ' وحدة من المنتج'
                : 'تم صرف ' . $this->movement->quantity . ' وحدة من المنتج',
        ];
    }
}

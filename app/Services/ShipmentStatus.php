<?php

namespace App\Services;

enum ShipmentStatus: string
{
    case Received = 'received';
    case PickedUp = 'picked_up';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Exception = 'exception';
}

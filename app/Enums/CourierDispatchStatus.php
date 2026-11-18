<?php

namespace App\Enums;

enum CourierDispatchStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Assigned = 'assigned';
    case Failed = 'failed';
}

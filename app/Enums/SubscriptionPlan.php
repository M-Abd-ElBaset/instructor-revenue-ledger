<?php

namespace App\Enums;

enum SubscriptionPlan: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Annual = 'annual';
}

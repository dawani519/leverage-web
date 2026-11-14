<?php

namespace App\Enums;

enum UserRole: string
{
    case User = 'user';
    case Broker = 'broker';
    case Admin = 'admin';
}

<?php

namespace App\Enums;

enum AuthProviderType: string
{
    case Google = 'google';
    case Credentials = 'credentials';
}

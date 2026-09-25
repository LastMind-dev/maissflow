<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    protected function requireAutomationManager(Request $request): void
    {
        abort_unless($request->user()?->canManageAutomations(), 403, 'Você não possui permissão para esta operação.');
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Static contact page. The legacy form was retired in favour of a Telegram-first
 * support flow — visitors now message the bot, which opens a conversation that
 * the admin handles in /admin/messages or directly in Telegram.
 */
class ContactController extends Controller
{
    public function show(): View
    {
        return view('pages.contact', ['pageTitle' => 'تماس با ما']);
    }
}

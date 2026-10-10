<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeContentController extends Controller
{
    /** Form field (underscored) => [setting key (dotted), max length]. */
    private const FIELDS = [
        'home_hero_badge' => ['home.hero_badge', 80],
        'home_hero_title' => ['home.hero_title', 120],
        'home_hero_title_accent' => ['home.hero_title_accent', 120],
        'home_hero_subtitle' => ['home.hero_subtitle', 300],
        'home_hero_cta_text' => ['home.hero_cta_text', 60],
        'home_hero_image' => ['home.hero_image', 300],
    ];

    public function edit(): View
    {
        return view('admin.settings.home', ['settings' => Setting::map()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (self::FIELDS as $field => [$key, $max]) {
            $rules[$field] = ['nullable', 'string', "max:$max"];
        }
        $request->validate($rules);

        $values = [];
        foreach (self::FIELDS as $field => [$key, $max]) {
            $values[$key] = $request->input($field);
        }
        Setting::putMany($values);

        return back()->with('success', 'محتوای صفحه اصلی ذخیره شد.');
    }
}

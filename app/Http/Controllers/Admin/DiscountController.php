<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\DiscountRule;
use Illuminate\Contracts\View\View;

class DiscountController extends Controller
{
    public function index(): View
    {
        return view('admin.discounts.index', [
            'coupons' => Coupon::latest()->paginate(20, ['*'], 'coupons_page'),
            'rules' => DiscountRule::latest()->paginate(20, ['*'], 'rules_page'),
        ]);
    }
}

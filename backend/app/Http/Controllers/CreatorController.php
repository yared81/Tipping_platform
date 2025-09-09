<?php 

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Tip;

class CreatorController extends Controller
{
    /**
     * Public profile endpoint
     * GET /api/creator/{id}
     */
    public function show($id)
    {
        $creator = User::where('id', $id)
            ->where('role', 'creator')
            ->firstOrFail();

        return response()->json([
            'id'       => $creator->id,
            'name'     => $creator->name,
            'bio'      => $creator->bio,
            'avatar'   => $creator->avatar_url, // uses accessor from User model
            'currency' => 'ETB',
        ]);
    }

    /**
     * Creator analytics endpoint
     * GET /api/creator/analytics
     */
    public function analytics(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'creator') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $total_tips = Tip::where('creator_id', $user->id)->count();

        $total_amount = Tip::where('creator_id', $user->id)
            ->where('status', 'succeeded')
            ->sum('amount');

        $last_tip = Tip::where('creator_id', $user->id)
            ->where('status', 'succeeded')
            ->max('created_at');

        $top = Tip::select('tipper_id', DB::raw('SUM(amount) as total'))
            ->where('creator_id', $user->id)
            ->where('status', 'succeeded')
            ->groupBy('tipper_id')
            ->orderByDesc('total')
            ->first();

        $top_tipper = $top ? User::find($top->tipper_id)?->name : null;

        return response()->json([
            'total_tips'   => $total_tips,
            'total_amount' => number_format($total_amount, 2),
            'last_tip'     => $last_tip,
            'top_tipper'   => $top_tipper,
            'balance'      => number_format($user->balance, 2),
        ]);
    }
}

<?php
namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\GameKey;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

use Illuminate\Support\Facades\Response;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class CartController extends Controller
{


    public function add(Game $game)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login')->with('error', 'You must be logged in.');
        }

        $order = $user->orders()->firstOrCreate(['status' => 'pending'], ['total_price' => 0]);

        // Jei jau turi šį žaidimą, neleisti pridėti dar
        if ($order->items()->where('game_id', $game->id)->exists()) {
            return redirect()->back()->with('error', 'You already have this game in your cart.');
        }

        // Rezervuoti tik vieną raktą
        $key = GameKey::where('game_id', $game->id)
            ->where('used', false)
            ->where(function($q) {
                $q->whereNull('reserved_until')->orWhere('reserved_until', '<', now());
            })
            ->first();

        $totalKeys = GameKey::where('game_id', $game->id)->where('used', false)->count();
        $reservedNow = GameKey::where('game_id', $game->id)
            ->where('used', false)
            ->where('reserved', true)
            ->where('reserved_until', '>=', now())
            ->count();

        if (!$key) {
            if ($totalKeys > 0 && $reservedNow >= $totalKeys) {
                return redirect()->back()->with('error', 'All keys are currently reserved by other users. Please try again in a few minutes.');
            } else {
                return redirect()->back()->with('error', 'Sorry, there are no keys left for this game.');
            }
        }

        $key->reserved = true;
        $key->reserved_until = Carbon::now()->addMinutes(10);
        $key->user_id = $user->id;
        $key->save();


        $order->items()->create([
            'game_id' => $game->id,
            'quantity' => 1,
            'price' => $game->price
        ]);

        $order->total_price = $order->items->sum(fn($item) => $item->quantity * $item->price);
        $order->save();

        return redirect()->back()->with('success', 'Game reserved and added to your basket!');
    }


    public function checkout()
    {
        $order = Auth::user()->orders()->where('status', 'pending')->with('items.game')->first();

        if (!$order || $order->items->isEmpty()) {
            return redirect()->route('cart.view')->with('error', 'Cart is empty');
        }

        foreach ($order->items as $item) {
            $needed = $item->quantity;


            $totalAvailableKeys = GameKey::where('game_id', $item->game_id)
                ->where('used', false)
                ->count();


            $reservedByUser = GameKey::where('game_id', $item->game_id)
                ->where('used', false)
                ->where('reserved', true)
                ->where('reserved_until', '>=', now())
                ->where('user_id', Auth::id())
                ->count();


            $freelyAvailable = GameKey::where('game_id', $item->game_id)
                ->where('used', false)
                ->where(function ($q) {
                    $q->whereNull('reserved')
                        ->orWhere('reserved', false)
                        ->orWhere('reserved_until', '<', now());
                })
                ->count();

            $totalKeys = GameKey::where('game_id', $item->game_id)->count();

            if ($totalKeys < $needed) {
                return redirect()->route('cart.view')
                    ->with('error', 'Only ' . $totalKeys . ' key(s) exist for "' . $item->game->title . '". You are trying to buy more than available in stock.');
            }

            // ❌ Not enough free+reserved-by-you keys
            if (($reservedByUser + $freelyAvailable) < $needed) {
                return redirect()->route('cart.view')
                    ->with('error', 'Not enough free keys for "' . $item->game->title . '". Some keys may be temporarily reserved by others. Please wait or reduce quantity.');
            }
        }

        // Stripe logika
        Stripe::setApiKey(env('STRIPE_SECRET'));

        $lineItems = [];

        foreach ($order->items as $item) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => ['name' => $item->game->title],
                    'unit_amount' => $item->price * 100,
                ],
                'quantity' => $item->quantity,
            ];
        }

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => route('cart.success'),
            'cancel_url' => route('cart.view'),
        ]);

        return redirect($session->url);
    }

    public function success()
    {
        $order = Auth::user()->orders()->where('status', 'pending')->with('items')->first();

        if ($order) {
            $order->status = 'completed';
            $order->save();

            foreach ($order->items as $item) {
                $needed = $item->quantity;

                $reservedKey = GameKey::where('game_id', $item->game_id)
                    ->where('used', false)
                    ->where('reserved', true)
                    ->where('reserved_until', '>=', now())
                    ->where('user_id', Auth::id())
                    ->first();

                $keys = collect();
                if ($reservedKey) {
                    $keys->push($reservedKey);
                }

                $remaining = $needed - $keys->count();
                if ($remaining > 0) {
                    $extraKeys = GameKey::where('game_id', $item->game_id)
                        ->where('used', false)
                        ->where(function($q) {
                            $q->whereNull('reserved_until')->orWhere('reserved_until', '<', now());
                        })
                        ->take($remaining)
                        ->get();

                    $keys = $keys->merge($extraKeys);
                }

                foreach ($keys as $key) {
                    $key->user_id = Auth::id();
                    $key->used = true;
                    $key->reserved = false;
                    $key->reserved_until = null;
                    $key->save();
                }
            }
        }

        return view('pages.card.success');
    }

    public function view()
    {
        $order = Auth::user()
            ->orders()
            ->where('status', 'pending')
            ->with('items.game')
            ->first();

        return view('pages.card.view', compact('order'));
    }

    public function remove(Game $game)
    {
        $user = Auth::user();

        $order = $user->orders()->where('status', 'pending')->first();

        if (!$order) {
            return redirect()->back()->with('error', 'No active cart found.');
        }

        $item = $order->items()->where('game_id', $game->id)->first();

        if ($item) {
            // Remove the item from cart
            $item->delete();

            // Free one reserved key for this game
            GameKey::where('game_id', $game->id)
                ->where('reserved', true)
                ->where('reserved_until', '>=', now())
                ->where('used', false)
                ->limit(1)
                ->update([
                    'reserved' => false,
                    'reserved_until' => null
                ]);

            // Update total price
            $order->total_price = $order->items->sum(function ($item) {
                return $item->quantity * $item->price;
            });
            $order->save();
        }

        return redirect()->back()->with('success', 'Game removed from your cart.');
    }


    public function clear()
    {
        $order = Auth::user()->orders()->where('status', 'pending')->with('items')->first();

        if ($order) {
            $gameIds = $order->items->pluck('game_id');

            GameKey::whereIn('game_id', $gameIds)
                ->where('reserved', true)
                ->where('reserved_until', '>=', now())
                ->where('used', false)
                ->update([
                    'reserved' => false,
                    'reserved_until' => null
                ]);

            $order->items()->delete();
            $order->total_price = 0;
            $order->save();
        }

        return redirect()->route('cart.view')->with('success', 'Cart cleared successfully.');
    }

    public function update(Request $request, $itemId)
    {
        $item = Auth::user()
            ->orders()
            ->where('status', 'pending')
            ->first()
            ->items()
            ->where('id', $itemId)
            ->first();

        if ($item) {
            $item->quantity = max(1, (int) $request->input('quantity'));
            $item->save();

            $order = $item->order;
            $order->total_price = $order->items->sum(function ($item) {
                return $item->quantity * $item->price;
            });
            $order->save();


            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'new_total' => $order->total_price,
                ]);
            }


            return redirect()->back()->with('success', 'Quantity updated!');
        }

        if ($request->ajax()) {
            return response()->json(['success' => false]);
        }

        return redirect()->back()->with('error', 'Item not found.');
    }

    public function myKeys()
    {
        $keys = GameKey::where('user_id', Auth::id())->with('game')->get();
        return view('pages.keys', compact('keys'));
    }

    public function destroy($id)
    {
        $key = \App\Models\GameKey::findOrFail($id);


        if ($key->user_id !== auth()->id()) {
            abort(403);
        }

        $key->delete();

        return redirect()->route('user.keys')->with('success', 'Key deleted successfully.');
    }


    public function markKeyViewed($id)
    {
        $key = GameKey::where('id', $id)
            ->where('user_id', Auth::id())
            ->whereNull('viewed_at') // tik jei dar nematė
            ->first();

        if ($key) {
            $key->viewed_at = now();
            $key->save();
        }

        return Response::json(['success' => true]);
    }

    public function refund(Request $request, $id)
    {
        $key = GameKey::findOrFail($id);

        if ($key->user_id !== Auth::id()) {
            abort(403);
        }


        if ($key->viewed_at !== null) {
            return redirect()->route('user.keys')->with('error', 'Refund not allowed. This key was already viewed.');
        }


        $key->used = false;
        $key->user_id = null;
        $key->reserved = false;
        $key->reserved_until = null;
        $key->viewed_at = null;
        $key->save();

        return redirect()->route('user.keys')->with('success', 'Key successfully refunded and returned to stock.');
    }
}

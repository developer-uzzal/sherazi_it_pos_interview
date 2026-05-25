<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'items'       => 'required|array',
        ]);

        // $totalAmount = 0;

        // $order = Order::create([
        //     'customer_id'  => $request->customer_id,
        //     'total_amount' => 0,
        //     'status'       => 'pending',
        // ]);

        // foreach ($request->items as $item) {
        //     $product = Product::find($item['product_id']);

        //     if (!$product || $product->stock < $item['quantity']) {
        //         return response()->json(['error' => 'Product unavailable'], 422);
        //     }

        //     OrderItem::create([
        //         'order_id'   => $order->id,
        //         'product_id' => $item['product_id'],
        //         'quantity'   => $item['quantity'],
        //         'unit_price' => $product->price,
        //     ]);

        //     $product->decrement('stock', $item['quantity']);

        //     $totalAmount += $product->price * $item['quantity'];
        // }

        // $order->update(['total_amount' => $totalAmount]);


        $totalAmount = 0;

        /*
         * Wrap order creation in a database transaction to prevent partial
         * writes. If any part of the loop fails, all changes are rolled back.
         */
        $createdOrder = DB::transaction(function () use ($request, &$totalAmount) {
            $order = Order::create([
                'customer_id'  => $request->customer_id,
                'total_amount' => 0,
                'status'       => 'pending',
            ]);
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                if (!$product || $product->stock < $item['quantity']) {
                    throw new \Exception('Product unavailable');
                }
                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                    'unit_price' => $product->price,
                ]);
                $product->decrement('stock', $item['quantity']);
                $totalAmount += $product->price * $item['quantity'];
            }
            $order->update(['total_amount' => $totalAmount]);
            return $order;
        });

        // Invalidate caches related to dashboard and sales report
        Cache::forget('products_dashboard');
        for ($i = 1; $i <= 3; $i++) {
            Cache::forget("sales_report_page_{$i}");
        }

        return response()->json($createdOrder, 201);
    }

    public function index()
    {
        // $orders = Order::all();

        // $data = [];
        // foreach ($orders as $order) {
        //     $data[] = [
        //         'id'          => $order->id,
        //         'customer'    => $order->customer->name,
        //         'total'       => $order->total_amount,
        //         'status'      => $order->status,
        //         'items_count' => $order->items->count(),
        //         'created_at'  => $order->created_at,
        //     ];
        // }


        $page = (int)request()->get('page', 1);
        $cacheKey = "orders_page_{$page}";

        $data = Cache::remember($cacheKey, now()->addMinutes(10), function () {
            $orders = Order::with(['customer', 'items'])->paginate(15);
            return [
                'data'         => $orders->getCollection()->map(function (Order $order) {
                    return [
                        'id'          => $order->id,
                        'customer'    => optional($order->customer)->name,
                        'total'       => $order->total_amount,
                        'status'      => $order->status,
                        'items_count' => $order->items->count(),
                        'created_at'  => $order->created_at,
                    ];
                })->toArray(),
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'per_page'     => $orders->perPage(),
                'total'        => $orders->total(),
            ];
        });

        return response()->json($data);
    }

    public function filterByStatus(Request $request)
    {
        $status = $request->input('status');

        //$orders = DB::select("SELECT * FROM orders WHERE status = '$status'");
        //protect sql injection
        $orders = Order::where('status', $status)->get();

        return response()->json($orders);
    }
}

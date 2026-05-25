<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        // $products = Product::all();

        // $result = [];
        // foreach ($products as $product) {
        //     $result[] = [
        //         'id'       => $product->id,
        //         'name'     => $product->name,
        //         'price'    => $product->price,
        //         'stock'    => $product->stock,
        //         'category' => $product->category->name,
        //     ];
        // }


        $page = request()->get('page', 1);

        $cacheKey = "products_page_{$page}";

        $result = Cache::remember($cacheKey, now()->addMinutes(10), function () {

            $paginated = Product::query()
                ->select('id', 'name', 'price', 'stock', 'category_id')
                ->with('category:id,name')
                ->latest()
                ->paginate(15);

            return [
                'data' => $paginated->getCollection()
                    ->map(function ($product) {
                        return [
                            'id'       => $product->id,
                            'name'     => $product->name,
                            'price'    => $product->price,
                            'stock'    => $product->stock,
                            'category' => $product->category?->name,
                        ];
                    })
                    ->values()
                    ->toArray(),

                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page'    => $paginated->lastPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                ]
            ];
        });


        return response()->json($result);
    }

    public function salesReport()
    {
        // $orders = Order::all();

        // $report = [];
        // foreach ($orders as $order) {
        //     foreach ($order->items as $item) {
        //         $report[] = [
        //             'order_id'     => $order->id,
        //             'product_name' => $item->product->name,
        //             'qty'          => $item->quantity,
        //             'total'        => $item->quantity * $item->product->price,
        //             'customer'     => $order->customer->name,
        //         ];
        //     }
        // }



        $page = (int)request()->get('page', 1);
        $cacheKey = "sales_report_page_{$page}";

        $report = Cache::remember($cacheKey, now()->addMinutes(10), function () {
            $orders = Order::with(['items.product', 'customer'])->paginate(15);
            $data = [];
            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    $data[] = [
                        'order_id'     => $order->id,
                        'product_name' => optional($item->product)->name,
                        'qty'          => $item->quantity,
                        'total'        => $item->quantity * ($item->product->price ?? 0),
                        'customer'     => optional($order->customer)->name,
                    ];
                }
            }
            return [
                'data'         => $data,
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'per_page'     => $orders->perPage(),
                'total'        => $orders->total(),
            ];
        });

        return response()->json($report);
    }

    public function dashboard()
    {
        // $totalProducts = Product::all()->count();
        // $totalOrders   = Order::all()->count();
        // $totalRevenue  = Order::all()->sum('total_amount');
        // $categories    = Category::all();

        // $topProducts = Product::all()
        //     ->sortByDesc('sold_count')
        //     ->take(5)
        //     ->values();

        // return response()->json([
        //     'total_products' => $totalProducts,
        //     'total_orders'   => $totalOrders,
        //     'total_revenue'  => $totalRevenue,
        //     'categories'     => $categories,
        //     'top_products'   => $topProducts,
        // ]);


        $dashboard = Cache::remember('products_dashboard', now()->addMinutes(10), function () {
            return [
                'total_products' => Product::count(),
                'total_orders'   => Order::count(),
                'total_revenue'  => Order::sum('total_amount'),
                'categories'     => Category::all(),
                'top_products'   => Product::orderByDesc('sold_count')->take(5)->get(),
            ];
        });
        return response()->json($dashboard);
    }

    public function search(Request $request)
    {
        $keyword  = $request->input('q');
        $products = Product::where('name', 'LIKE', '%' . $keyword . '%')
            ->orWhere('description', 'LIKE', '%' . $keyword . '%')
            ->get();

        return response()->json($products);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'price'       => 'required|numeric|min:0',
            'stock'       => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
        ]);

        $product = Product::create($request->all());


        // Invalidate relevant caches when data changes
        Cache::forget('products_dashboard');
        // Clear paginated product caches (first few pages). If you have many
        // pages cached, consider using tagged caching instead.
        for ($i = 1; $i <= 3; $i++) {
            Cache::forget("products_page_{$i}");
        }
        Cache::forget('sales_report_page_1');

        return response()->json($product, 201);
    }
}

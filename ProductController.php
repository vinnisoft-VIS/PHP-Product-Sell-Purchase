<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Size;
use App\Models\Category;
use App\Models\UserDetail;
use App\Models\UserStore;
use App\Models\TrendingBrand;
use App\Models\TrendingSearch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    public function myProducts()
    {
        $products = Product::with('images')->where('user_id', Auth::id())->get();
        foreach ($products as $key => $product) {
            $products[$key]['brand_name'] = $product->brand->name;
            $products[$key]['color_name'] = $product->color->color_name;
            $products[$key]['condition_name'] = $product->condition->title;
            $products[$key]['subcategorytwo_name'] = optional($product->subcategoryTwo)->name;
            $sizes = [];
            foreach (json_decode($product->size_id) as $sizeKey => $size) {
                $value = Size::where('id', $size)->first();
                $sizes[$sizeKey] = $value ? $value->size : '';
            }
            $products[$key]['sizes'] = implode(',', $sizes);
        }

        return response()->json([
            'success' => true,
            'message' => 'My products list',
            'data'    => $products
        ]);
    }

    public function allProducts(Request $request)
    {
        $products = new Product;
        $products = $products->with('images')->where('status', 1);
        if ($request->sort) {
            $sortType = $request->sort;
            if ($sortType == 'new') {
                $products = $products->orderBy('id', 'desc');
            } elseif ($sortType == 'popular') {
                // $products = $products->orderBy('fav_count', 'desc');
            } elseif ($sortType == 'high') {
                $products = $products->orderBy('selling_price', 'desc');
            } elseif ($sortType == 'low') {
                $products = $products->orderBy('selling_price', 'asc');
            }
        }
        if ($request->brand) {
            $products = $products->whereIn('brand_id', $request->brand);
        }
        if ($request->condition) {
            $products = $products->whereIn('condition_id', $request->condition);
        }
        if ($request->color) {
            $products = $products->whereIn('color_id', $request->color);
        }
        if ($request->category_id) {
            $products = $products->where('category_id', $request->category_id);
        }
        if ($request->subcategory_id) {
            $products = $products->where('subcategory_id', $request->subcategory_id);
        }
        if ($request->subcategory_two_id) {
            $products = $products->where('subcategory_two_id', $request->subcategory_two_id);
        }
        if ($request->gender) {
            $products = $products->where('gender', $request->gender);
        }
        if ($request->price_start && $request->price_end) {
            $products = $products->where('selling_price', '>=', $request->price_start)
                                ->where('selling_price', '<=', $request->price_end);
        }
        if ($request->search) {
            $products = $products->where('name', 'like', $request->search.'%');
        }
        $products = $products->get();
        $productsArray = [];
        $count = 0;
        if ($request->size && $request->size_category) {
            foreach ($products as $key => $product) {
                $sizeCategories = $request->size_category;
                $requestedSizes = $request->size;
                foreach ($sizeCategories as $key => $sizeCategory) {
                    $productSizes = json_decode($product->size_id);
                    $requestedSize = $requestedSizes[$key];
                    $sizeArr = array_intersect($requestedSize, $productSizes);
                    if ($product->size_category_id == $sizeCategory && $sizeArr) {
                        $productsArray[$count] = $product;
                        $productsArray[$count]['brand_name'] = $product->brand->name;
                        $productsArray[$count]['color_name'] = $product->color->color_name;
                        $productsArray[$count]['condition_name'] = $product->condition->title;
                        $productsArray[$count]['subcategorytwo_name'] = optional($product->subcategoryTwo)->name;
                        $sizes = [];
                        foreach (json_decode($product->size_id) as $sizeKey => $size) {
                            $value = Size::where('id', $size)->first();
                            $sizes[$sizeKey] = $value ? $value->size : '';
                        }
                        $productsArray[$count]['sizes'] = implode(',', $sizes);
                        $productsArray[$count]['user'] = $product->user;
                    }
                }
                $count++;
            }
        } else {
            foreach ($products as $key => $product) {
                $products[$key]['brand_name'] = $product->brand->name;
                $products[$key]['color_name'] = $product->color->color_name;
                $products[$key]['condition_name'] = $product->condition->title;
                $products[$key]['subcategorytwo_name'] = optional($product->subcategoryTwo)->name;
                $sizes = [];
                foreach (json_decode($product->size_id) as $sizeKey => $size) {
                    $value = Size::where('id', $size)->first();
                    $sizes[$sizeKey] = $value ? $value->size : '';
                }
                $products[$key]['sizes'] = implode(',', $sizes);
                $products[$key]['user'] = $product->user;
            }
            $productsArray = $products;
        }

        return response()->json([
            'success' => true,
            'message' => "All Products List.",
            'data'    => $productsArray
        ]);
    }

    public function addProduct(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'               => 'required',
            'description'        => 'required',
            // 'category_id'        => 'required',
            // 'subcategory_id'     => 'required',
            // 'subcategory_two_id' => 'required',
            'gender'             => 'required',
            'brand_id'           => 'required',
            'color_id'           => 'required',
            'condition_id'       => 'required',
            'size_id'            => 'required',
            'size_category_id'   => 'required',
            'quantity'           => 'required',
            'selling_price'      => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()
            ]);
        }

        $nationalShippingCost = NULL;
        if ($request->national_shipping_cost > 0) {
            $nationalShippingCost = $request->national_shipping_cost;
        }
        $internationalShippingCost = NULL;
        if ($request->international_shipping_cost > 0) {
            $internationalShippingCost = $request->international_shipping_cost;
        }

        $sizes = json_decode($request->size_id, true);
        $product = Product::create([
            'user_id'            => Auth::id(),
            'name'               => $request->name,
            'description'        => $request->description,
            'category_id'        => $request->category_id,
            'subcategory_id'     => $request->subcategory_id,
            'subcategory_two_id' => $request->subcategory_two_id,
            'gender'             => $request->gender,
            'brand_id'           => $request->brand_id,
            'color_id'           => $request->color_id,
            'condition_id'       => $request->condition_id,
            'size_id'            => json_encode($sizes),
            'size_category_id'   => $request->size_category_id,
            'total_qty'          => $request->quantity,
            'selling_price'      => $request->selling_price,
            'original_price'     => $request->original_price ?? NULL,
            'status'             => 1,
            'national_shipping_cost'      => $nationalShippingCost,
            'international_shipping_cost' => $internationalShippingCost
        ]);

        $images = $request->file('image');
        foreach ($images as $key => $image) {
            $imageName = time().$key.'.'.$image->extension();
            $imagePath = storage_path('app/public') . '/product-images/';
            $image->move($imagePath, $imageName);

            ProductImage::create([
                'user_id'    => Auth::id(),
                'product_id' => $product->id,
                'image'      => '/product-images/'. $imageName
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product added successfully.',
            'data'    => $product
        ]);
    }

    public function productDetail($id)
    {
        $product = Product::with('images')->where('id', $id)->first();
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found.'
            ]);
        }

        $product['brand_name'] = $product->brand->name;
        $product['color_name'] = $product->color->color_name;
        $product['condition_name'] = $product->condition->title;
        $product['category_name'] = optional($product->category)->name;
        $product['subcategory_name'] = optional($product->subcategory)->name;
        $product['subcategorytwo_name'] = optional($product->subcategoryTwo)->name;
        $sizes = [];
        foreach (json_decode($product->size_id) as $sizeKey => $size) {
            $value = Size::where('id', $size)->first();
            $sizes[$sizeKey] = $value ? $value->size : '';
        }
        $product['sizes'] = implode(',', $sizes);

        return response()->json([
            'success' => true,
            'message' => 'Product Detail',
            'data'    => $product
        ]);
    }

    public function homeProducts()
    {
        $user = Auth::user();
        $categories = Category::where('status', 1)->get();
        if (count($categories) > 0) {
            $categories[count($categories)] = [
                "id"          => 0,
                "name"        => "All Products",
                "image"       => null,
                "image_path"  => env('APP_URL')."/images/Women-Category-Allproducts.jpg",
                "status"      => 1,
                "created_at"  => \Carbon\Carbon::now(),
                "updated_at"  => \Carbon\Carbon::now(),
            ];

        }

        $products = new Product;
        $products = $products->with('images')->where('status', 1);
        $products = $products->get();
        foreach ($products as $key => $product) {
            $products[$key]['brand_name'] = $product->brand->name;
            $products[$key]['color_name'] = $product->color->color_name;
            $products[$key]['condition_name'] = $product->condition->title;
            $products[$key]['category_name'] = optional($product->category)->name;
            $products[$key]['subcategory_name'] = optional($product->subcategory)->name;
            $products[$key]['subcategorytwo_name'] = optional($product->subcategoryTwo)->name;
            $sizes = [];
            foreach (json_decode($product->size_id) as $sizeKey => $size) {
                $value = Size::where('id', $size)->first();
                $sizes[$sizeKey] = $value ? $value->size : '';
            }
            $products[$key]['sizes'] = implode(',', $sizes);
            $products[$key]['user'] = $product->user;
            $products[$key]['user_store'] = UserStore::where('user_id', $product->user_id)->first();
        }

        $userGender = $user->gender ?? '';
        $userBrands = $user->brand_id ? json_decode($user->brand_id) : [];
        $homeProducts = Product::with('images')->where('gender', $userGender)->whereIn('brand_id', $userBrands)->take(10)->get();
        if ($user->gender && $user->brand_id && count($homeProducts) < 0) {
            $productsForYou = [];
            $count = 0;
            foreach ($homeProducts as $key => $homeProduct) {
                $userDetail = UserDetail::where('size_category_id', $homeProduct->size_category_id)->first();
                if ($userDetail) {
                    $homeProductSizes = json_decode($homeProduct->size_id);
                    $userSizes = json_decode($userDetail->size_id);
                    $sizeArr = array_intersect($userSizes, $homeProductSizes);
                    if ($sizeArr) {
                        $productsForYou[$count] = $homeProduct;
                        $productsForYou[$count]['brand_name'] = $homeProduct->brand->name;
                        $productsForYou[$count]['color_name'] = $homeProduct->color->color_name;
                        $productsForYou[$count]['condition_name'] = $homeProduct->condition->title;
                        $productsForYou[$count]['category_name'] = optional($product->category)->name;
                        $productsForYou[$count]['subcategory_name'] = optional($product->subcategory)->name;
                        $productsForYou[$count]['subcategorytwo_name'] = optional($homeProduct->subcategoryTwo)->name;
                        $sizes = [];
                        foreach (json_decode($homeProduct->size_id) as $sizeKey => $size) {
                            $value = Size::where('id', $size)->first();
                            $sizes[$sizeKey] = $value ? $value->size : '';
                        }
                        $productsForYou[$count]['sizes'] = implode(',', $sizes);
                        $productsForYou[$count]['user'] = $homeProduct->user;
                        $productsForYou[$count]['user_store'] = UserStore::where('user_id', $product->user_id)->first();
                        $count++;
                    }
                }
            }
        } else {
            $homeProducts = Product::with('images')->orderBy('id', 'desc')->take(10)->get();
            $productsForYou = [];
            $count = 0;
            foreach ($homeProducts as $key => $homeProduct) {
                $productsForYou[$count] = $homeProduct;
                $productsForYou[$count]['brand_name'] = $homeProduct->brand->name;
                $productsForYou[$count]['color_name'] = $homeProduct->color->color_name;
                $productsForYou[$count]['condition_name'] = $homeProduct->condition->title;
                $productsForYou[$count]['category_name'] = optional($product->category)->name;
                $productsForYou[$count]['subcategory_name'] = optional($product->subcategory)->name;
                $productsForYou[$count]['subcategorytwo_name'] = optional($homeProduct->subcategoryTwo)->name;
                $sizes = [];
                foreach (json_decode($homeProduct->size_id) as $sizeKey => $size) {
                    $value = Size::where('id', $size)->first();
                    $sizes[$sizeKey] = $value ? $value->size : '';
                }
                $productsForYou[$count]['sizes'] = implode(',', $sizes);
                $productsForYou[$count]['user'] = $homeProduct->user;
                $productsForYou[$count]['user_store'] = UserStore::where('user_id', $product->user_id)->first();
                $count++;
            }
        }

        $newCollections = UserStore::where('user_id', '!=', Auth::id())
                                  ->orderBy('id', 'desc')
                                  ->take(10)->get();

        $data = [
            'categories'       => $categories,
            'products_for_you' => $productsForYou,
            'newsfeed'         => $products,
            'new_collections'  => $newCollections
        ];

        return response()->json([
            'success' => true,
            'message' => 'Home products data.',
            'data'    => $data
        ]);
    }

    public function getTrendingBrands()
    {
        $trendingBrands = TrendingBrand::all();
        foreach ($trendingBrands as $key => $trendingBrand) {
            $trendingBrands[$key]['brand_name'] = $trendingBrand->brand->name;
        }

        return response()->json([
            'success' => true,
            'message' => 'Trending Brands',
            'data'    => $trendingBrands
        ]);
    }

    public function getTrendingSearches()
    {
        $trendingSearches = TrendingSearch::latest()->get();
        foreach ($trendingSearches as $key => $trendingSearch) {
            if ($trendingSearch->type == 'brand') {
                $name = $trendingSearch->brand->name;
            } elseif ($trendingSearch->type == 'category') {
                $name = $trendingSearch->category->name;
            } elseif ($trendingSearch->type == 'subcategory') {
                $name = $trendingSearch->subcategory->name;
            } else {
                $name = $trendingSearch->subcategorytwo->name;
            }
            $trendingSearches[$key]['trending_search'] = $name;
        }

        return response()->json([
            'success' => true,
            'message' => 'Trending Searches',
            'data'    => $trendingSearches
        ]);
    }
}

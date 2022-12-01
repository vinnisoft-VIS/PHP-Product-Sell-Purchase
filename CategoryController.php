<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\GenderCategory;
use App\Models\Subcategory;
use App\Models\Subcategorytwo;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    public function getCategories(Request $request)
    {
        $i = 0;
        $categoriesArr[$i++] = [
            "id"          => 0,
            "category_id" => 0,
            "type"        => "all",
            "image"       => null,
            "image_path"  => "http://dupli.appdeft.biz/storage/categories/1666943111.jpg",
            "created_at"  => \Carbon\Carbon::now(),
            "updated_at"  => \Carbon\Carbon::now(),
            "name"        => "All Products",
            "is_next"     => 0
        ];
        if ($request->type) {
            $genderCategories = GenderCategory::where('type', $request->type)->get();
            foreach ($genderCategories as $key => $genderCategory) {
                $genderCategories[$key]['name'] = $genderCategory->category->name;
                $isNext = 0;
                $subcategory = Subcategory::where('type', $request->type)->where('category_id', $genderCategory->category_id)->first();
                if ($subcategory) {
                    $isNext = 1;
                }
                $genderCategories[$key]['is_next'] = $isNext;
                $categoriesArr[$i++] = $genderCategory;
            }
            // $categories = $categoriesArr;
            $categoriesArr[0]['name'] = "All Products ". ucwords($request->type);
            $categoriesArr[0]['image_path'] = env('APP_URL').'/images/'.ucwords($request->type)."-Category-Allproducts.jpg";
        } else {
            $categories = Category::where('status', 1)->get();
            foreach ($categories as $category) {
                $categoriesArr[$i++] = $category;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Categories List',
            'data'    => $categoriesArr
        ]);
    }

    public function getSubcategories(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'category_id' => 'required',
            'type'        => 'required'
        ]);

        if ($validation->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validation->errors()
            ]);
        }

        $category = Category::where('id', $request->category_id)->first();
        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found!'
            ]);
        }

        $subcategories = Subcategory::where('category_id', $request->category_id)->where('type', $request->type)->get();
        foreach ($subcategories as $key => $subcategory) {
            $isNext = 0;
            $subcategoryTwo = Subcategorytwo::where('category_id', $request->category_id)
                                            ->where('subcategory_id', $subcategory->id)
                                            ->where('type', $request->type)->first();
            if ($subcategoryTwo) {
                $isNext = 1;
            }
            $subcategories[$key]['is_next'] = $isNext;
        }

        return response()->json([
            'success' => true,
            'message' => 'Subcategories list.',
            'data'    => $subcategories
        ]);
    }

    public function getSubcategoriesTwo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id'    => 'required',
            'subcategory_id' => 'required',
            'type'           => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()
            ]);
        }

        $category = Category::where('id', $request->category_id)->first();
        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found!'
            ]);
        }

        $subcategory = Subcategory::where('id', $request->subcategory_id)->first();
        if (!$subcategory) {
            return response()->json([
                'success' => false,
                'message' => 'Subcategory not found!'
            ]);
        }

        $subcategoriestwo = Subcategorytwo::where('category_id', $request->category_id)
                                          ->where('subcategory_id', $request->subcategory_id)
                                          ->where('type', $request->type)->get();

        return response()->json([
            'success' => true,
            'message' => 'Subcategories Two list.',
            'data'    => $subcategoriestwo
        ]);
    }
}

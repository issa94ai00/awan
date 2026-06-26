<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SpecialOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;

class SpecialOfferController extends Controller
{
    /**
     * Display a listing of the resource for admin panel.
     */
    public function index(Request $request): JsonResponse
    {
        $offers = SpecialOffer::with('product')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $offers
        ]);
    }

    /**
     * Display active offers for public frontend.
     */
    public function activeOffers(): JsonResponse
    {
        \Log::info('API SpecialOfferController activeOffers method called');
        $today = now()->toDateString();
        
        $offers = SpecialOffer::with('product')
            ->where('is_active', true)
            ->where(function ($query) use ($today) {
                $query->whereNull('start_date')
                      ->orWhere('start_date', '<=', $today);
            })
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')
                      ->orWhere('end_date', '>=', $today);
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($offer) {
                return [
                    'id'                  => $offer->id,
                    'title_ar'            => $offer->title_ar,
                    'title_en'            => $offer->title_en,
                    'description_ar'      => $offer->description_ar,
                    'description_en'      => $offer->description_en,
                    'discount_percentage' => $offer->discount_percentage,
                    'link'                => $offer->link,
                    'start_date'          => $offer->start_date,
                    'end_date'            => $offer->end_date,
                    'is_active'           => $offer->is_active,
                    // Return full absolute URL so frontend doesn't need to guess
                    'image'               => image_url($offer->image),
                    'product'             => $offer->product ? [
                        'id'      => $offer->product->id,
                        'name_ar' => $offer->product->name_ar,
                        'name_en' => $offer->product->name_en,
                        'slug'    => $offer->product->slug,
                    ] : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $offers
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title_ar' => 'required|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'description_ar' => 'required|string',
            'description_en' => 'nullable|string',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'product_id' => 'nullable|exists:products,id',
            'link' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'sometimes|boolean',
            'image_file' => 'nullable|image|max:3072', // Max 3MB
        ]);

        $data = $request->except(['image_file', 'is_active']);
        $data['is_active'] = $request->input('is_active') === 'true' || $request->input('is_active') === '1' || $request->input('is_active') === true;

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('special-offers', 'public');
            $data['image'] = $path;
        }

        $offer = SpecialOffer::create($data);
        $offer->load('product');

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة العرض بنجاح',
            'data' => $offer
        ], 210);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $offer = SpecialOffer::findOrFail($id);

        $validated = $request->validate([
            'title_ar' => 'required|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'description_ar' => 'required|string',
            'description_en' => 'nullable|string',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'product_id' => 'nullable|exists:products,id',
            'link' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'sometimes|boolean',
            'image_file' => 'nullable|image|max:3072', // Max 3MB
        ]);

        $data = $request->except(['image_file', 'is_active']);
        $data['is_active'] = $request->input('is_active') === 'true' || $request->input('is_active') === '1' || $request->input('is_active') === true;

        if ($request->hasFile('image_file')) {
            // Delete old image if exists
            if ($offer->image) {
                Storage::disk('public')->delete($offer->image);
            }
            $path = $request->file('image_file')->store('special-offers', 'public');
            $data['image'] = $path;
        }

        $offer->update($data);
        $offer->load('product');

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث العرض بنجاح',
            'data' => $offer
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        $offer = SpecialOffer::findOrFail($id);

        if ($offer->image) {
            Storage::disk('public')->delete($offer->image);
        }

        $offer->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف العرض بنجاح'
        ]);
    }

    /**
     * Toggle active status.
     */
    public function toggleStatus($id): JsonResponse
    {
        $offer = SpecialOffer::findOrFail($id);
        $offer->is_active = !$offer->is_active;
        $offer->save();

        return response()->json([
            'success' => true,
            'message' => $offer->is_active ? 'تم تفعيل العرض' : 'تم تعطيل العرض',
            'data' => [
                'is_active' => $offer->is_active
            ]
        ]);
    }
}

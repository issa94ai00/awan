<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    /**
     * Upload image file
     */
    public function upload(Request $request): JsonResponse
    {
        // Accept either 'file', 'main_image', or 'gallery_image'
        $file = $request->file('file') ?? $request->file('main_image') ?? $request->file('gallery_image');

        if (!$file) {
            return response()->json([
                'success' => false,
                'message' => 'No file uploaded',
                'data' => null
            ], 400);
        }

        $request->validate([
            'file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'main_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'gallery_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
        ]);

        $extension = $file->getClientOriginalExtension();
        $slug = $request->input('slug');

        if ($slug) {
            $fileName = $slug . '.' . $extension;
        } else {
            $fileName = time() . '_' . $file->getClientOriginalName();
        }

        // Store file in public storage
        $path = $file->storeAs('uploads', $fileName, 'public');

        // Return the path without /storage prefix since it will be added by asset() helper
        return response()->json([
            'success' => true,
            'message' => 'File uploaded successfully',
            'data' => [
                'url' => $path,
                'path' => $path
            ]
        ]);
    }

    /**
     * Delete image file
     */
    public function delete(Request $request): JsonResponse
    {
        $request->validate([
            'path' => 'required|string'
        ]);

        $path = str_replace('/storage/', '', $request->path);
        
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
            
            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully',
                'data' => null
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'File not found',
            'data' => null
        ], 404);
    }
}

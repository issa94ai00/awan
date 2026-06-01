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
        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            
            // Store file in public storage
            $path = $file->storeAs('uploads', $fileName, 'public');
            
            $url = Storage::url($path);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => [
                    'url' => $url,
                    'path' => $path
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No file uploaded',
            'data' => null
        ], 400);
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

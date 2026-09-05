<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use App\Support\ImageStore;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    /**
     * Upload image file
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp,avif,bmp|max:5120',
            'main_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp,avif,bmp|max:5120',
            'gallery_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp,avif,bmp|max:5120',
            'slug' => 'nullable|string|max:255',
        ]);

        // Accept either 'file', 'main_image', or 'gallery_image'
        $file = $request->file('file') ?? $request->file('main_image') ?? $request->file('gallery_image');

        if (!$file) {
            return response()->json([
                'success' => false,
                'message' => 'No file uploaded',
                'data' => null
            ], 400);
        }

        // The name used to be just "{slug}.{ext}", which made every upload for
        // one product collide: a gallery photo overwrote the main image, each
        // gallery photo overwrote the one before it, and replacing an image
        // reused the exact same URL so browsers kept serving the old picture.
        // The slug stays in the name for readability; the suffix makes it unique.
        $extension = strtolower($file->getClientOriginalExtension())
            ?: strtolower((string) $file->guessExtension())
            ?: 'jpg';

        $slug = Str::slug((string) $request->input('slug'))
            ?: Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
            ?: 'image';

        $fileName = Str::limit($slug, 60, '').'-'.now()->format('Ymd').'-'.Str::lower(Str::random(8)).'.'.$extension;

        // Store file in public storage
        $path = $file->storeAs('uploads', $fileName, 'public');

        // `url`/`path` stay relative — that is the form the database holds.
        // `full_url` is the browsable one, for previewing what was just sent.
        return response()->json([
            'success' => true,
            'message' => 'File uploaded successfully',
            'data' => [
                'url' => $path,
                'path' => $path,
                'full_url' => image_url($path),
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
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

        // image_path() normalises whatever the client sends (absolute URL,
        // /storage/... path, bare path) and refuses anything containing "..".
        $path = image_path($request->input('path'));

        // Only files this application wrote may be removed: everything else
        // under the public disk (and everything shipped in public/) is off
        // limits.
        if (!$path || !ImageStore::isManaged($path)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid file path',
                'data' => null
            ], 422);
        }

        // A picture that a product, category, offer or past order still shows
        // is not an orphan, however the caller reached this endpoint.
        if (ImageStore::isReferenced($path)) {
            return response()->json([
                'success' => false,
                'message' => 'File is still in use by a saved record',
                'data' => null
            ], 409);
        }

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

<?php

namespace App\Http\Controllers;

use App\Models\Explore;
use App\Services\CloudinaryService;
use Cloudinary\Api\Upload\UploadApi;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExploreController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $explore = Explore::all();

        return response()->json([
            'message' => 'Explore items retrieved successfully',
            'data' => $explore,
        ], 200);
    }

    public function getFilter()
    {
        $explore = Explore::all();
        
        $filters = $explore->pluck('filter') 
            ->filter() 
            ->map(function($item) {
                return array_map('trim', explode(',', $item)); 
            })
            ->flatten()
            ->unique()
            ->values(); 

        return response()->json([
            'message' => 'Explore filters retrieved successfully',
            'filters' => $filters,
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:200',
            'web_link' => 'required|string',
            'filter' => 'nullable|string',
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        try {
            if (!$request->hasFile('image') || !$request->file('image')->isValid()) {
                return response()->json(['error' => 'No valid image uploaded.'], 422);
            }

            $file = $request->file('image');
            $filePath = $file->getRealPath();

            if (!$filePath) {
                return response()->json(['error' => 'Temporary file not found.'], 422);
            }

            // Upload to Cloudinary
            $cloudinaryService = app(CloudinaryService::class);
            $uploadedFileUrl = $cloudinaryService->upload($filePath, 'explore_images');

            if (!$uploadedFileUrl) {
                return response()->json(['error' => 'Cloudinary upload failed.'], 500);
            }

            $explore = Explore::create([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'web_link' => $validated['web_link'],
                'filter' => $validated['filter'] ?? null,
                'image' => $uploadedFileUrl,
            ]);

            return response()->json([
                'message' => 'Explore item created successfully',
                'data' => $explore,
            ], 201);

        } catch (\Throwable $e) {
            Log::error('Explore upload error: ' . $e->getMessage());

            return response()->json([
                'error' => 'The image failed to upload.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(Explore $explore)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Explore $explore)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
   public function update(Request $request, $id)
    {
        $explore = Explore::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string|max:200',
            'web_link' => 'sometimes|required|string',
            'filter' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        try {
            $cloudinaryService = app(\App\Services\CloudinaryService::class);

            // If a new image is uploaded
            if ($request->hasFile('image') && $request->file('image')->isValid()) {
                $file = $request->file('image');
                $filePath = $file->getRealPath();

                if (!$filePath) {
                    return response()->json(['error' => 'Temporary file not found.'], 422);
                }

                // Remove old image from Cloudinary if it exists
                if ($explore->image) {
                    $cloudinaryService->delete($explore->image, 'explore_images');
                }

                // Upload new image
                $uploadedFileUrl = $cloudinaryService->upload($filePath, 'explore_images');

                if (!$uploadedFileUrl) {
                    return response()->json(['error' => 'Cloudinary upload failed.'], 500);
                }

                $validated['image'] = $uploadedFileUrl;
            } else {
                // Keep the old image if none is uploaded
                $validated['image'] = $explore->image;
            }

            $explore->update($validated);
            $explore->refresh();

            return response()->json([
                'message' => 'Explore item updated successfully',
                'data' => $explore,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Explore update error: ' . $e->getMessage());

            return response()->json([
                'error' => 'The update failed.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $explore = Explore::findOrFail($id);
            $explore->delete();

            return response()->json([
                'message' => 'Explore item deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete explore item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}

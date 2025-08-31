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

        public function store(Request $request, CloudinaryService $cloudinary)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:200',
            'web_link' => 'required|string',
            'filter' => 'nullable|string',
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $uploadedFileUrl = $cloudinary->upload(
                $request->file('image')->getRealPath(),
                'explore_images'
            );
        } else {
            return response()->json(['error' => 'Image upload failed'], 422);
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
            'description' => 'sometimes|required|string|max:100',
            'web_link' => 'sometimes|required|string',
            'filter' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        if ($request->hasFile('image')) {
            if ($explore->image) {
                try {
                    $publicId = pathinfo(parse_url($explore->image, PHP_URL_PATH), PATHINFO_FILENAME);
                    Cloudinary::destroy('explore_images/' . $publicId);
                } catch (\Exception $e) {
                    Log::warning("Failed to delete old Cloudinary image: " . $e->getMessage());
                }
            }

            $uploadedFileUrl = Cloudinary::upload(
                $request->file('image')->getRealPath(),
                ['folder' => 'explore_images']
            )->getSecurePath();

            $validated['image'] = $uploadedFileUrl;
        } else {
            $validated['image'] = $explore->image;
        }

        $explore->update($validated);
        $explore->refresh();

        return response()->json([
            'message' => 'Explore item updated successfully',
            'data' => $explore,
        ], 200);
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

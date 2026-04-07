<?php

namespace App\Http\Controllers;

use App\Models\Slide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class SlideController extends Controller
{
    public function listUserSlides(Request $request)
    {
        $userId = $request->user()->id;

        $slides = Slide::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($slide) {
                return [
                    'id' => $slide->id,
                    'file_path' => $slide->file_path,
                    'file_name' => basename($slide->file_path),
                    'uploaded_at' => $slide->created_at,
                ];
            });

        return response()->json([
            'slides' => $slides,
        ]);
    }

    public function uploadSlide(Request $request)
    {
        try {
            // Validate the file input
            $request->validate([
                'slide' => 'required|file|mimes:ppt,pptx,pdf,doc,docx,xls,xlsx,txt,csv|max:20480', // 20 MB max size
            ]);

            $userId = $request->user()->id; // Assuming the user is authenticated
            $userFolder = "slides/user_{$userId}";

            // Create a folder for the user if it doesn't exist.
            if (!Storage::disk('local')->exists($userFolder)) {
                Storage::disk('local')->makeDirectory($userFolder);
            }

            // Store the file in storage/app/private/slides/user_{id}
            $filePath = $request->file('slide')->storeAs($userFolder, $request->file('slide')->getClientOriginalName(), 'local');

            // Save the slide information to the database
            Slide::create([
                'user_id' => $userId,
                'file_path' => $filePath,
            ]);

            return response()->json([
                'message' => 'Slide uploaded successfully.',
                'file_path' => $filePath,
            ], 201);

        } catch (Exception $e) {
            // Log the error to the Laravel log file
            Log::error('Slide upload error: ' . $e->getMessage());

            // Return an error response to the client
            return response()->json([
                'message' => 'An error occurred while uploading the slide.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteSlide(Request $request, $slideId)
    {
        try {
            $userId = $request->user()->id;
            $slide = Slide::where('id', $slideId)
                ->where('user_id', $userId)
                ->first();

            if (!$slide) {
                return response()->json([
                    'message' => 'Slide not found.',
                ], 404);
            }

            $fileName = basename($slide->file_path);
            $legacyPath = "slides/user_{$userId}/{$fileName}";
            $nestedPrivatePath = "private/slides/user_{$userId}/{$fileName}";

            // Delete whichever location exists (legacy or current).
            if (!empty($slide->file_path) && Storage::disk('local')->exists($slide->file_path)) {
                Storage::disk('local')->delete($slide->file_path);
            }
            if (Storage::disk('local')->exists($legacyPath)) {
                Storage::disk('local')->delete($legacyPath);
            }
            if (Storage::disk('local')->exists($nestedPrivatePath)) {
                Storage::disk('local')->delete($nestedPrivatePath);
            }

            $slide->delete();

            return response()->json([
                'message' => 'Slide deleted successfully.',
            ]);
        } catch (Exception $e) {
            Log::error('Slide delete error: ' . $e->getMessage());
            return response()->json([
                'message' => 'An error occurred while deleting the slide.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

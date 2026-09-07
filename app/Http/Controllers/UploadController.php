<?php

namespace App\Http\Controllers;

use App\Models\Upload;
use App\Services\WatermarkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class UploadController extends Controller
{
    public function index(): View
    {
        $uploads = Schema::hasTable('uploads') ? Upload::query()->latest()->limit(25)->get() : collect();

        return view('welcome', ['uploads' => $uploads]);
    }

    public function store(Request $request, WatermarkService $watermarkService): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'file' => ['required', 'file', 'max:51200', 'mimes:jpg,jpeg,png,gif,webp,pdf,xlsx,xls,ods,csv'],
        ]);
        $file = $request->file('file');
        $originalName = basename($file->getClientOriginalName());
        $previous = Upload::query()->where('original_name', $originalName)->latest()->first();

        try {
            $result = $watermarkService->create($file, $validated['user_id'], $previous);
            $storedPath = 'watermarked/'.now()->format('Y/m').'/'.str()->uuid().'.'.$result['extension'];
            Storage::disk('local')->put($storedPath, file_get_contents($result['path']));
            @unlink($result['path']);
            Upload::create(['original_name' => $originalName, 'stored_path' => $storedPath, 'mime_type' => $result['mime'], 'extension' => $result['extension'], 'uploaded_by_user_id' => $validated['user_id'], 'watermark_ids' => $result['watermark_ids']]);
        } catch (Throwable $exception) {
            report($exception);
            return back()->withInput()->withErrors(['file' => 'The file could not be watermarked. Check its format and try again.']);
        }
        return to_route('uploads.index')->with('success', 'Watermark added. The processed file is ready to download.');
    }

    public function download(Upload $upload)
    {
        abort_if(empty($upload->watermark_ids) || !Storage::disk('local')->exists($upload->stored_path), 404);
        return response()->download(
            Storage::disk('local')->path($upload->stored_path),
            $upload->original_name,
            ['Content-Type' => $upload->mime_type],
        );
    }
}
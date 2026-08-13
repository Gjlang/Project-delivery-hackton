<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index()
    {
        $categories = config('document_categories');

        $documents = Document::latest()->get();

        $counts = $documents->groupBy('category');

        foreach ($categories as $key => &$category) {
            $categoryDocs = $counts->get($key, collect());
            $category['key'] = $key;
            $category['total'] = $categoryDocs->count();
            $category['indexed'] = $categoryDocs->where('status', 'indexed')->count();
        }
        unset($category);

        return view('company-knowledge.index', [
            'categories' => $categories,
            'documents' => $documents,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|in:'.implode(',', array_keys(config('document_categories'))),
            'file' => 'required|file|mimes:pdf,doc,docx,txt,md|max:20480',
        ]);

        $file = $request->file('file');
        $path = $file->store('documents', 'public');

        $document = Document::create([
            'category' => $validated['category'],
            'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'original_filename' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'version' => '1.0',
            'status' => 'indexed',
            'uploaded_by' => $request->user()->id,
        ]);

        return redirect()->route('company-knowledge.index')
            ->with('status', "\"{$document->original_filename}\" uploaded to ".config("document_categories.{$document->category}.label").'.');
    }

    public function show(Document $document)
    {
        return response()->json([
            'id' => $document->id,
            'title' => $document->title,
            'category' => config("document_categories.{$document->category}.label"),
            'version' => $document->version,
            'status' => $document->status,
            'extracted_summary' => $document->extracted_summary,
            'extracted_sections' => $document->extracted_sections,
            'date' => $document->created_at->format('M d, Y'),
        ]);
    }

    public function reindex(Document $document)
    {
        $document->update(['status' => 'indexed']);

        return back()->with('status', "\"{$document->original_filename}\" re-indexed.");
    }

    public function destroy(Document $document)
    {
        Storage::disk('public')->delete($document->path);
        $document->delete();

        return back()->with('status', 'Document removed.');
    }
}

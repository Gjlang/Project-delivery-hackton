<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class KnowledgeBaseController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $categories = config('knowledge_rules');

        foreach ($categories as $prefix => &$meta) {
            $meta['count'] = $meta['model']::where('created_by', $userId)->count();
        }
        unset($meta);

        $documents = Document::where('uploaded_by', $userId)->latest()->take(20)->get();

        return view('knowledge-base.index', [
            'categories' => $categories,
            'documents' => $documents,
        ]);
    }

    /**
     * Thin proxy: parsing, rule extraction, MySQL writes, and embedding all
     * now live in the Python service (python-service/main.py) so that layer
     * can be reused by the LangGraph/LangChain orchestrator planned later.
     * Laravel just forwards the file and relays whatever comes back -- the
     * response shape (status/error/total_rules) is unchanged, so the
     * existing upload UI in knowledge-base/index.blade.php needs no changes.
     */
    public function upload(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx,txt,md|max:20480',
            'category' => 'required|string|in:'.implode(',', array_keys(config('knowledge_rules'))),
        ]);

        $file = $request->file('file');

        $response = Http::timeout(120)
            ->withHeaders(['X-API-Key' => config('services.python_indexer.api_key')])
            ->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
            ->post(config('services.python_indexer.base_url').'/documents/upload', [
                'category' => $validated['category'],
                'created_by' => $request->user()->id,
            ]);

        $data = $response->json() ?? [
            'status' => 'error',
            'error' => 'The indexing service did not respond.',
        ];

        if ($request->wantsJson()) {
            return response()->json($data, $response->successful() ? 200 : 502);
        }

        return back()->with('status', ($data['status'] ?? 'error') === 'success'
            ? "Stored {$data['total_rules']} rules."
            : ($data['error'] ?? 'Upload failed.'));
    }

    /**
     * Thin proxy, same pattern as upload(): the Python service owns the
     * stored file, the rule-table rows (source_document_id FK), and the
     * Qdrant vectors, so it does the actual cascading delete. Laravel's
     * `documents` row is deleted by Python too (same shared MySQL table
     * rule_repository.create_document() writes into).
     */
    public function destroy(Request $request, Document $document)
    {
        abort_unless($document->uploaded_by === $request->user()->id, 404);

        $response = Http::timeout(60)
            ->withHeaders(['X-API-Key' => config('services.python_indexer.api_key')])
            ->delete(config('services.python_indexer.base_url')."/documents/{$document->id}");

        if (! $response->successful()) {
            return response()->json(['status' => 'error', 'error' => 'Could not delete this document.'], 502);
        }

        return response()->json(['status' => 'deleted']);
    }
}

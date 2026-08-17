<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class KnowledgeBaseController extends Controller
{
    public function index()
    {
        $categories = config('knowledge_rules');

        foreach ($categories as $prefix => &$meta) {
            $meta['count'] = $meta['model']::count();
        }
        unset($meta);

        $documents = Document::latest()->take(20)->get();

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
}

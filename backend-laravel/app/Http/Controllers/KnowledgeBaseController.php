<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\DocumentParser;
use App\Services\Rules\KnowledgeRuleChunker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KnowledgeBaseController extends Controller
{
    public function __construct(
        private DocumentParser $parser,
        private KnowledgeRuleChunker $chunker,
    ) {
    }

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

    public function upload(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx,txt,md|max:20480',
            'category' => 'required|string|in:'.implode(',', array_keys(config('knowledge_rules'))),
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
            'status' => 'processing',
        ]);

        $result = $this->parseAndStore($document);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => $document->status,
                'filename' => $document->original_filename,
                'category' => $document->category,
                'error' => $document->parse_error,
                'total_rules' => array_sum($result),
                'categories' => $result,
            ]);
        }

        return back()->with('status', "\"{$document->original_filename}\" uploaded and processed.");
    }

    private function parseAndStore(Document $document): array
    {
        $stored = [];

        try {
            $absolutePath = Storage::disk('public')->path($document->path);
            $extension = pathinfo($document->original_filename, PATHINFO_EXTENSION);

            $text = $this->parser->extractText($absolutePath, $extension);

            $ruleTriggerWords = config('document_categories.rule_trigger_words', []);
            $analysis = $this->parser->analyze($text, [], $ruleTriggerWords);

            $grouped = $this->chunker->chunk($text);
            $categories = config('knowledge_rules');

            foreach ($grouped as $prefix => $rules) {
                $meta = $categories[$prefix] ?? null;

                if ($meta === null) {
                    continue;
                }

                foreach ($rules as $rule) {
                    $meta['model']::updateOrCreate(
                        ['rule_code' => $rule['code']],
                        [
                            'section' => $rule['section'],
                            'title' => $rule['title'],
                            'rule_text' => $rule['text'],
                            'sort_order' => $rule['sort_order'],
                            'source_document_id' => $document->id,
                        ]
                    );
                }

                $stored[$meta['label']] = count($rules);
            }

            $document->update([
                'status' => 'indexed',
                'extracted_sections' => $analysis['sections'],
                'extracted_rules' => $analysis['rules'],
                'keyword_hits' => $analysis['keyword_hits'],
                'word_count' => $analysis['word_count'],
                'parsed_text' => $analysis['text'] ?: null,
                'parse_error' => null,
            ]);
        } catch (\Throwable $e) {
            $document->update([
                'status' => 'error',
                'parse_error' => $e->getMessage(),
            ]);
        }

        return $stored;
    }
}

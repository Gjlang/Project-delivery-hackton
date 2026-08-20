<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Project;
use App\Services\DocumentParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function __construct(private DocumentParser $parser)
    {
    }

    public function index(Request $request, Project $project)
    {
        $this->authorizeProject($request, $project);

        $categories = config('document_categories.categories');

        $documents = $project->documents()->latest()->get();

        $counts = $documents->groupBy('category');

        foreach ($categories as $key => &$category) {
            $categoryDocs = $counts->get($key, collect());
            $category['key'] = $key;
            $category['total'] = $categoryDocs->count();
            $category['indexed'] = $categoryDocs->where('status', 'indexed')->count();
        }
        unset($category);

        return view('company-knowledge.index', [
            'project' => $project,
            'categories' => $categories,
            'documents' => $documents,
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $this->authorizeProject($request, $project);

        $validated = $request->validate([
            'category' => 'required|in:'.implode(',', array_keys(config('document_categories.categories'))),
            'file' => 'required|file|mimes:pdf,doc,docx,txt,md|max:20480',
        ]);

        $file = $request->file('file');
        $path = $file->store('documents', 'public');

        $document = Document::create([
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'category' => $validated['category'],
            'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'original_filename' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'version' => '1.0',
            'status' => 'processing',
            'uploaded_by' => $request->user()->id,
        ]);

        $this->parseAndStore($document);

        return redirect()->route('projects.company-knowledge.index', $project)
            ->with('status', "\"{$document->original_filename}\" uploaded to ".config("document_categories.categories.{$document->category}.label").'.');
    }

    public function library(Request $request, Project $project)
    {
        $this->authorizeProject($request, $project);

        $query = $project->documents();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('original_filename', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $documents = $query->latest()->paginate(15)->withQueryString();

        return view('company-knowledge.library', [
            'project' => $project,
            'documents' => $documents,
            'categories' => config('document_categories.categories'),
            'filters' => $request->only(['search', 'category', 'status']),
        ]);
    }

    /**
     * Aggregate view: every processed document pooled by category, with its
     * key points and full parsed text, for reviewing what the AI has
     * actually extracted before it's used elsewhere in the system.
     */
    public function summary(Request $request, Project $project)
    {
        $this->authorizeProject($request, $project);

        $categories = config('document_categories.categories');

        $documentsByCategory = $project->documents()
            ->whereIn('status', ['indexed', 'error'])
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('category');

        foreach ($categories as $key => &$category) {
            $categoryDocs = $documentsByCategory->get($key, collect());
            $category['key'] = $key;
            $category['documents'] = $categoryDocs;
            $category['key_points'] = $categoryDocs
                ->flatMap(fn ($doc) => collect($doc->extracted_rules ?? [])->map(fn ($rule) => [
                    'text' => $rule,
                    'source' => $doc->original_filename,
                ]))
                ->values();
        }
        unset($category);

        return view('company-knowledge.summary', [
            'project' => $project,
            'categories' => $categories,
        ]);
    }

    public function show(Request $request, Project $project, Document $document)
    {
        $this->authorizeProjectDocument($request, $project, $document);

        return response()->json([
            'id' => $document->id,
            'title' => $document->title,
            'category' => $document->category,
            'category_label' => config("document_categories.categories.{$document->category}.label"),
            'original_filename' => $document->original_filename,
            'version' => $document->version,
            'status' => $document->status,
            'extracted_sections' => $document->extracted_sections,
            'extracted_rules' => $document->extracted_rules ?? [],
            'keyword_hits' => $document->keyword_hits ?? [],
            'word_count' => $document->word_count,
            'parsed_text' => $document->parsed_text,
            'parse_error' => $document->parse_error,
            'date' => $document->created_at->format('M d, Y'),
        ]);
    }

    public function update(Request $request, Project $project, Document $document)
    {
        $this->authorizeProjectDocument($request, $project, $document);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|in:'.implode(',', array_keys(config('document_categories.categories'))),
            'version' => 'required|string|max:50',
            'file' => 'nullable|file|mimes:pdf,doc,docx,txt,md|max:20480',
        ]);

        $document->title = $validated['title'];
        $document->category = $validated['category'];
        $document->version = $validated['version'];

        $fileReplaced = $request->hasFile('file');

        if ($fileReplaced) {
            Storage::disk('public')->delete($document->path);

            $file = $request->file('file');
            $document->path = $file->store('documents', 'public');
            $document->original_filename = $file->getClientOriginalName();
            $document->mime_type = $file->getClientMimeType();
            $document->size = $file->getSize();
            $document->status = 'processing';
        }

        $document->save();

        // Category changes affect which keyword set applies, and a replaced
        // file needs fresh extraction either way, so always re-run detection.
        $this->parseAndStore($document);

        if ($request->wantsJson()) {
            return response()->json(['status' => 'ok']);
        }

        return back()->with('status', "\"{$document->original_filename}\" updated.");
    }

    public function reindex(Request $request, Project $project, Document $document)
    {
        $this->authorizeProjectDocument($request, $project, $document);

        $this->parseAndStore($document);

        return back()->with('status', "\"{$document->original_filename}\" re-indexed.");
    }

    public function destroy(Request $request, Project $project, Document $document)
    {
        $this->authorizeProjectDocument($request, $project, $document);

        Storage::disk('public')->delete($document->path);
        $document->delete();

        return back()->with('status', 'Document removed.');
    }

    /**
     * A project is only accessible by the user who created it; block access
     * even if someone guesses another user's project id.
     */
    private function authorizeProject(Request $request, Project $project): void
    {
        abort_unless($project->created_by === $request->user()->id, 404);
    }

    /**
     * A document must belong to both the project it's addressed through and
     * that project's company, so a document can't be reached via a
     * mismatched project URL either.
     */
    private function authorizeProjectDocument(Request $request, Project $project, Document $document): void
    {
        $this->authorizeProject($request, $project);

        abort_unless($document->project_id === $project->id, 404);
    }

    /**
     * Run the extraction + word/rule detection pipeline for a document and
     * persist the results. Parsing failures degrade gracefully: the upload
     * itself is never lost, only the extracted metadata is left empty.
     */
    private function parseAndStore(Document $document): void
    {
        try {
            $absolutePath = Storage::disk('public')->path($document->path);
            $extension = pathinfo($document->original_filename, PATHINFO_EXTENSION);

            $text = $this->parser->extractText($absolutePath, $extension);

            $categoryKeywords = config("document_categories.categories.{$document->category}.keywords", []);
            $ruleTriggerWords = config('document_categories.rule_trigger_words', []);

            $analysis = $this->parser->analyze($text, $categoryKeywords, $ruleTriggerWords);

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
    }
}

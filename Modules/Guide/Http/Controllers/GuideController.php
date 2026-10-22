<?php

namespace Modules\Guide\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Guide\Entities\GuideVideo;
use Modules\Guide\Entities\GuideCategory;
use Modules\Guide\Entities\GuideDocument;

class GuideController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'XSS']);
    }

    public function index()
    {
        $categories = GuideCategory::with(['videos', 'documents'])->orderBy('order')->get();
        $videos = GuideVideo::orderBy('category_id')->orderBy('order')->get();
        $documents = GuideDocument::orderBy('category_id')->orderBy('order')->get();

        return view('guide::index', compact('categories', 'videos', 'documents'));
    }

    public function videos()
    {
        $categories = GuideCategory::with('videos')->orderBy('order')->get();
        $videos = GuideVideo::with('category')->orderBy('order')->get();

        return view('guide::videos.index', compact('categories', 'videos'));
    }

    public function showVideo($id)
    {
        $video = GuideVideo::with('category')->findOrFail($id);
        $relatedVideos = GuideVideo::where('category_id', $video->category_id)
            ->where('id', '!=', $id)
            ->limit(5)
            ->get();

        return view('guide::videos.show', compact('video', 'relatedVideos'));
    }

    public function documentation()
    {
        $categories = GuideCategory::with('documents')->orderBy('order')->get();
        $documents = GuideDocument::with('category')->orderBy('order')->get();

        return view('guide::documentation.index', compact('categories', 'documents'));
    }

    public function showDocument($id)
    {
        $document = GuideDocument::with('category')->findOrFail($id);
        $relatedDocs = GuideDocument::where('category_id', $document->category_id)
            ->where('id', '!=', $id)
            ->limit(5)
            ->get();

        return view('guide::documentation.show', compact('document', 'relatedDocs'));
    }

    public function category($slug)
    {
        $category = GuideCategory::where('slug', $slug)->firstOrFail();
        $videos = GuideVideo::where('category_id', $category->id)->orderBy('order')->get();
        $documents = GuideDocument::where('category_id', $category->id)->orderBy('order')->get();

        return view('guide::category', compact('category', 'videos', 'documents'));
    }

    public function search(Request $request)
    {
        $query = $request->get('q');

        $videos = GuideVideo::where('title', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->get();

        $documents = GuideDocument::where('title', 'like', "%{$query}%")
            ->orWhere('content', 'like', "%{$query}%")
            ->get();

        return view('guide::search', compact('videos', 'documents', 'query'));
    }

    public function chatbot()
    {
        return view('guide::chatbot.index');
    }
}

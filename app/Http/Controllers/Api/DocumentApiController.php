<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DucumentUpload;
use App\Models\Utility;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Two things share the word "document" in this ERP:
 *  - /documents         document *types* (Document) - what GET /documents has always returned
 *  - /document-uploads  the company's uploaded files (DucumentUpload, HRM > Document)
 * They have separate id spaces, so they get separate prefixes.
 */
class DocumentApiController extends Controller
{
    use ApiResponser;

    // ---------------------------------------------------------------
    // Document types
    // ---------------------------------------------------------------

    public function createDocumentType(Request $request)
    {
        if (!Auth::user()->can('create document type')) {
            return $this->error('Permission denied.', 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:20',
            'is_required' => 'required|boolean',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $document = new Document();
        $document->name = $request->name;
        $document->is_required = $request->boolean('is_required') ? 1 : 0;
        $document->created_by = Auth::user()->creatorId();
        $document->save();

        return $this->success(['document_type' => [
            'id' => $document->id,
            'name' => $document->name,
            'is_required' => (bool) $document->is_required,
        ]], 'Document type created.', 201);
    }

    public function deleteDocumentType($id)
    {
        if (!Auth::user()->can('delete document type')) {
            return $this->error('Permission denied.', 403);
        }

        $document = Document::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$document) {
            return $this->error('Document type not found.', 404);
        }
        $document->delete();

        return $this->success([], 'Document type deleted.');
    }

    // ---------------------------------------------------------------
    // Uploaded documents
    // ---------------------------------------------------------------

    public function getDocumentUploads()
    {
        if (!Auth::user()->can('manage document')) {
            return $this->error('Permission denied.', 403);
        }

        $documents = $this->visibleUploads()->orderBy('id', 'desc')->get()
            ->map(fn ($doc) => $this->formatUpload($doc));

        return $this->success(['documents' => $documents], 'Documents retrieved.');
    }

    public function getDocumentUpload($id)
    {
        if (!Auth::user()->can('manage document')) {
            return $this->error('Permission denied.', 403);
        }

        $document = $this->visibleUploads()->where('id', $id)->first();
        if (!$document) {
            return $this->error('Document not found.', 404);
        }

        return $this->success(['document' => $this->formatUpload($document)], 'Document retrieved.');
    }

    public function uploadDocument(Request $request)
    {
        if (!Auth::user()->can('create document')) {
            return $this->error('Permission denied.', 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'document' => 'required|file',
            'role' => 'nullable|integer',
            'description' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Same storage path and size/mime checks as the web upload (Utility::upload_file).
        $fileName = time() . '_' . $request->file('document')->getClientOriginalName();
        $path = Utility::upload_file($request, 'document', $fileName, 'uploads/documentUpload', []);
        if ($path['flag'] == 0) {
            return $this->error($path['msg'], 422);
        }

        $document = new DucumentUpload();
        $document->name = $request->name;
        $document->document = $fileName;
        $document->role = (int) $request->input('role', 0); // 0 = visible to all roles
        $document->description = $request->description;
        $document->created_by = Auth::user()->creatorId();
        $document->save();

        return $this->success(['document' => $this->formatUpload($document)], 'Document uploaded successfully.', 201);
    }

    public function deleteDocument($id)
    {
        if (!Auth::user()->can('delete document')) {
            return $this->error('Permission denied.', 403);
        }

        $document = DucumentUpload::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$document) {
            return $this->error('Document not found.', 404);
        }
        $document->delete();

        return $this->success([], 'Document deleted successfully.');
    }

    public function downloadDocument($id)
    {
        if (!Auth::user()->can('manage document')) {
            return $this->error('Permission denied.', 403);
        }

        $document = $this->visibleUploads()->where('id', $id)->first();
        if (!$document || empty($document->document)) {
            return $this->error('Document not found.', 404);
        }

        return $this->success([
            'name' => $document->document,
            'download_url' => $this->fileUrl($document),
        ], 'Download link generated.');
    }

    // Company users see every upload; everyone else sees uploads for their role or for all roles (role 0).
    private function visibleUploads()
    {
        $user = Auth::user();
        $query = DucumentUpload::where('created_by', $user->creatorId());

        if ($user->type != 'company') {
            $role = $user->roles->first();
            $query->whereIn('role', array_filter([0, $role ? $role->id : null], fn ($v) => $v !== null));
        }

        return $query;
    }

    private function formatUpload(DucumentUpload $doc)
    {
        return [
            'id' => $doc->id,
            'name' => $doc->name,
            'description' => $doc->description,
            'role' => (int) $doc->role,
            'file_name' => $doc->document,
            'file_url' => $doc->document ? $this->fileUrl($doc) : null,
            'created_at' => $doc->created_at,
        ];
    }

    private function fileUrl(DucumentUpload $doc)
    {
        return Utility::get_file('uploads/documentUpload') . '/' . $doc->document;
    }
}

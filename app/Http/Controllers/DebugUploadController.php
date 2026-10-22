<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DebugUploadController extends Controller
{
    public function index()
    {
        $phpInfo = [
            'file_uploads' => ini_get('file_uploads'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'php_version' => phpversion(),
        ];

        return view('debug.upload', ['phpInfo' => $phpInfo]);
    }

    public function test(Request $request)
    {
        $result = [
            'request_method' => $request->method(),
            'content_type' => $request->header('Content-Type'),
            'content_length' => $request->header('Content-Length'),
            'has_file' => $request->hasFile('file'),
            'all_files' => $request->allFiles(),
            'all_input' => $request->except(['_token']),
        ];

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $result['file_details'] = [
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'extension' => $file->getClientOriginalExtension(),
                'is_valid' => $file->isValid(),
                'error' => $file->getError(),
                'error_message' => $this->getUploadErrorMessage($file->getError()),
            ];

            // Test Laravel validation
            try {
                $request->validate([
                    'file' => 'required|mimes:csv,txt,xlsx,xls',
                ]);
                $result['validation'] = 'PASSED ✅';
            } catch (\Exception $e) {
                $result['validation'] = 'FAILED ❌: ' . $e->getMessage();
            }
        } else {
            $result['error'] = 'No file received in request';
        }

        $phpInfo = [
            'file_uploads' => ini_get('file_uploads'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'php_version' => phpversion(),
        ];

        return view('debug.upload', ['phpInfo' => $phpInfo, 'result' => $result]);
    }

    private function getUploadErrorMessage($code)
    {
        $errors = [
            UPLOAD_ERR_OK => 'لا يوجد خطأ، تم رفع الملف بنجاح',
            UPLOAD_ERR_INI_SIZE => 'الملف أكبر من upload_max_filesize في php.ini',
            UPLOAD_ERR_FORM_SIZE => 'الملف أكبر من MAX_FILE_SIZE في HTML form',
            UPLOAD_ERR_PARTIAL => 'تم رفع الملف جزئياً فقط',
            UPLOAD_ERR_NO_FILE => 'لم يتم رفع أي ملف',
            UPLOAD_ERR_NO_TMP_DIR => 'مجلد tmp مفقود',
            UPLOAD_ERR_CANT_WRITE => 'فشل كتابة الملف على القرص',
            UPLOAD_ERR_EXTENSION => 'امتداد PHP أوقف رفع الملف',
        ];

        return $errors[$code] ?? 'خطأ غير معروف: ' . $code;
    }
}

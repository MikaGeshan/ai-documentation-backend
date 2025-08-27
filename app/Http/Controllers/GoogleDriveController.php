<?php

namespace App\Http\Controllers;

use App\Services\GoogleTokenService;
use Google\Service\Drive as Google_Service_Drive;
use Google\Service\Docs as Google_Service_Docs;
use Google\Service\Drive\DriveFile as Google_Service_Drive_DriveFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GoogleDriveController extends Controller
{
     protected string $accountEmail;

    public function __construct()
    {
        $this->accountEmail = env('GOOGLE_ADMIN_EMAIL'); 
    }

    protected function getClient()
    {
        return GoogleTokenService::getAuthorizedClient($this->accountEmail);
    }

    protected function getDriveService()
    {
        return new Google_Service_Drive($this->getClient());
    }

    protected function getDocsService()
    {
        return new Google_Service_Docs($this->getClient());
    }

    public function getDriveContents()
    {
        try {
            $drive = $this->getDriveService();
            $parentFolderId = env('GOOGLE_DRIVE_FOLDER_ID');

            $mainFolder = $drive->files->get($parentFolderId, [
                'fields' => 'id, name, webViewLink'
            ]);

            $subfolders = $drive->files->listFiles([
                'q' => "'$parentFolderId' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
                'fields' => 'files(id, name, webViewLink)',
                'pageSize' => 1000
            ])->getFiles();

            $subfolderIds = array_map(fn($f) => $f->id, $subfolders);
            if (empty($subfolderIds)) {
                return response()->json([
                    'folder' => $mainFolder,
                    'subfolders' => []
                ]);
            }

            $query = '(' . implode(' or ', array_map(fn($id) => "'$id' in parents", $subfolderIds)) . ") and trashed = false";
            $files = $drive->files->listFiles([
                'q' => $query,
                'fields' => 'files(id, name, mimeType, webViewLink, iconLink, parents)',
                'pageSize' => 1000
            ])->getFiles();

            $groupedFiles = [];
            foreach ($files as $file) {
                foreach ($file->parents as $parentId) {
                    $groupedFiles[$parentId][] = $file;
                }
            }

            $subfolderData = array_map(fn($f) => [
                'id' => $f->id,
                'name' => $f->name,
                'webViewLink' => $f->webViewLink,
                'files' => $groupedFiles[$f->id] ?? []
            ], $subfolders);

            return response()->json([
                'folder' => $mainFolder,
                'subfolders' => $subfolderData
            ]);
        } catch (\Exception $e) {
            Log::error('GoogleDriveController@getDriveContents', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Failed to fetch Drive contents.',
                'details' => $e->getMessage()
            ], 500);
        }
    }

     public function createGoogleDriveFolder(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email'
        ]);

        $email = $request->email; 

        try {
            $client = GoogleTokenService::getAuthorizedClient($email);
            $service = new Google_Service_Drive($client);

            $folder = $service->files->create(new Google_Service_Drive_DriveFile([
                'name' => $request->name,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [env('GOOGLE_DRIVE_FOLDER_ID')],
            ]), [
                'fields' => 'id, name',
            ]);

            return response()->json([
                'message' => 'Folder berhasil dibuat',
                'folder' => $folder,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Gagal membuat folder.',
                'details' => $e->getMessage()
            ], 500);
        }
    }

     public function uploadFileToDrive(Request $request)
    {
        // Log::info('MASUK uploadFileToDrive', $request->all());

        $request->validate([
            'folder_id' => 'required|string',
            'file' => 'required|file|mimes:pdf|max:5120',
        ]);

        $email = $request->user()->email ?? $request->input('email');


        try {
            $client = GoogleTokenService::getAuthorizedClient($email);
            $service = new Google_Service_Drive($client);

            $file = $request->file('file');
            $driveFile = new Google_Service_Drive_DriveFile([
                'name' => $file->getClientOriginalName(),
                'parents' => [$request->input('folder_id')],
            ]);

            $uploaded = $service->files->create($driveFile, [
                'data' => file_get_contents($file->getPathname()),
                'mimeType' => $file->getClientMimeType(),
                'uploadType' => 'multipart',
            ]);

            Log::info('Upload success:', ['id' => $uploaded->id]);

            return response()->json([
                'message' => 'File berhasil diupload ke Google Drive.',
                'file_id' => $uploaded->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Upload Failed:', ['message' => $e->getMessage()]);
            return response()->json([
                'message' => 'Gagal upload file: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function deleteGoogleDocs(Request $request)
    {
        $request->validate(['file_id' => 'required|string']);
        $email = $request->user()->email ?? $request->input('email');


        try {
            $client = GoogleTokenService::getAuthorizedClient($email);
            $drive = new Google_Service_Drive($client);
            $drive->files->delete($request->input('file_id'));

            return response()->json(['message' => 'File berhasil dihapus.']);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Gagal menghapus file.',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function downloadGoogleDocs(Request $request)
    {
        $request->validate(['file_id' => 'required|string']);
        $fileId = $request->input('file_id');

        try {
            $client = $this->getClient();
            $drive = $this->getDriveService();

            $file = $drive->files->get($fileId, ['fields' => 'name']);
            $fileName = ($file->name ?? 'downloaded') . '.pdf';
            $exportUrl = "https://www.googleapis.com/drive/v3/files/{$fileId}/export?mimeType=application/pdf";

            $http = new \GuzzleHttp\Client();
            $response = $http->get($exportUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $client->getAccessToken()['access_token'],
                ],
                'stream' => true,
            ]);

            return response()->stream(fn() => print($response->getBody()->getContents()), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"$fileName\"",
            ]);
        } catch (\Exception $e) {
            Log::error('GoogleDriveController@downloadGoogleDocs', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Failed to download Google Doc.',
                'details' => $e->getMessage()
            ], 500);
        }
    }


    public function viewGoogleDocsAsPdf(Request $request)
    {
        $request->validate(['file_id' => 'required|string']);
        $fileId = $request->input('file_id');

        try {
            $client = $this->getClient();
            $drive = new Google_Service_Drive($client);
            $file = $drive->files->get($fileId, ['fields' => 'name']);
            $fileName = ($file->name ?? 'view') . '.pdf';

            $exportUrl = "https://www.googleapis.com/drive/v3/files/{$fileId}/export?mimeType=application/pdf";
            $http = new \GuzzleHttp\Client();
            $response = $http->get($exportUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $client->getAccessToken()['access_token'],
                ],
                'stream' => true,
            ]);

            return response()->stream(fn() => print($response->getBody()->getContents()), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "inline; filename=\"$fileName\"",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Gagal menampilkan dokumen sebagai PDF.',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function convertGoogleDocsToTxt(Request $request)
    {
        $request->validate(['file_id' => 'required|string']);
        $fileId = $request->input('file_id');
        $email = $request->user()->email ?? $request->input('email');

        try {
            $client = GoogleTokenService::getAuthorizedClient($email);
            $docs = new Google_Service_Docs($client);
            $bodyElements = $docs->documents->get($fileId)->getBody()->getContent();

            $text = '';
            foreach ($bodyElements as $element) {
                if (isset($element->paragraph)) {
                    foreach ($element->getParagraph()->getElements() as $pElement) {
                        $textRun = $pElement->getTextRun();
                        if ($textRun) {
                            $text .= $textRun->getContent();
                        }
                    }
                }
            }

            return response()->json(['file_id' => $fileId, 'text' => $text]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Gagal mengonversi Google Docs ke teks.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

   
}

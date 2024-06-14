<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;

class RecordingController extends Controller
{
    public function fetchRecording($number): JsonResponse
    {
        $accessKey = env('API_ACCESS_KEY');
        $secretKey = env('API_SECRET_KEY');

        if (!$accessKey || !$secretKey) {
            return response()->json(['error' => 'API credentials are not set'], 500);
        }

        $url = "https://api.maqsam.com/v1/recording/{$number}";

        try {
            $response = Http::withBasicAuth($accessKey, $secretKey)->get($url);

            if ($response->successful()) {
                $fileName = "recording_{$number}.mp3";
                Storage::disk('local')->put($fileName, $response->body());

                $filePath = storage_path("app/{$fileName}");
                $driveFileId = $this->uploadToDrive($filePath, $number);

                return response()->json(['success' => "Recording saved as {$fileName}"], 200);
            } else {
                return response()->json(['error' => "Failed to fetch recording: {$response->status()} - {$response->body()}"], $response->status());
            }
        } catch (\Exception $e) {
            Log::error("An error occurred: {$e->getMessage()}");
            return response()->json(['error' => "An error occurred: {$e->getMessage()}"], 500);
        }
    }

    private function uploadToDrive(string $filePath, string $fileName){
        $client = new Client();
        $client->addScope(Drive::DRIVE_FILE);

        $clientId = env('DRIVE_CLIENT_ID');
        $clientSecret = env('DRIVE_CLIENT_SECRET');
        $refreshToken = env('DRIVE_REFRESH_TOKEN');
        $accessTokenResponse = Http::post('https://oauth2.googleapis.com/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        ini_set('memory_limit', '-1');

        $accessToken = json_decode($accessTokenResponse->body(), true)['access_token'];


        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setAccessToken($accessToken);
        $client->refreshToken($refreshToken);

        $folderId = env('DRIVE_FOLDER_ID');

        $client->refreshToken($refreshToken);

        $service = new Drive($client);

        $fileMetadata = new DriveFile([
            'name' => $fileName,
            'parents' => [$folderId],
        ]);

        $content = file_get_contents($filePath);

        $file = $service->files->create($fileMetadata, [
            'data' => $content,
            'mimeType' => 'audio/mpeg',
            'uploadType' => 'multipart',
            'fields' => 'id',
        ]);

        return $file->id ?? null;
    }

    private function uploadToGoogleDrive(string $filePath, string $fileName)
    {
        $clientId = env('DRIVE_CLIENT_ID');
        $clientSecret = env('DRIVE_CLIENT_SECRET');
        $refreshToken = env('DRIVE_REFRESH_TOKEN');
        $folderId = env('DRIVE_FOLDER_ID');

        $accessTokenResponse = Http::post('https://oauth2.googleapis.com/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        ini_set('memory_limit', '-1');

        $accessToken = json_decode($accessTokenResponse->body(), true)['access_token'];

        $content = file_get_contents($filePath);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'Content-Type' => 'Application/json',
        ])->post('https://www.googleapis.com/drive/v3/files', [
            'data' => $content,
            'mimeType' => 'audio/mpeg',
            'uploadType' => 'multipart',
            'parents' => [$folderId],
        ]);

        if ($response->successful()) {
            return response('File uploaded successfully to drive', 200);
        } else {
            return response()->json(['error' => "Failed uploading to drive: {$response->status()} - {$response->body()}"], $response->status());
        }
    }
}

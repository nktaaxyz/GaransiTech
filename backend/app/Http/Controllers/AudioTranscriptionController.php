<?php

namespace App\Http\Controllers;

use App\Exceptions\TranscriptionFailedException;
use App\Exceptions\TranscriptionUnavailableException;
use App\Services\LocalWhisperTranscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AudioTranscriptionController extends Controller
{
    public function __invoke(Request $request, LocalWhisperTranscriber $transcriber): JsonResponse
    {
        $validated = $request->validate([
            'audio' => ['required', 'file', 'mimes:flac,mp3,m4a,ogg,wav,webm', 'max:20480'],
        ]);

        try {
            return response()->json([
                'text' => $transcriber->transcribe($validated['audio']),
                'language' => 'id',
            ]);
        } catch (TranscriptionUnavailableException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        } catch (TranscriptionFailedException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Audio tidak dapat ditranskripsikan. Silakan coba lagi.',
            ], 502);
        }
    }
}

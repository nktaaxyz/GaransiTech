<?php

namespace App\Services;

use App\Exceptions\TranscriptionFailedException;
use App\Exceptions\TranscriptionUnavailableException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

class LocalWhisperTranscriber
{
    public function transcribe(UploadedFile $audio): string
    {
        $model = config('services.local_whisper.model');

        if (! is_string($model) || $model === '' || ! is_file($model)) {
            throw new TranscriptionUnavailableException(
                'Model transkripsi lokal belum dikonfigurasi di server.',
            );
        }

        $workDirectory = storage_path('app/private/transcriptions/'.Str::uuid());
        File::ensureDirectoryExists($workDirectory);

        try {
            $inputPath = $audio->getRealPath();

            if (! is_string($inputPath)) {
                throw new TranscriptionFailedException('Uploaded audio file is not readable.');
            }

            $wavPath = $workDirectory.DIRECTORY_SEPARATOR.'audio.wav';
            $this->run('audio conversion', [
                (string) config('services.local_whisper.ffmpeg_binary'),
                '-hide_banner',
                '-loglevel',
                'error',
                '-nostdin',
                '-y',
                '-i',
                $inputPath,
                '-vn',
                '-ar',
                '16000',
                '-ac',
                '1',
                '-c:a',
                'pcm_s16le',
                $wavPath,
            ]);

            $outputBase = $workDirectory.DIRECTORY_SEPARATOR.'transcription';
            $this->run('speech recognition', [
                (string) config('services.local_whisper.binary'),
                '-m',
                $model,
                '-f',
                $wavPath,
                '-l',
                'id',
                '-nt',
                '-np',
                '-otxt',
                '-of',
                $outputBase,
            ]);

            $transcriptPath = $outputBase.'.txt';

            if (! is_file($transcriptPath)) {
                throw new TranscriptionFailedException('Local speech recognition produced no transcript file.');
            }

            $transcript = trim((string) file_get_contents($transcriptPath));

            if ($transcript === '') {
                throw new TranscriptionFailedException('Local speech recognition produced an empty transcript.');
            }

            return $transcript;
        } finally {
            if (is_dir($workDirectory) && ! File::deleteDirectory($workDirectory)) {
                Log::critical('Could not remove temporary audio transcription files.');
            }
        }
    }

    /**
     * @param  list<string>  $command
     */
    private function run(string $stage, array $command): void
    {
        try {
            $result = Process::timeout((int) config('services.local_whisper.timeout', 180))
                ->run($command);
        } catch (ProcessFailedException|ProcessTimedOutException $exception) {
            Log::warning('Local speech transcription process could not complete.', [
                'stage' => $stage,
                'exception' => $exception::class,
            ]);

            throw new TranscriptionFailedException(
                "Local transcription {$stage} process failed to start or timed out.",
                previous: $exception,
            );
        }

        if ($result->failed()) {
            Log::warning('Local speech transcription process returned an error.', [
                'stage' => $stage,
                'exit_code' => $result->exitCode(),
            ]);

            throw new TranscriptionFailedException(
                "Local transcription {$stage} process exited unsuccessfully.",
            );
        }
    }
}

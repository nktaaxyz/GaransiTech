<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LocalWhisperTranscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class AudioTranscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_authenticated_admin_can_transcribe_audio_in_indonesian(): void
    {
        $this->mock(LocalWhisperTranscriber::class, function (MockInterface $mock): void {
            $mock->shouldReceive('transcribe')
                ->once()
                ->andReturn('Layar laptop tidak menyala.');
        });

        $this->postJson('/api/claims/transcribe', [
            'audio' => $this->fakeWavUpload(),
        ])
            ->assertOk()
            ->assertExactJson([
                'text' => 'Layar laptop tidak menyala.',
                'language' => 'id',
            ]);
    }

    public function test_transcription_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards()->shouldUse('web');

        $this->postJson('/api/claims/transcribe')
            ->assertUnauthorized();
    }

    public function test_transcription_reports_when_the_local_model_is_not_configured(): void
    {
        config(['services.local_whisper.model' => null]);

        $this->postJson('/api/claims/transcribe', [
            'audio' => $this->fakeWavUpload(),
        ])
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'Model transkripsi lokal belum dikonfigurasi di server.');
    }

    public function test_transcription_rejects_non_audio_uploads(): void
    {
        $this->postJson('/api/claims/transcribe', [
            'audio' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['audio']);
    }

    private function fakeWavUpload(): UploadedFile
    {
        $wavHeader = 'RIFF'.pack('V', 36).'WAVEfmt '.pack('V', 16)
            .pack('v', 1).pack('v', 1).pack('V', 16000).pack('V', 32000)
            .pack('v', 2).pack('v', 16).'data'.pack('V', 0);

        return UploadedFile::fake()->createWithContent('recording.wav', $wavHeader);
    }
}

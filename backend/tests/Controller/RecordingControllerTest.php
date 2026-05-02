<?php

namespace App\Tests\Controller;

use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class RecordingControllerTest extends ApiTestCase
{
    private function makeAudioFile(string $mimeType = 'audio/x-wav'): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'rec_test_');
        // Minimal WAV header — fileinfo detects as audio/x-wav (in ALLOWED_MIME_TYPES)
        file_put_contents($tmp,
            "RIFF" . pack("V", 36) . "WAVE" .
            "fmt "  . pack("V", 16) . pack("v", 1) . pack("v", 1) .
            pack("V", 44100) . pack("V", 88200) . pack("v", 2) . pack("v", 16) .
            "data"  . pack("V", 0)
        );
        return new UploadedFile($tmp, 'test.wav', $mimeType, null, true);
    }

    private function makeInvalidFile(): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'rec_txt_');
        file_put_contents($tmp, str_repeat('x', 64));
        return new UploadedFile($tmp, 'test.txt', 'text/plain', null, true);
    }

    private function makeLargeAudioFile(): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'rec_large_');
        // WAV header + sparse body > 50 MB so filesize() > MAX_FILE_SIZE
        $header = "RIFF" . pack("V", 50 * 1024 * 1024 + 36) . "WAVE" .
                  "fmt "  . pack("V", 16) . pack("v", 1) . pack("v", 1) .
                  pack("V", 44100) . pack("V", 88200) . pack("v", 2) . pack("v", 16) .
                  "data"  . pack("V", 50 * 1024 * 1024);
        $fp = fopen($tmp, 'w');
        fwrite($fp, $header);
        fseek($fp, 50 * 1024 * 1024 + strlen($header));
        fwrite($fp, "\0");
        fclose($fp);
        return new UploadedFile($tmp, 'large.wav', 'audio/x-wav', null, true);
    }

    // ── GET /api/recordings ───────────────────────────────────────────────────

    public function testListAsTeacher(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_list_teacher');
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_list_member');
        $this->createRecording($member);

        $this->req('GET', '/api/recordings', [], 'tok_list_teacher');
        self::assertSame(200, $this->httpStatus());
        $data = $this->responseData();
        self::assertCount(1, $data);
        self::assertArrayHasKey('family', $data[0]);
    }

    public function testListAsFamilyMember(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_list_fm');
        $this->createRecording($member);

        $this->req('GET', '/api/recordings', [], 'tok_list_fm');
        self::assertSame(200, $this->httpStatus());
        self::assertCount(1, $this->responseData());
    }

    public function testListAsFamilyMemberSeesOnlyOwnFamily(): void
    {
        $family1 = $this->createFamily('F1');
        $family2 = $this->createFamily('F2');
        $member1 = $this->createMember($family1, 'm1@t.com', 'tok_f1');
        $member2 = $this->createMember($family2, 'm2@t.com', 'tok_f2');
        $this->createRecording($member1);
        $this->createRecording($member2);

        $this->req('GET', '/api/recordings', [], 'tok_f1');
        self::assertCount(1, $this->responseData());
    }

    // ── POST /api/recordings (upload) ─────────────────────────────────────────

    private function upload(UploadedFile $file, string $token): void
    {
        $this->em->clear();
        $this->client->request('POST', '/api/recordings', [], ['audio' => $file], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);
    }

    public function testUploadForbiddenForTeacher(): void
    {
        $this->createTeacher('t@t.com', 'tok_upload_teacher');
        $this->upload($this->makeAudioFile(), 'tok_upload_teacher');
        self::assertSame(403, $this->httpStatus());
    }

    public function testUploadConflictAlreadyUploadedToday(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_upload_conflict');
        $this->createRecording($member); // recording with today's timestamp

        $this->upload($this->makeAudioFile(), 'tok_upload_conflict');
        self::assertSame(409, $this->httpStatus());
    }

    public function testUploadNoFile(): void
    {
        $family = $this->createFamily();
        $this->createMember($family, 'm@t.com', 'tok_no_file');
        $this->em->clear();
        $this->client->request('POST', '/api/recordings', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer tok_no_file',
        ]);
        self::assertSame(400, $this->httpStatus());
    }

    public function testUploadInvalidMimeType(): void
    {
        $family = $this->createFamily();
        $this->createMember($family, 'm@t.com', 'tok_bad_mime');
        $this->upload($this->makeInvalidFile(), 'tok_bad_mime');
        self::assertSame(422, $this->httpStatus());
    }

    public function testUploadFileTooLarge(): void
    {
        $family = $this->createFamily();
        $this->createMember($family, 'm@t.com', 'tok_too_large');
        $this->upload($this->makeLargeAudioFile(), 'tok_too_large');
        self::assertSame(422, $this->httpStatus());
        self::assertStringContainsString('large', $this->responseData()['error']);
    }

    public function testUploadSuccess(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_upload_ok');
        $this->upload($this->makeAudioFile(), 'tok_upload_ok');
        self::assertSame(201, $this->httpStatus());
        self::assertArrayHasKey('id', $this->responseData());
    }

    public function testUploadDirectoryNotWritable(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_not_writable');

        // Create dir as non-writable
        $userDir = self::TEST_RECORDINGS_DIR . '/' . $member->getId();
        mkdir($userDir, 0755, true);
        chmod($userDir, 0555);

        $this->upload($this->makeAudioFile(), 'tok_not_writable');

        chmod($userDir, 0755); // restore before asserting
        self::assertSame(500, $this->httpStatus());
    }

    public function testUploadMkdirFails(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_mkdir_fail');

        // Block mkdir by placing a file at the expected dir path
        $userDir = self::TEST_RECORDINGS_DIR . '/' . $member->getId();
        file_put_contents($userDir, 'blocker'); // file where dir should be

        $this->upload($this->makeAudioFile(), 'tok_mkdir_fail');
        @unlink($userDir); // cleanup
        self::assertSame(500, $this->httpStatus());
    }

    // ── DELETE /api/recordings/{id} ───────────────────────────────────────────

    public function testDeleteNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_del_nf');
        $this->req('POST', '/api/recordings/99999/delete', [], 'tok_del_nf');
        self::assertSame(404, $this->httpStatus());
    }

    public function testDeleteForbiddenForOtherMember(): void
    {
        $family = $this->createFamily();
        $member1 = $this->createMember($family, 'm1@t.com', 'tok_del_owner');
        $member2 = $this->createMember($family, 'm2@t.com', 'tok_del_other');
        $rec = $this->createRecording($member1);

        $this->req('POST', '/api/recordings/' . $rec->getId() . '/delete', [], 'tok_del_other');
        self::assertSame(403, $this->httpStatus());
    }

    public function testDeleteOwnRecordingWithFile(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_del_own');
        $rec = $this->createRecording($member);
        $this->createRecordingFile($member->getId(), $rec->getFilename());

        $this->req('POST', '/api/recordings/' . $rec->getId() . '/delete', [], 'tok_del_own');
        self::assertSame(204, $this->httpStatus());
        self::assertFileDoesNotExist(self::TEST_RECORDINGS_DIR . '/' . $member->getId() . '/' . $rec->getFilename());
    }

    public function testDeleteRecordingFileNotOnDisk(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_del_nodisk');
        $rec = $this->createRecording($member);
        // No file on disk — should still delete DB entry

        $this->req('POST', '/api/recordings/' . $rec->getId() . '/delete', [], 'tok_del_nodisk');
        self::assertSame(204, $this->httpStatus());
    }

    public function testDeleteAsTeacher(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_del_teacher');
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_del_member_t');
        $rec = $this->createRecording($member);

        $this->req('POST', '/api/recordings/' . $rec->getId() . '/delete', [], 'tok_del_teacher');
        self::assertSame(204, $this->httpStatus());
    }

    // ── GET /api/recordings/{id}/audio ────────────────────────────────────────

    public function testAudioNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_audio_nf');
        $this->req('GET', '/api/recordings/99999/audio', [], 'tok_audio_nf');
        self::assertSame(404, $this->httpStatus());
    }

    public function testAudioAccessDenied(): void
    {
        $family1 = $this->createFamily('F1');
        $family2 = $this->createFamily('F2');
        $member1 = $this->createMember($family1, 'm1@t.com', 'tok_audio_owner');
        $member2 = $this->createMember($family2, 'm2@t.com', 'tok_audio_other');
        $rec = $this->createRecording($member1);

        $this->req('GET', '/api/recordings/' . $rec->getId() . '/audio', [], 'tok_audio_other');
        self::assertSame(403, $this->httpStatus());
    }

    public function testAudioFileNotOnDisk(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_audio_nodisk');
        $rec = $this->createRecording($member);
        // No file on disk

        $this->req('GET', '/api/recordings/' . $rec->getId() . '/audio', [], 'tok_audio_nodisk');
        self::assertSame(404, $this->httpStatus());
    }

    public function testAudioSuccess(): void
    {
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_audio_ok');
        $rec = $this->createRecording($member);
        $this->createRecordingFile($member->getId(), $rec->getFilename(), 'fake webm data');

        $this->req('GET', '/api/recordings/' . $rec->getId() . '/audio', [], 'tok_audio_ok');
        self::assertSame(200, $this->httpStatus());
    }
}

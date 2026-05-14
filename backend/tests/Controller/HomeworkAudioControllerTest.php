<?php

namespace App\Tests\Controller;

use App\Entity\HomeworkAudio;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class HomeworkAudioControllerTest extends ApiTestCase
{
    private function makeAudioFile(): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'hw_aud_');
        // Minimal valid WebM header bytes
        file_put_contents($tmp, "\x1a\x45\xdf\xa3" . str_repeat("\x00", 60));
        return new UploadedFile($tmp, 'test.webm', 'audio/webm', null, true);
    }

    private function makeInvalidFile(): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'hw_txt_');
        file_put_contents($tmp, str_repeat('x', 64));
        return new UploadedFile($tmp, 'test.txt', 'text/plain', null, true);
    }

    private function persistHomeworkAudio(
        \App\Entity\Lesson $lesson,
        \App\Entity\Family $family,
        string $filename = 'test_audio.webm',
        string $hwType = 'lesen'
    ): HomeworkAudio {
        $audio = new HomeworkAudio();
        $audio->setLesson($lesson)
              ->setFamily($family)
              ->setHomeworkType($hwType)
              ->setFilename($filename)
              ->setMimeType('audio/webm')
              ->setFileSize(1024);
        $this->em->persist($audio);
        $this->em->flush();

        if (!is_dir(self::TEST_HOMEWORK_AUDIO_DIR)) {
            mkdir(self::TEST_HOMEWORK_AUDIO_DIR, 0755, true);
        }
        file_put_contents(self::TEST_HOMEWORK_AUDIO_DIR . '/' . $filename, 'fake_audio_data');

        return $audio;
    }

    // ── POST /api/lessons/{id}/homework-audio ─────────────────────────────────

    public function testUploadForbiddenForTeacher(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwaup_teacher');
        $lesson = $this->createLesson('2025-06-01', null, true, ['lesen']);
        $this->req('POST', '/api/lessons/' . $lesson->getId() . '/homework-audio', [], 'tok_hwaup_teacher', ['audio' => $this->makeAudioFile()], ['homework_type' => 'lesen']);
        self::assertSame(403, $this->httpStatus());
    }

    public function testUploadLessonNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwaup_nf_t');
        $family = $this->createFamily();
        $this->createMember($family, 'm@t.com', 'tok_hwaup_nf');
        $this->req('POST', '/api/lessons/99999/homework-audio', [], 'tok_hwaup_nf', ['audio' => $this->makeAudioFile()], ['homework_type' => 'lesen']);
        self::assertSame(404, $this->httpStatus());
    }

    public function testUploadInvalidHomeworkType(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwaup_inv_t_t');
        $family = $this->createFamily();
        $this->createMember($family, 'm@t.com', 'tok_hwaup_inv_t');
        $lesson = $this->createLesson('2025-06-01', null, true, ['lesen']);
        // 'schreiben' is a photo type, not an audio type
        $this->req('POST', '/api/lessons/' . $lesson->getId() . '/homework-audio', [], 'tok_hwaup_inv_t', ['audio' => $this->makeAudioFile()], ['homework_type' => 'schreiben']);
        self::assertSame(422, $this->httpStatus());
    }

    public function testUploadTypeNotAssignedForLesson(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwaup_nohw_t');
        $family = $this->createFamily();
        $this->createMember($family, 'm@t.com', 'tok_hwaup_nohw');
        $lesson = $this->createLesson('2025-06-01', null, true, ['sonstiges']); // only sonstiges, not lesen
        $this->req('POST', '/api/lessons/' . $lesson->getId() . '/homework-audio', [], 'tok_hwaup_nohw', ['audio' => $this->makeAudioFile()], ['homework_type' => 'lesen']);
        self::assertSame(422, $this->httpStatus());
    }

    public function testUploadMissingHomeworkType(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwaup_notype_t');
        $family = $this->createFamily();
        $this->createMember($family, 'm@t.com', 'tok_hwaup_notype');
        $lesson = $this->createLesson('2025-06-01', null, true, ['lesen']);
        $this->req('POST', '/api/lessons/' . $lesson->getId() . '/homework-audio', [], 'tok_hwaup_notype', ['audio' => $this->makeAudioFile()]);
        self::assertSame(422, $this->httpStatus());
    }

    public function testUploadNoFile(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwaup_nofile_t');
        $family = $this->createFamily();
        $this->createMember($family, 'm@t.com', 'tok_hwaup_nofile');
        $lesson = $this->createLesson('2025-06-01', null, true, ['lesen']);
        $this->req('POST', '/api/lessons/' . $lesson->getId() . '/homework-audio', [], 'tok_hwaup_nofile', [], ['homework_type' => 'lesen']);
        self::assertSame(400, $this->httpStatus());
    }

    public function testUploadInvalidFileType(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwaup_inv_t');
        $family = $this->createFamily();
        $this->createMember($family, 'm@t.com', 'tok_hwaup_inv');
        $lesson = $this->createLesson('2025-06-01', null, true, ['lesen']);
        $this->req('POST', '/api/lessons/' . $lesson->getId() . '/homework-audio', [], 'tok_hwaup_inv', ['audio' => $this->makeInvalidFile()], ['homework_type' => 'lesen']);
        self::assertSame(422, $this->httpStatus());
    }

    public function testUploadSuccess(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwaup_ok_t');
        $family = $this->createFamily('Muster');
        $this->createMember($family, 'm@t.com', 'tok_hwaup_ok');
        $lesson = $this->createLesson('2025-06-01', null, true, ['lesen']);

        $this->req('POST', '/api/lessons/' . $lesson->getId() . '/homework-audio', [], 'tok_hwaup_ok', ['audio' => $this->makeAudioFile()], ['homework_type' => 'lesen']);
        self::assertSame(201, $this->httpStatus());
        $data = $this->responseData();
        self::assertArrayHasKey('id', $data);
        self::assertSame('lesen', $data['homeworkType']);
        self::assertSame('Muster', $data['familyName']);
        self::assertArrayHasKey('fileSize', $data);
    }

    // ── GET /api/homework-audio/{id}/stream ───────────────────────────────────

    public function testStreamNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwastream_nf');
        $this->req('GET', '/api/homework-audio/99999/stream', [], 'tok_hwastream_nf');
        self::assertSame(404, $this->httpStatus());
    }

    public function testStreamWrongFamily(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwastream_wf_t');
        $family1 = $this->createFamily('F1');
        $family2 = $this->createFamily('F2');
        $this->createMember($family2, 'm2@t.com', 'tok_hwastream_wf');
        $lesson = $this->createLesson('2025-06-01', null, true, ['lesen']);
        $audio  = $this->persistHomeworkAudio($lesson, $family1, 'aud_wf.webm');

        $this->req('GET', '/api/homework-audio/' . $audio->getId() . '/stream', [], 'tok_hwastream_wf');
        self::assertSame(403, $this->httpStatus());
    }

    public function testStreamAsOwnFamily(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwastream_ok_t');
        $family = $this->createFamily('Muster');
        $this->createMember($family, 'm@t.com', 'tok_hwastream_ok');
        $lesson = $this->createLesson('2025-06-01', null, true, ['lesen']);
        $audio  = $this->persistHomeworkAudio($lesson, $family, 'aud_ok.webm');

        $this->req('GET', '/api/homework-audio/' . $audio->getId() . '/stream', [], 'tok_hwastream_ok');
        self::assertSame(200, $this->httpStatus());
    }

    public function testStreamAsTeacher(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwastream_teacher');
        $family = $this->createFamily('Muster');
        $lesson = $this->createLesson('2025-06-01', null, true, ['lesen']);
        $audio  = $this->persistHomeworkAudio($lesson, $family, 'aud_teacher.webm');

        $this->req('GET', '/api/homework-audio/' . $audio->getId() . '/stream', [], 'tok_hwastream_teacher');
        self::assertSame(200, $this->httpStatus());
    }

    // ── POST /api/homework-audio/{id}/delete ─────────────────────────────────

    public function testDeleteNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwadel_nf');
        $this->req('POST', '/api/homework-audio/99999/delete', [], 'tok_hwadel_nf');
        self::assertSame(404, $this->httpStatus());
    }

    public function testDeleteWrongFamily(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwadel_wf_t');
        $family1 = $this->createFamily('F1');
        $family2 = $this->createFamily('F2');
        $this->createMember($family2, 'm2@t.com', 'tok_hwadel_wf');
        $lesson = $this->createLesson('2025-06-01', null, true, ['lesen']);
        $audio  = $this->persistHomeworkAudio($lesson, $family1, 'aud_del_wf.webm');

        $this->req('POST', '/api/homework-audio/' . $audio->getId() . '/delete', [], 'tok_hwadel_wf');
        self::assertSame(403, $this->httpStatus());
    }

    public function testDeleteOwnAudio(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwadel_ok_t');
        $family = $this->createFamily('Muster');
        $this->createMember($family, 'm@t.com', 'tok_hwadel_ok');
        $lesson = $this->createLesson('2025-06-01', null, true, ['lesen']);
        $audio  = $this->persistHomeworkAudio($lesson, $family, 'aud_del_ok.webm');

        $this->req('POST', '/api/homework-audio/' . $audio->getId() . '/delete', [], 'tok_hwadel_ok');
        self::assertSame(204, $this->httpStatus());
        self::assertFalse(file_exists(self::TEST_HOMEWORK_AUDIO_DIR . '/aud_del_ok.webm'));
    }

    public function testDeleteAsTeacher(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwadel_teacher');
        $family = $this->createFamily('Muster');
        $lesson = $this->createLesson('2025-06-01', null, true, ['lesen']);
        $audio  = $this->persistHomeworkAudio($lesson, $family, 'aud_del_teacher.webm');

        $this->req('POST', '/api/homework-audio/' . $audio->getId() . '/delete', [], 'tok_hwadel_teacher');
        self::assertSame(204, $this->httpStatus());
    }
}

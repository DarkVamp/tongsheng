<?php

namespace App\Tests\Controller;

use App\Entity\FeedbackAttachment;
use App\Entity\FeedbackMessage;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FeedbackControllerTest extends ApiTestCase
{
    private function makeAudioFile(): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'fb_aud_');
        $wav = 'RIFF' . pack('V', 36) . 'WAVE'
            . 'fmt ' . pack('V', 16) . pack('v', 1) . pack('v', 1)
            . pack('V', 44100) . pack('V', 88200) . pack('v', 2) . pack('v', 16)
            . 'data' . pack('V', 0);
        file_put_contents($tmp, $wav);
        return new UploadedFile($tmp, 'feedback.wav', 'audio/wav', null, true);
    }

    private function makeImageFile(): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'fb_img_');
        // Minimales 1×1 PNG
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        file_put_contents($tmp, $png);
        return new UploadedFile($tmp, 'feedback.png', 'image/png', null, true);
    }

    private function makeInvalidFile(): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'fb_bad_');
        file_put_contents($tmp, str_repeat('x', 64));
        return new UploadedFile($tmp, 'bad.txt', 'text/plain', null, true);
    }

    private function persistMessage(
        \App\Entity\Lesson $lesson,
        \App\Entity\User $student,
        \App\Entity\User $teacher,
        ?string $text = 'Sehr gut!'
    ): FeedbackMessage {
        $msg = new FeedbackMessage();
        $msg->setLesson($lesson)
            ->setStudent($student)
            ->setAuthor($teacher)
            ->setText($text);
        $this->em->persist($msg);
        $this->em->flush();
        return $msg;
    }

    private function persistAttachment(
        FeedbackMessage $message,
        string $filename = 'att.wav',
        string $type = 'audio'
    ): FeedbackAttachment {
        $att = new FeedbackAttachment();
        $att->setMessage($message)
            ->setType($type)
            ->setFilename($filename)
            ->setMimeType($type === 'audio' ? 'audio/wav' : 'image/png')
            ->setFileSize(512);
        $this->em->persist($att);
        $this->em->flush();

        file_put_contents(self::TEST_FEEDBACK_DIR . '/' . $filename, 'fakedata');
        return $att;
    }

    // ── GET /api/lessons/{id}/feedback ────────────────────────────────────────

    public function testListLessonNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_fb_list_nf');
        $this->req('GET', '/api/lessons/99999/feedback', [], 'tok_fb_list_nf');
        self::assertSame(404, $this->httpStatus());
    }

    public function testListAsTeacherReturnsAllMessages(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_fb_list_t');
        $family1 = $this->createFamily('F1');
        $family2 = $this->createFamily('F2');
        $s1 = $this->createStudent($family1, 's1@t.com', 'tok_fb_s1', 'Kind1');
        $s2 = $this->createStudent($family2, 's2@t.com', 'tok_fb_s2', 'Kind2');
        $lesson = $this->createLesson('2025-06-01');

        $this->persistMessage($lesson, $s1, $teacher, 'Gut!');
        $this->persistMessage($lesson, $s2, $teacher, 'Prima!');

        $this->req('GET', '/api/lessons/' . $lesson->getId() . '/feedback', [], 'tok_fb_list_t');
        self::assertSame(200, $this->httpStatus());
        $data = $this->responseData();
        self::assertCount(2, $data);
    }

    public function testListAsFamilyReturnsOwnMessages(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_fb_list_fam_t');
        $family1 = $this->createFamily('F1');
        $family2 = $this->createFamily('F2');
        $s1 = $this->createStudent($family1, 's1@t.com', 'tok_fb_s1_fam', 'Kind1');
        $s2 = $this->createStudent($family2, 's2@t.com', 'tok_fb_s2_fam', 'Kind2');
        $this->createMember($family1, 'm1@t.com', 'tok_fb_m1_fam');
        $lesson = $this->createLesson('2025-06-01');

        $this->persistMessage($lesson, $s1, $teacher, 'Gut!');
        $this->persistMessage($lesson, $s2, $teacher, 'Prima!');

        $this->req('GET', '/api/lessons/' . $lesson->getId() . '/feedback', [], 'tok_fb_m1_fam');
        self::assertSame(200, $this->httpStatus());
        $data = $this->responseData();
        self::assertCount(1, $data);
        self::assertSame('Kind1', $data[0]['studentName']);
    }

    // ── POST /api/lessons/{lessonId}/feedback/{studentId} ────────────────────

    public function testCreateForbiddenForFamilyMember(): void
    {
        $this->createTeacher('t@t.com', 'tok_fb_cr_t');
        $family = $this->createFamily();
        $student = $this->createStudent($family, 's@t.com', 'tok_fb_cr_s');
        $this->createMember($family, 'm@t.com', 'tok_fb_cr_m');
        $lesson = $this->createLesson('2025-06-01');

        $this->req('POST', '/api/lessons/' . $lesson->getId() . '/feedback/' . $student->getId(), ['text' => 'Test'], 'tok_fb_cr_m');
        self::assertSame(403, $this->httpStatus());
    }

    public function testCreateLessonNotFound(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_fb_cr_nf_t');
        $family  = $this->createFamily();
        $student = $this->createStudent($family, 's@t.com', 'tok_fb_cr_nf_s');

        $this->req('POST', '/api/lessons/99999/feedback/' . $student->getId(), ['text' => 'Test'], 'tok_fb_cr_nf_t');
        self::assertSame(404, $this->httpStatus());
    }

    public function testCreateStudentNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_fb_cr_nosit');
        $lesson = $this->createLesson('2025-06-01');

        $this->req('POST', '/api/lessons/' . $lesson->getId() . '/feedback/99999', ['text' => 'Test'], 'tok_fb_cr_nosit');
        self::assertSame(404, $this->httpStatus());
    }

    public function testCreateNonStudentRejected(): void
    {
        $this->createTeacher('t@t.com', 'tok_fb_cr_nonstud_t');
        $family = $this->createFamily();
        $member = $this->createMember($family, 'm@t.com', 'tok_fb_cr_nonstud_m');
        $lesson = $this->createLesson('2025-06-01');

        // member is not a student (isStudent=false)
        $this->req('POST', '/api/lessons/' . $lesson->getId() . '/feedback/' . $member->getId(), ['text' => 'Test'], 'tok_fb_cr_nonstud_t');
        self::assertSame(404, $this->httpStatus());
    }

    public function testCreateSuccess(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_fb_cr_ok_t');
        $family  = $this->createFamily('Muster');
        $student = $this->createStudent($family, 's@t.com', 'tok_fb_cr_ok_s', 'KindMuster');
        $lesson  = $this->createLesson('2025-06-01');

        $this->req('POST', '/api/lessons/' . $lesson->getId() . '/feedback/' . $student->getId(), ['text' => 'Sehr gut!'], 'tok_fb_cr_ok_t');
        self::assertSame(201, $this->httpStatus());
        $data = $this->responseData();
        self::assertArrayHasKey('id', $data);
        self::assertSame('KindMuster', $data['studentName']);
        self::assertSame('Sehr gut!', $data['text']);
        self::assertSame([], $data['attachments']);
    }

    public function testCreateWithoutTextSucceeds(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_fb_cr_notext_t');
        $family  = $this->createFamily('Muster');
        $student = $this->createStudent($family, 's@t.com', 'tok_fb_cr_notext_s');
        $lesson  = $this->createLesson('2025-06-01');

        $this->req('POST', '/api/lessons/' . $lesson->getId() . '/feedback/' . $student->getId(), [], 'tok_fb_cr_notext_t');
        self::assertSame(201, $this->httpStatus());
        self::assertNull($this->responseData()['text']);
    }

    // ── PATCH /api/feedback/messages/{id} ────────────────────────────────────

    public function testPatchNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_fb_patch_nf');
        $this->req('PATCH', '/api/feedback/messages/99999', ['text' => 'x'], 'tok_fb_patch_nf');
        self::assertSame(404, $this->httpStatus());
    }

    public function testPatchForbiddenForFamily(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_fb_patch_forb_t');
        $family  = $this->createFamily();
        $student = $this->createStudent($family, 's@t.com', 'tok_fb_patch_forb_s');
        $this->createMember($family, 'm@t.com', 'tok_fb_patch_forb_m');
        $lesson  = $this->createLesson('2025-06-01');
        $msg     = $this->persistMessage($lesson, $student, $teacher);

        $this->req('PATCH', '/api/feedback/messages/' . $msg->getId(), ['text' => 'neu'], 'tok_fb_patch_forb_m');
        self::assertSame(403, $this->httpStatus());
    }

    public function testPatchText(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_fb_patch_ok_t');
        $family  = $this->createFamily();
        $student = $this->createStudent($family, 's@t.com', 'tok_fb_patch_ok_s');
        $lesson  = $this->createLesson('2025-06-01');
        $msg     = $this->persistMessage($lesson, $student, $teacher, 'Alt');

        $this->req('PATCH', '/api/feedback/messages/' . $msg->getId(), ['text' => 'Neu'], 'tok_fb_patch_ok_t');
        self::assertSame(200, $this->httpStatus());
        self::assertSame('Neu', $this->responseData()['text']);
    }

    // ── POST /api/feedback/messages/{id}/attachment ───────────────────────────

    public function testUploadAttachmentNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_fb_att_nf');
        $this->req('POST', '/api/feedback/messages/99999/attachment', [], 'tok_fb_att_nf', ['file' => $this->makeAudioFile()]);
        self::assertSame(404, $this->httpStatus());
    }

    public function testUploadAttachmentForbiddenForFamily(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_fb_att_forb_t');
        $family  = $this->createFamily();
        $student = $this->createStudent($family, 's@t.com', 'tok_fb_att_forb_s');
        $this->createMember($family, 'm@t.com', 'tok_fb_att_forb_m');
        $lesson  = $this->createLesson('2025-06-01');
        $msg     = $this->persistMessage($lesson, $student, $teacher);

        $this->req('POST', '/api/feedback/messages/' . $msg->getId() . '/attachment', [], 'tok_fb_att_forb_m', ['file' => $this->makeAudioFile()]);
        self::assertSame(403, $this->httpStatus());
    }

    public function testUploadAttachmentNoFile(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_fb_att_nofile_t');
        $family  = $this->createFamily();
        $student = $this->createStudent($family, 's@t.com', 'tok_fb_att_nofile_s');
        $lesson  = $this->createLesson('2025-06-01');
        $msg     = $this->persistMessage($lesson, $student, $teacher);

        $this->req('POST', '/api/feedback/messages/' . $msg->getId() . '/attachment', [], 'tok_fb_att_nofile_t');
        self::assertSame(400, $this->httpStatus());
    }

    public function testUploadAttachmentInvalidType(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_fb_att_inv_t');
        $family  = $this->createFamily();
        $student = $this->createStudent($family, 's@t.com', 'tok_fb_att_inv_s');
        $lesson  = $this->createLesson('2025-06-01');
        $msg     = $this->persistMessage($lesson, $student, $teacher);

        $this->req('POST', '/api/feedback/messages/' . $msg->getId() . '/attachment', [], 'tok_fb_att_inv_t', ['file' => $this->makeInvalidFile()]);
        self::assertSame(422, $this->httpStatus());
    }

    public function testUploadAudioAttachmentSuccess(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_fb_att_aud_t');
        $family  = $this->createFamily();
        $student = $this->createStudent($family, 's@t.com', 'tok_fb_att_aud_s');
        $lesson  = $this->createLesson('2025-06-01');
        $msg     = $this->persistMessage($lesson, $student, $teacher);

        $this->req('POST', '/api/feedback/messages/' . $msg->getId() . '/attachment', [], 'tok_fb_att_aud_t', ['file' => $this->makeAudioFile()]);
        self::assertSame(201, $this->httpStatus());
        $data = $this->responseData();
        self::assertSame('audio', $data['type']);
        self::assertArrayHasKey('id', $data);
    }

    public function testUploadImageAttachmentSuccess(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_fb_att_img_t');
        $family  = $this->createFamily();
        $student = $this->createStudent($family, 's@t.com', 'tok_fb_att_img_s');
        $lesson  = $this->createLesson('2025-06-01');
        $msg     = $this->persistMessage($lesson, $student, $teacher);

        $this->req('POST', '/api/feedback/messages/' . $msg->getId() . '/attachment', [], 'tok_fb_att_img_t', ['file' => $this->makeImageFile()]);
        self::assertSame(201, $this->httpStatus());
        $data = $this->responseData();
        self::assertSame('image', $data['type']);
    }

    // ── GET /api/feedback/attachments/{id}/stream ─────────────────────────────

    public function testStreamAttachmentNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_fb_str_nf');
        $this->req('GET', '/api/feedback/attachments/99999/stream', [], 'tok_fb_str_nf');
        self::assertSame(404, $this->httpStatus());
    }

    public function testStreamAttachmentForbiddenForOtherFamily(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_fb_str_forb_t');
        $family1 = $this->createFamily('F1');
        $family2 = $this->createFamily('F2');
        $s1      = $this->createStudent($family1, 's1@t.com', 'tok_fb_str_s1', 'Kind1');
        $this->createMember($family2, 'm2@t.com', 'tok_fb_str_m2');
        $lesson  = $this->createLesson('2025-06-01');
        $msg     = $this->persistMessage($lesson, $s1, $teacher);
        $att     = $this->persistAttachment($msg, 'str_forb.wav');

        $this->req('GET', '/api/feedback/attachments/' . $att->getId() . '/stream', [], 'tok_fb_str_m2');
        self::assertSame(403, $this->httpStatus());
    }

    public function testStreamAttachmentAsOwnFamily(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_fb_str_own_t');
        $family  = $this->createFamily('Muster');
        $student = $this->createStudent($family, 's@t.com', 'tok_fb_str_s');
        $this->createMember($family, 'm@t.com', 'tok_fb_str_m');
        $lesson  = $this->createLesson('2025-06-01');
        $msg     = $this->persistMessage($lesson, $student, $teacher);
        $att     = $this->persistAttachment($msg, 'str_own.wav');

        $this->req('GET', '/api/feedback/attachments/' . $att->getId() . '/stream', [], 'tok_fb_str_m');
        self::assertSame(200, $this->httpStatus());
    }

    public function testStreamAttachmentAsTeacher(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_fb_str_teach_t');
        $family  = $this->createFamily();
        $student = $this->createStudent($family, 's@t.com', 'tok_fb_str_teach_s');
        $lesson  = $this->createLesson('2025-06-01');
        $msg     = $this->persistMessage($lesson, $student, $teacher);
        $att     = $this->persistAttachment($msg, 'str_teach.wav');

        $this->req('GET', '/api/feedback/attachments/' . $att->getId() . '/stream', [], 'tok_fb_str_teach_t');
        self::assertSame(200, $this->httpStatus());
    }

    // ── POST /api/feedback/messages/{id}/delete ───────────────────────────────

    public function testDeleteMessageNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_fb_dmsg_nf');
        $this->req('POST', '/api/feedback/messages/99999/delete', [], 'tok_fb_dmsg_nf');
        self::assertSame(404, $this->httpStatus());
    }

    public function testDeleteMessageForbiddenForFamily(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_fb_dmsg_forb_t');
        $family  = $this->createFamily();
        $student = $this->createStudent($family, 's@t.com', 'tok_fb_dmsg_forb_s');
        $this->createMember($family, 'm@t.com', 'tok_fb_dmsg_forb_m');
        $lesson  = $this->createLesson('2025-06-01');
        $msg     = $this->persistMessage($lesson, $student, $teacher);

        $this->req('POST', '/api/feedback/messages/' . $msg->getId() . '/delete', [], 'tok_fb_dmsg_forb_m');
        self::assertSame(403, $this->httpStatus());
    }

    public function testDeleteMessageDeletesAttachmentFiles(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_fb_dmsg_ok_t');
        $family  = $this->createFamily();
        $student = $this->createStudent($family, 's@t.com', 'tok_fb_dmsg_ok_s');
        $lesson  = $this->createLesson('2025-06-01');
        $msg     = $this->persistMessage($lesson, $student, $teacher);
        $att     = $this->persistAttachment($msg, 'del_msg.wav');

        $msgId = $msg->getId();
        self::assertFileExists(self::TEST_FEEDBACK_DIR . '/del_msg.wav');

        $this->req('POST', '/api/feedback/messages/' . $msgId . '/delete', [], 'tok_fb_dmsg_ok_t');
        self::assertSame(204, $this->httpStatus());
        self::assertFileDoesNotExist(self::TEST_FEEDBACK_DIR . '/del_msg.wav');
    }

    // ── POST /api/feedback/attachments/{id}/delete ────────────────────────────

    public function testDeleteAttachmentNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_fb_datt_nf');
        $this->req('POST', '/api/feedback/attachments/99999/delete', [], 'tok_fb_datt_nf');
        self::assertSame(404, $this->httpStatus());
    }

    public function testDeleteAttachmentForbiddenForFamily(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_fb_datt_forb_t');
        $family  = $this->createFamily();
        $student = $this->createStudent($family, 's@t.com', 'tok_fb_datt_forb_s');
        $this->createMember($family, 'm@t.com', 'tok_fb_datt_forb_m');
        $lesson  = $this->createLesson('2025-06-01');
        $msg     = $this->persistMessage($lesson, $student, $teacher);
        $att     = $this->persistAttachment($msg, 'del_att_forb.wav');

        $this->req('POST', '/api/feedback/attachments/' . $att->getId() . '/delete', [], 'tok_fb_datt_forb_m');
        self::assertSame(403, $this->httpStatus());
    }

    public function testDeleteAttachmentSuccess(): void
    {
        $teacher = $this->createTeacher('t@t.com', 'tok_fb_datt_ok_t');
        $family  = $this->createFamily();
        $student = $this->createStudent($family, 's@t.com', 'tok_fb_datt_ok_s');
        $lesson  = $this->createLesson('2025-06-01');
        $msg     = $this->persistMessage($lesson, $student, $teacher);
        $att     = $this->persistAttachment($msg, 'del_att_ok.wav');

        $attId = $att->getId();
        self::assertFileExists(self::TEST_FEEDBACK_DIR . '/del_att_ok.wav');

        $this->req('POST', '/api/feedback/attachments/' . $attId . '/delete', [], 'tok_fb_datt_ok_t');
        self::assertSame(204, $this->httpStatus());
        self::assertFileDoesNotExist(self::TEST_FEEDBACK_DIR . '/del_att_ok.wav');
    }
}

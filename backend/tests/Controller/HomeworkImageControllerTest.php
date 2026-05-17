<?php

namespace App\Tests\Controller;

use App\Entity\HomeworkImage;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class HomeworkImageControllerTest extends ApiTestCase
{
    private function makeImageFile(): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'hw_img_');
        // Minimal 1×1 PNG — finfo detects as image/png
        file_put_contents($tmp, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        ));
        return new UploadedFile($tmp, 'test.png', 'image/png', null, true);
    }

    private function makeInvalidFile(): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'hw_txt_');
        file_put_contents($tmp, str_repeat('x', 64));
        return new UploadedFile($tmp, 'test.txt', 'text/plain', null, true);
    }

    private function persistHomeworkAudioForImageTest(
        \App\Entity\Lesson $lesson,
        \App\Entity\Family $family,
        string $hwType = 'lesen'
    ): \App\Entity\HomeworkAudio {
        $audio = new \App\Entity\HomeworkAudio();
        $audio->setLesson($lesson)
              ->setFamily($family)
              ->setHomeworkType($hwType)
              ->setFilename('test_audio.webm')
              ->setMimeType('audio/webm')
              ->setFileSize(1024);
        $this->em->persist($audio);
        $this->em->flush();
        return $audio;
    }

    private function persistHomeworkImage(
        \App\Entity\Lesson $lesson,
        \App\Entity\Family $family,
        string $filename = 'test_img.png',
        string $hwType = 'schreiben'
    ): HomeworkImage {
        $img = new HomeworkImage();
        $img->setLesson($lesson)
            ->setFamily($family)
            ->setHomeworkType($hwType)
            ->setFilePath($filename)
            ->setOriginalFilename('photo.png')
            ->setMimeType('image/png');
        $this->em->persist($img);
        $this->em->flush();

        if (!is_dir(self::TEST_HOMEWORK_DIR)) {
            mkdir(self::TEST_HOMEWORK_DIR, 0755, true);
        }
        file_put_contents(self::TEST_HOMEWORK_DIR . '/' . $filename, 'fake_png_data');

        return $img;
    }

    // ── GET /api/lessons/latest-homework ─────────────────────────────────────

    public function testLatestHomeworkNoLesson(): void
    {
        $family = $this->createFamily();
        $this->createMember($family, 'm@t.com', 'tok_lh_none');
        $this->req('GET', '/api/lessons/latest-homework', [], 'tok_lh_none');
        self::assertSame(200, $this->httpStatus());
        self::assertNull(json_decode($this->client->getResponse()->getContent(), true));
    }

    public function testLatestHomeworkAsFamilyNoImages(): void
    {
        $this->createTeacher('t@t.com', 'tok_lh_fm_empty_t');
        $family  = $this->createFamily('Muster');
        $this->createMember($family, 'm@t.com', 'tok_lh_fm_empty');
        $this->createLesson('2025-06-01', null, true, ['schreiben']);

        $this->req('GET', '/api/lessons/latest-homework', [], 'tok_lh_fm_empty');
        self::assertSame(200, $this->httpStatus());
        $data = $this->responseData();
        self::assertArrayHasKey('lesson', $data);
        self::assertArrayHasKey('images', $data);
        self::assertArrayHasKey('audio', $data);
        self::assertSame([], $data['images']);
        self::assertSame([], $data['audio']);
        self::assertTrue($data['lesson']['homeworkAssigned']);
    }

    public function testLatestHomeworkAsFamilyWithImages(): void
    {
        $this->createTeacher('t@t.com', 'tok_lh_fm_imgs_t');
        $family  = $this->createFamily('Muster');
        $this->createMember($family, 'm@t.com', 'tok_lh_fm_imgs');
        $lesson  = $this->createLesson('2025-06-01', null, true, ['schreiben']);
        $this->persistHomeworkImage($lesson, $family);

        $this->req('GET', '/api/lessons/latest-homework', [], 'tok_lh_fm_imgs');
        self::assertSame(200, $this->httpStatus());
        $data = $this->responseData();
        self::assertArrayHasKey('audio', $data);
        self::assertSame([], $data['audio']);
        self::assertCount(1, $data['images']);
        self::assertSame('photo.png', $data['images'][0]['originalFilename']);
    }

    public function testLatestHomeworkAsFamilyWithAudio(): void
    {
        $this->createTeacher('t@t.com', 'tok_lh_fm_aud_t');
        $family  = $this->createFamily('Muster');
        $this->createMember($family, 'm@t.com', 'tok_lh_fm_aud');
        $lesson  = $this->createLesson('2025-06-01', null, true, ['lesen']);
        $this->persistHomeworkAudioForImageTest($lesson, $family);

        $this->req('GET', '/api/lessons/latest-homework', [], 'tok_lh_fm_aud');
        self::assertSame(200, $this->httpStatus());
        $data = $this->responseData();
        self::assertSame([], $data['images']);
        self::assertCount(1, $data['audio']);
        self::assertSame('lesen', $data['audio'][0]['homeworkType']);
    }

    public function testLatestHomeworkAsTeacher(): void
    {
        $this->createTeacher('t@t.com', 'tok_lh_teacher');
        $family = $this->createFamily('Muster');
        $lesson = $this->createLesson('2025-06-01', null, true, ['schreiben']);

        $this->req('GET', '/api/lessons/latest-homework', [], 'tok_lh_teacher');
        self::assertSame(200, $this->httpStatus());
        $data = $this->responseData();
        self::assertArrayHasKey('lesson', $data);
        self::assertArrayHasKey('families', $data);
        self::assertCount(1, $data['families']);
        self::assertSame('Muster', $data['families'][0]['name']);
        self::assertFalse($data['families'][0]['submitted']);
    }

    // ── POST /api/lessons/{id}/homework ───────────────────────────────────────

    public function testUploadForbiddenForTeacher(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwup_teacher');
        $lesson = $this->createLesson('2025-06-01', null, true, ['schreiben']);
        $this->req('POST', '/api/lessons/' . $lesson->getId() . '/homework', [], 'tok_hwup_teacher', ['image' => $this->makeImageFile()], ['homework_type' => 'schreiben']);
        self::assertSame(403, $this->httpStatus());
    }

    public function testUploadLessonNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwup_nf_t');
        $family = $this->createFamily();
        $this->createMember($family, 'm@t.com', 'tok_hwup_nf');
        $this->req('POST', '/api/lessons/99999/homework', [], 'tok_hwup_nf', ['image' => $this->makeImageFile()], ['homework_type' => 'schreiben']);
        self::assertSame(404, $this->httpStatus());
    }

    public function testUploadTypeNotAssignedForLesson(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwup_nohw_t');
        $family = $this->createFamily();
        $this->createMember($family, 'm@t.com', 'tok_hwup_nohw');
        $lesson = $this->createLesson('2025-06-01', null, false); // homeworkTypes = null
        $this->req('POST', '/api/lessons/' . $lesson->getId() . '/homework', [], 'tok_hwup_nohw', ['image' => $this->makeImageFile()], ['homework_type' => 'schreiben']);
        self::assertSame(422, $this->httpStatus());
    }

    public function testUploadMissingHomeworkType(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwup_notype_t');
        $family = $this->createFamily();
        $this->createMember($family, 'm@t.com', 'tok_hwup_notype');
        $lesson = $this->createLesson('2025-06-01', null, true, ['schreiben']);
        $this->req('POST', '/api/lessons/' . $lesson->getId() . '/homework', [], 'tok_hwup_notype', ['image' => $this->makeImageFile()]);
        self::assertSame(422, $this->httpStatus());
    }

    public function testUploadNoFile(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwup_nofile_t');
        $family = $this->createFamily();
        $this->createMember($family, 'm@t.com', 'tok_hwup_nofile');
        $lesson = $this->createLesson('2025-06-01', null, true, ['schreiben']);
        $this->req('POST', '/api/lessons/' . $lesson->getId() . '/homework', [], 'tok_hwup_nofile', [], ['homework_type' => 'schreiben']);
        self::assertSame(400, $this->httpStatus());
    }

    public function testUploadInvalidFileType(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwup_inv_t');
        $family = $this->createFamily();
        $this->createMember($family, 'm@t.com', 'tok_hwup_inv');
        $lesson = $this->createLesson('2025-06-01', null, true, ['schreiben']);
        $this->req('POST', '/api/lessons/' . $lesson->getId() . '/homework', [], 'tok_hwup_inv', ['image' => $this->makeInvalidFile()], ['homework_type' => 'schreiben']);
        self::assertSame(422, $this->httpStatus());
    }

    public function testUploadSuccess(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwup_ok_t');
        $family = $this->createFamily('Muster');
        $this->createMember($family, 'm@t.com', 'tok_hwup_ok');
        $lesson = $this->createLesson('2025-06-01', null, true, ['schreiben']);

        $this->req('POST', '/api/lessons/' . $lesson->getId() . '/homework', [], 'tok_hwup_ok', ['image' => $this->makeImageFile()], ['homework_type' => 'schreiben']);
        self::assertSame(201, $this->httpStatus());
        $data = $this->responseData();
        self::assertArrayHasKey('id', $data);
        self::assertSame('image/png', $data['mimeType']);
        self::assertSame('Muster', $data['familyName']);
        self::assertSame('test.png', $data['originalFilename']);
        self::assertSame('schreiben', $data['homeworkType']);
    }

    // ── GET /api/homework/{id}/image ──────────────────────────────────────────

    public function testServeImageNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwserve_nf');
        $this->req('GET', '/api/homework/99999/image', [], 'tok_hwserve_nf');
        self::assertSame(404, $this->httpStatus());
    }

    public function testServeImageWrongFamily(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwserve_wf_t');
        $family1 = $this->createFamily('F1');
        $family2 = $this->createFamily('F2');
        $this->createMember($family2, 'm2@t.com', 'tok_hwserve_wf');
        $lesson  = $this->createLesson('2025-06-01', null, true, ['schreiben']);
        $img     = $this->persistHomeworkImage($lesson, $family1, 'img_wf.png');

        $this->req('GET', '/api/homework/' . $img->getId() . '/image', [], 'tok_hwserve_wf');
        self::assertSame(403, $this->httpStatus());
    }

    public function testServeImageAsOwnFamily(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwserve_ok_t');
        $family = $this->createFamily('Muster');
        $this->createMember($family, 'm@t.com', 'tok_hwserve_ok');
        $lesson = $this->createLesson('2025-06-01', null, true, ['schreiben']);
        $img    = $this->persistHomeworkImage($lesson, $family, 'img_ok.png');

        $this->req('GET', '/api/homework/' . $img->getId() . '/image', [], 'tok_hwserve_ok');
        self::assertSame(200, $this->httpStatus());
    }

    public function testServeImageAsTeacher(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwserve_teacher');
        $family = $this->createFamily('Muster');
        $lesson = $this->createLesson('2025-06-01', null, true, ['schreiben']);
        $img    = $this->persistHomeworkImage($lesson, $family, 'img_teacher.png');

        $this->req('GET', '/api/homework/' . $img->getId() . '/image', [], 'tok_hwserve_teacher');
        self::assertSame(200, $this->httpStatus());
    }

    // ── POST /api/homework/{id}/delete ────────────────────────────────────────

    public function testDeleteNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwdel_nf');
        $this->req('POST', '/api/homework/99999/delete', [], 'tok_hwdel_nf');
        self::assertSame(404, $this->httpStatus());
    }

    public function testDeleteWrongFamily(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwdel_wf_t');
        $family1 = $this->createFamily('F1');
        $family2 = $this->createFamily('F2');
        $this->createMember($family2, 'm2@t.com', 'tok_hwdel_wf');
        $lesson  = $this->createLesson('2025-06-01', null, true, ['schreiben']);
        $img     = $this->persistHomeworkImage($lesson, $family1, 'img_del_wf.png');

        $this->req('POST', '/api/homework/' . $img->getId() . '/delete', [], 'tok_hwdel_wf');
        self::assertSame(403, $this->httpStatus());
    }

    public function testDeleteOwnImage(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwdel_ok_t');
        $family = $this->createFamily('Muster');
        $this->createMember($family, 'm@t.com', 'tok_hwdel_ok');
        $lesson = $this->createLesson('2025-06-01', null, true, ['schreiben']);
        $img    = $this->persistHomeworkImage($lesson, $family, 'img_del_ok.png');

        $this->req('POST', '/api/homework/' . $img->getId() . '/delete', [], 'tok_hwdel_ok');
        self::assertSame(204, $this->httpStatus());
        self::assertFalse(file_exists(self::TEST_HOMEWORK_DIR . '/img_del_ok.png'));
    }

    public function testDeleteAsTeacher(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwdel_teacher');
        $family = $this->createFamily('Muster');
        $lesson = $this->createLesson('2025-06-01', null, true, ['schreiben']);
        $img    = $this->persistHomeworkImage($lesson, $family, 'img_del_teacher.png');

        $this->req('POST', '/api/homework/' . $img->getId() . '/delete', [], 'tok_hwdel_teacher');
        self::assertSame(204, $this->httpStatus());
    }

    // ── GET /api/lessons/{id}/homework/all ───────────────────────────────────

    public function testAllForLessonForbiddenForFamilyMember(): void
    {
        $family = $this->createFamily();
        $this->createMember($family, 'm@t.com', 'tok_hwall_fm');
        $this->req('GET', '/api/lessons/1/homework/all', [], 'tok_hwall_fm');
        self::assertSame(403, $this->httpStatus());
    }

    public function testAllForLessonNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwall_nf');
        $this->req('GET', '/api/lessons/99999/homework/all', [], 'tok_hwall_nf');
        self::assertSame(404, $this->httpStatus());
    }

    public function testAllForLessonAsTeacher(): void
    {
        $this->createTeacher('t@t.com', 'tok_hwall_ok');
        $family1 = $this->createFamily('Alpha');
        $family2 = $this->createFamily('Beta');
        $lesson  = $this->createLesson('2025-06-01', null, true, ['schreiben']);
        $this->persistHomeworkImage($lesson, $family1, 'img_all_alpha.png');

        $this->req('GET', '/api/lessons/' . $lesson->getId() . '/homework/all', [], 'tok_hwall_ok');
        self::assertSame(200, $this->httpStatus());
        $data = $this->responseData();
        self::assertArrayHasKey('lesson', $data);
        self::assertArrayHasKey('families', $data);
        self::assertCount(2, $data['families']);

        $byName = array_column($data['families'], null, 'name');
        self::assertTrue($byName['Alpha']['submitted']);
        self::assertCount(1, $byName['Alpha']['images']);
        self::assertFalse($byName['Beta']['submitted']);
        self::assertSame([], $byName['Beta']['images']);
    }

    // ── GET /api/lessons/{id}/family-homework ────────────────────────────────

    public function testFamilyHomeworkForbiddenForTeacher(): void
    {
        $this->createTeacher('t@t.com', 'tok_famhw_teacher');
        $lesson = $this->createLesson('2025-06-01');

        $this->req('GET', '/api/lessons/' . $lesson->getId() . '/family-homework', [], 'tok_famhw_teacher');
        self::assertSame(403, $this->httpStatus());
    }

    public function testFamilyHomeworkNotFound(): void
    {
        $this->createTeacher('t@t.com', 'tok_famhw_notfound_t');
        $family = $this->createFamily();
        $this->createMember($family, 'm@t.com', 'tok_famhw_notfound');

        $this->req('GET', '/api/lessons/99999/family-homework', [], 'tok_famhw_notfound');
        self::assertSame(404, $this->httpStatus());
    }

    public function testFamilyHomeworkEmptyLesson(): void
    {
        $this->createTeacher('t@t.com', 'tok_famhw_empty_t');
        $family = $this->createFamily();
        $this->createMember($family, 'm@t.com', 'tok_famhw_empty');
        $lesson = $this->createLesson('2025-06-01', null, true, ['schreiben']);

        $this->req('GET', '/api/lessons/' . $lesson->getId() . '/family-homework', [], 'tok_famhw_empty');
        self::assertSame(200, $this->httpStatus());

        $data = $this->responseData();
        self::assertSame([], $data['images']);
        self::assertSame([], $data['audio']);
    }

    public function testFamilyHomeworkWithImageAndAudio(): void
    {
        $this->createTeacher('t@t.com', 'tok_famhw_data_t');
        $family = $this->createFamily();
        $this->createMember($family, 'm@t.com', 'tok_famhw_data');
        $lesson = $this->createLesson('2025-06-01', null, true, ['schreiben', 'lesen']);
        $this->persistHomeworkImage($lesson, $family, 'famhw_img.png', 'schreiben');
        $this->persistHomeworkAudioForImageTest($lesson, $family, 'lesen');

        $this->req('GET', '/api/lessons/' . $lesson->getId() . '/family-homework', [], 'tok_famhw_data');
        self::assertSame(200, $this->httpStatus());

        $data = $this->responseData();
        self::assertCount(1, $data['images']);
        self::assertSame('schreiben', $data['images'][0]['homeworkType']);
        self::assertCount(1, $data['audio']);
        self::assertSame('lesen', $data['audio'][0]['homeworkType']);
    }

    public function testFamilyHomeworkOnlyOwnFamily(): void
    {
        $this->createTeacher('t@t.com', 'tok_famhw_own_t');
        $familyA = $this->createFamily('Alpha');
        $familyB = $this->createFamily('Beta');
        $this->createMember($familyA, 'a@t.com', 'tok_famhw_own');
        $this->createMember($familyB, 'b@t.com', 'tok_famhw_other');
        $lesson = $this->createLesson('2025-06-01', null, true, ['schreiben']);
        $this->persistHomeworkImage($lesson, $familyB, 'famhw_other.png', 'schreiben');

        $this->req('GET', '/api/lessons/' . $lesson->getId() . '/family-homework', [], 'tok_famhw_own');
        self::assertSame(200, $this->httpStatus());
        self::assertSame([], $this->responseData()['images']);
    }
}

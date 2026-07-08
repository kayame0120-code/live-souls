<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendancePhoto;
use App\Models\User;
use App\Services\PhotoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\FileFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 参戦写真（spec §4・§7 テスト化必須）:
 * 他ユーザーは閲覧可・削除403 / 6枚目拒否 / 10MB超拒否 / EXIF除去。
 */
class PhotoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $other;
    private Attendance $attendance;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->user = User::factory()->create();
        $this->other = User::factory()->create();

        $this->attendance = Attendance::create([
            'user_id' => $this->user->id,
            'event_name' => '公演',
            'event_date' => '2026-06-01',
        ]);
    }

    private function uploadPhoto(): AttendancePhoto
    {
        $this->actingAs($this->user);
        $file = UploadedFile::fake()->image('photo.jpg', 640, 480);

        return app(PhotoService::class)->store($this->attendance, $file);
    }

    public function test_投稿者本人は写真を削除できる(): void
    {
        $photo = $this->uploadPhoto();

        $this->actingAs($this->user)
            ->delete(route('photos.destroy', $photo))
            ->assertRedirect();

        $this->assertDatabaseMissing('attendance_photos', ['id' => $photo->id]);
    }

    public function test_他ユーザーは閲覧できるが削除は403(): void
    {
        $photo = $this->uploadPhoto();

        // 閲覧は全メンバー可（規約0-6）
        $this->actingAs($this->other)
            ->get(route('photos.show', $photo))
            ->assertOk();

        // 削除は投稿者のみ
        $this->actingAs($this->other)
            ->delete(route('photos.destroy', $photo))
            ->assertStatus(403);

        $this->assertDatabaseHas('attendance_photos', ['id' => $photo->id]);
    }

    public function test_6枚目のアップロードは拒否される(): void
    {
        $this->actingAs($this->user);

        // 既存5枚
        foreach (range(1, 5) as $i) {
            app(PhotoService::class)->store($this->attendance, UploadedFile::fake()->image("p{$i}.jpg"));
        }

        $response = $this->put(route('attendances.update', $this->attendance), [
            'event_name' => '公演',
            'event_date' => '2026-06-01',
            'status' => 'attended',
            'photos' => [UploadedFile::fake()->image('sixth.jpg')],
        ]);

        $response->assertSessionHasErrors('photos');
        $this->assertSame(5, $this->attendance->photos()->count());
    }

    public function test_10MB超のアップロードは拒否される(): void
    {
        $this->actingAs($this->user);

        $tooLarge = (new FileFactory())->create('big.jpg', 10241, 'image/jpeg'); // 10MB + 1KB

        $response = $this->put(route('attendances.update', $this->attendance), [
            'event_name' => '公演',
            'event_date' => '2026-06-01',
            'status' => 'attended',
            'photos' => [$tooLarge],
        ]);

        $response->assertSessionHasErrors('photos.0');
        $this->assertSame(0, $this->attendance->photos()->count());
    }

    public function test_保存時にEXIFが除去される(): void
    {
        $this->actingAs($this->user);

        // GDでJPEGを生成し、EXIF(APP1)セグメントを注入したファイルを作る
        $img = imagecreatetruecolor(64, 48);
        ob_start();
        imagejpeg($img);
        $jpeg = ob_get_clean();
        imagedestroy($img);

        // SOI(FFD8) 直後に APP1 "Exif" セグメントを挿入
        $exifPayload = "Exif\x00\x00FAKE-GPS-METADATA";
        $app1 = "\xFF\xE1" . pack('n', strlen($exifPayload) + 2) . $exifPayload;
        $jpegWithExif = substr($jpeg, 0, 2) . $app1 . substr($jpeg, 2);

        $this->assertStringContainsString('Exif', $jpegWithExif);

        $tmp = tempnam(sys_get_temp_dir(), 'exif');
        file_put_contents($tmp, $jpegWithExif);
        $file = new UploadedFile($tmp, 'with-exif.jpg', 'image/jpeg', null, true);

        $photo = app(PhotoService::class)->store($this->attendance, $file);

        $stored = Storage::disk('local')->get($photo->path);
        $this->assertNotEmpty($stored);
        // 再エンコードによりEXIFセグメントが存在しないこと
        $this->assertStringNotContainsString('Exif', $stored);
    }
}

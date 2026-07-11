<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendancePhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * 参戦写真の保存・削除（spec §4 attendance_photos）。
 * 保存時にGDで再エンコードし、EXIF等のメタデータ（GPS含む）を除去する。
 * heic は環境上デコード不可のため受付対象外（QUESTIONS.md Q5 参照）。
 */
class PhotoService
{
    public const MAX_PHOTOS_PER_ATTENDANCE = 5;

    public function store(Attendance $attendance, UploadedFile $file): AttendancePhoto
    {
        [$binary, $extension] = $this->reencodeWithoutMetadata($file);

        $path = sprintf(
            'attendance-photos/%d/%s.%s',
            $attendance->id,
            Str::uuid(),
            $extension,
        );

        Storage::disk($this->disk())->put($path, $binary);

        return AttendancePhoto::create([
            'attendance_id' => $attendance->id,
            'user_id' => Auth::id(),
            'path' => $path,
        ]);
    }

    public function delete(AttendancePhoto $photo): void
    {
        Storage::disk($this->disk())->delete($photo->path);
        $photo->delete();
    }

    public function fileResponse(AttendancePhoto $photo)
    {
        return Storage::disk($this->disk())->response($photo->path);
    }

    public function disk(): string
    {
        return config('filesystems.photo_disk', 'local');
    }

    /**
     * GDで再エンコードしてメタデータ（EXIF/GPS等）を除去する。
     *
     * @return array{0: string, 1: string} [バイナリ, 拡張子]
     */
    private function reencodeWithoutMetadata(UploadedFile $file): array
    {
        $source = file_get_contents($file->getRealPath());
        $image = @imagecreatefromstring($source);

        if ($image === false) {
            throw new RuntimeException('画像を読み込めませんでした');
        }

        $mime = $file->getMimeType();

        ob_start();
        switch ($mime) {
            case 'image/png':
                imagesavealpha($image, true);
                imagepng($image);
                $extension = 'png';
                break;
            case 'image/webp':
                imagewebp($image, null, 90);
                $extension = 'webp';
                break;
            default:
                imagejpeg($image, null, 90);
                $extension = 'jpg';
                break;
        }
        $binary = ob_get_clean();
        imagedestroy($image);

        return [$binary, $extension];
    }
}

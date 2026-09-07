<?php

namespace App\Services;

use App\Models\Upload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Setasign\Fpdi\Tcpdf\Fpdi;

class WatermarkService
{
    public function create(UploadedFile $file, string $userId, ?Upload $previous): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $watermarkIds = array_values(array_unique(array_merge($previous?->watermark_ids ?? [], [$userId])));
        $sourcePath = $previous ? Storage::disk('local')->path($previous->stored_path) : $file->getRealPath();

        if ($this->isImage($extension)) {
            $processedPath = $this->watermarkImage($sourcePath, $extension, $watermarkIds);
            $mime = $this->imageMime($extension);
        } elseif ($extension === 'pdf') {
            $processedPath = $this->watermarkPdf($sourcePath, $watermarkIds);
            $mime = 'application/pdf';
        } elseif ($this->isSpreadsheet($extension)) {
            $processedPath = $this->watermarkSpreadsheet($sourcePath, $watermarkIds);
            $mime = $file->getMimeType() ?: 'application/octet-stream';
        } else {
            throw new RuntimeException('This file type is not supported yet.');
        }

        return ['path' => $processedPath, 'mime' => $mime, 'extension' => $extension, 'watermark_ids' => $watermarkIds];
    }

    private function watermarkImage(string $sourcePath, string $extension, array $watermarkIds): string
    {
        $image = imagecreatefromstring((string) file_get_contents($sourcePath));
        if ($image === false) throw new RuntimeException('The image could not be read.');
        imagesavealpha($image, true);
        $width = imagesx($image);
        $height = imagesy($image);
        $font = 5;
        $text = 'WATERMARK: '.implode(' | ', $watermarkIds);
        $stepX = max(imagefontwidth($font) * strlen($text) + 80, 260);
        $stepY = max(imagefontheight($font) + 55, 100);
        $color = imagecolorallocatealpha($image, 180, 30, 30, 65);
        $background = imagecolorallocatealpha($image, 255, 255, 255, 95);

        for ($y = -$height; $y < $height * 2; $y += $stepY) {
            for ($x = -$width; $x < $width * 2; $x += $stepX) {
                imagestring($image, $font, $x + 1, $y + 1, $text, $background);
                imagestring($image, $font, $x, $y, $text, $color);
            }
        }

        $outputPath = $this->temporaryPath($extension);
        $saved = match ($extension) {
            'jpg', 'jpeg' => imagejpeg($image, $outputPath, 92),
            'png' => imagepng($image, $outputPath, 6),
            'gif' => imagegif($image, $outputPath),
            'webp' => imagewebp($image, $outputPath, 92),
            default => false,
        };
        imagedestroy($image);
        if (!$saved) throw new RuntimeException('The watermarked image could not be saved.');
        return $outputPath;
    }

    private function watermarkPdf(string $sourcePath, array $watermarkIds): string
    {
        $pdf = new Fpdi;
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pageCount = $pdf->setSourceFile($sourcePath);
        $text = 'WATERMARK: '.implode(' | ', $watermarkIds);

        for ($page = 1; $page <= $pageCount; $page++) {
            $template = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($template);
            $pdf->AddPage($size['width'] > $size['height'] ? 'L' : 'P', [$size['width'], $size['height']]);
            $pdf->useTemplate($template);
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetTextColor(160, 25, 25);
            $pdf->SetAlpha(0.32);
            for ($y = 20; $y < $size['height']; $y += 45) {
                for ($x = 8; $x < $size['width']; $x += 78) $pdf->Text($x, $y, $text);
            }
            $pdf->SetAlpha(1);
        }

        $outputPath = $this->temporaryPath('pdf');
        $pdf->Output($outputPath, 'F');
        return $outputPath;
    }

    private function watermarkSpreadsheet(string $sourcePath, array $watermarkIds): string
    {
        $spreadsheet = IOFactory::load($sourcePath);
        $text = 'WATERMARK: '.implode(' | ', $watermarkIds);
        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $headerFooter = $worksheet->getHeaderFooter();
            $headerFooter->setOddHeader('&L'.$text);
            $headerFooter->setEvenHeader('&L'.$text);
            $headerFooter->setOddFooter('&L'.$text);
            $headerFooter->setEvenFooter('&L'.$text);
        }
        $outputPath = $this->temporaryPath(pathinfo($sourcePath, PATHINFO_EXTENSION));
        IOFactory::createWriter($spreadsheet, IOFactory::identify($sourcePath))->save($outputPath);
        return $outputPath;
    }

    private function temporaryPath(string $extension): string { return sys_get_temp_dir().'\\'.Str::uuid().'.'.$extension; }
    private function isImage(string $extension): bool { return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true); }
    private function isSpreadsheet(string $extension): bool { return in_array($extension, ['xlsx', 'xls', 'ods', 'csv'], true); }
    private function imageMime(string $extension): string { return match ($extension) { 'jpg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', default => 'application/octet-stream' }; }
}
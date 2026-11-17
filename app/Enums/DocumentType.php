<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Kinds of stored file, with the upload rules for each.
 */
enum DocumentType: string implements HasColor, HasLabel
{
    case Cv = 'cv';
    case Photo = 'photo';
    case IntroVideo = 'intro_video';
    case IdDocument = 'id_document';
    case Certificate = 'certificate';
    case Contract = 'contract';
    case Invoice = 'invoice';
    case PaymentSlip = 'payment_slip';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cv => __('CV'),
            self::Photo => __('Profile photo'),
            self::IntroVideo => __('Introduction video'),
            self::IdDocument => __('ID document'),
            self::Certificate => __('Certificate'),
            self::Contract => __('Contract'),
            self::Invoice => __('Invoice'),
            self::PaymentSlip => __('Payment slip'),
            self::Other => __('Other'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Cv => 'gray',
            self::Photo => 'gray',
            self::IntroVideo => 'gray',
            self::IdDocument => 'gray',
            self::Certificate => 'gray',
            self::Contract => 'gray',
            self::Invoice => 'gray',
            self::PaymentSlip => 'gray',
            self::Other => 'gray',
        };
    }

    /**
     * Allowed MIME types for this kind of file.
     *
     * @return list<string>
     */
    public function mimeTypes(): array
    {
        return match ($this) {
            self::Photo => ['image/jpeg', 'image/png', 'image/webp'],
            self::IntroVideo => ['video/mp4', 'video/quicktime', 'video/webm'],
            self::Cv => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            default => ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
        };
    }

    /** Maximum size in kilobytes. */
    public function maxKilobytes(): int
    {
        return match ($this) {
            self::IntroVideo => 30 * 1024, // a 30-second phone clip
            self::Photo => 5 * 1024,
            default => 10 * 1024,
        };
    }

    /**
     * What a freelancer may upload about themselves.
     *
     * @return list<self>
     */
    public static function freelancerUploadable(): array
    {
        return [self::Cv, self::Photo, self::IntroVideo, self::IdDocument, self::Certificate];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\CustomersRegions\Services;

use App\Domain\CustomersRegions\Contracts\QrToken;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

final class CustomerQrPresenter
{
    public function dataUri(QrToken $token): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => true,
            'addQuietzone' => true,
            'quietzoneSize' => 4,
        ]);

        return (string) (new QRCode($options))->render($token->value());
    }
}
